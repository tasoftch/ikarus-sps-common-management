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

namespace Ikarus\SPS\Common\Plugin;


use Ikarus\SPS\Common\AbstractSocketCommonPluginManagement;
use Ikarus\SPS\Plugin\Cyclic\AbstractCyclicPlugin;
use Ikarus\SPS\Plugin\Management\CyclicPluginManagementInterface;
use Ikarus\SPS\Plugin\SetupPluginInterface;
use Ikarus\SPS\Plugin\TearDownPluginInterface;
use TASoft\Util\BackgroundProcess;

/**
 * The common management plugin is responsible to launch and control the common server.
 *
 * @package Ikarus\SPS\Common\Plugin
 */
abstract class AbstractCommonManagementServerPlugin extends AbstractCyclicPlugin implements SetupPluginInterface, TearDownPluginInterface
{
	/** @var BackgroundProcess */
	private $process;


	/**
	 * @inheritDoc
	 */
	public function update(CyclicPluginManagementInterface $pluginManagement)
	{
		if($pluginManagement instanceof AbstractSocketCommonPluginManagement) {
			if($pluginManagement->isEngineStopped($code, $reason)) {
				$pluginManagement->stopEngine($code, $reason);
				return;
			}
		}
	}

	abstract protected function connectionType(): string;
	abstract protected function connectionAddress(): string;
	abstract protected function connectionPort(): int;

	public function setup()
	{
		$bin = escapeshellarg(dirname( dirname( dirname(__FILE__))) . "/bin/server.php");

		$cmd = "php $bin ";
		$type = $this->connectionType();
		$cmd .= "$type ";
		switch ($type) {
			case 'unix':
				$cmd .= escapeshellarg($this->connectionAddress());
				break;
			case 'inet':
				$cmd .= escapeshellarg($this->connectionAddress()) . " ";
				$cmd .= escapeshellarg($this->connectionPort());
				break;
		}


		$this->process = new BackgroundProcess(sprintf($cmd));
		$this->process->run();

		if($type == 'unix') {
			for($e=0;$e<1000;$e++) {
				if(file_exists($this->connectionAddress()))
					break;
				usleep(100);
			}
		}
	}

	public function tearDown()
	{
		$this->process->kill(SIGTERM);
	}
}