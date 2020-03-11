<?php
/**
 * BSD 3-Clause License
 *
 * Copyright (c) 2020, TASoft Applications
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *  Redistributions of source code must retain the above copyright notice, this
 *   list of conditions and the following disclaimer.
 *
 *  Redistributions in binary form must reproduce the above copyright notice,
 *   this list of conditions and the following disclaimer in the documentation
 *   and/or other materials provided with the distribution.
 *
 *  Neither the name of the copyright holder nor the names of its
 *   contributors may be used to endorse or promote products derived from
 *   this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace Ikarus\SPS\Common\Plugin\Cyclic;

use Ikarus\SPS\Common\MySQLCommonPluginManagement;
use Ikarus\SPS\Common\Plugin\CommonConnectorInterface;
use Ikarus\SPS\CyclicEngineInterface;
use Ikarus\SPS\EngineInterface;
use Ikarus\SPS\Exception\SPSException;
use Ikarus\SPS\Plugin\Cyclic\CallbackCyclicPlugin;
use Ikarus\SPS\Plugin\EngineDependentPluginInterface;
use Ikarus\SPS\Plugin\SetupPluginInterface;
use Ikarus\SPS\Plugin\TearDownPluginInterface;
use TASoft\Util\PDO;

/**
 * The forked external plugin is very special.
 * On SPS engine startup it will be detached into a separate process and runs under an own pluginmanagement (Typically MySQL plugin management)
 *
 * Class ForkedExternalCommonPlugin
 * @package Ikarus\SPS\Common\Plugin\Cyclic
 */
class ForkedExternalCommonPlugin extends CallbackCyclicPlugin implements SetupPluginInterface, TearDownPluginInterface, EngineDependentPluginInterface
{
    private $processID;
    /** @var CommonConnectorInterface */
    private $connector;
    private $frequency;

    /** @var CyclicEngineInterface */
    private $mainEngine;

    public function setEngine(?EngineInterface $engine)
    {
        $this->mainEngine = $engine;
    }


    public function __construct(string $identifier, callable $callback, CommonConnectorInterface $connector, int $frequency = 0)
    {
        if(!function_exists('pcntl_fork'))
            throw new SPSException("Forked plugins are only available if the php extension PCNTL is installed");

        parent::__construct($identifier, $callback);
        $this->connector = $connector;
        $this->frequency = $frequency;
    }

    public function setup()
    {
        switch ( $this->processID = pcntl_fork() ) {
            case -1:
                throw new SPSException("Can not fork the current process");
            case 0:
                // Is child process
                $this->startEngineSimulation();
                exit();
            default:
                // Parent process
        }
    }

    private function startEngineSimulation() {
        $PDO = $this->connector->getPDO();
        $management = new MySQLCommonPluginManagement($PDO);

        $freq = 1 / ($this->frequency ?: $this->mainEngine->getFrequency());

        while (1) {
            $this->update($management);
            usleep( $freq * 1e6 );
        }
    }

    public function tearDown()
    {
        if($this->processID) {
            // Parent process must kill running child plugin
            posix_kill( $this->processID, SIGTERM );
        }
    }
}