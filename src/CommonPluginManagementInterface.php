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

namespace Ikarus\SPS\Common;


use Ikarus\SPS\Plugin\Management\PluginManagementInterface;

interface CommonPluginManagementInterface extends PluginManagementInterface
{
    /**
     * Puts a command to the cycle stack
     *
     * @param string $command
     * @param null $info
     * @return void
     */
    public function putCommand(string $command, $info = NULL);

    /**
     * Gets the info of a command
     *
     * @param string $command
     * @return mixed
     */
    public function getCommand(string $command);

    /**
     * Removes a specific command or all commands from stack.
     *
     * @param string|NULL $command
     * @return void
     */
    public function clearCommand(string $command = NULL);

    /**
     * Puts a value for a specific key in a domain
     *
     * @param mixed $value
     * @param string $key
     * @param string $domain
     * @return void
     */
    public function putValue($value, $key, $domain);

    /**
     * Fetches a value from a domain, filtered by a key if specified.
     *
     * @param string|null $key
     * @param string $domain
     * @return mixed
     */
    public function fetchValue($domain, $key = NULL);
}