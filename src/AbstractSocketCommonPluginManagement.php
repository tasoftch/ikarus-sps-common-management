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


use Ikarus\SPS\Alert\AlertInterface;
use Ikarus\SPS\Alert\AlertRecoveryInterface;
use Ikarus\SPS\Helper\CyclicPluginManager;
use Ikarus\SPS\Plugin\PluginInterface;

abstract class AbstractSocketCommonPluginManagement extends CyclicPluginManager
{
	private $identifier;
	/** @var resource */
	protected $socket;
	protected $alerts = [];

	private $pendentAlertCount = 0;

	public function __construct($identifier)
	{
		$this->identifier = $identifier;
	}

	protected function sendCommand($command)
	{
		socket_write($this->socket, $command);
		return @unserialize( socket_read($this->socket, 8192) );
	}

	abstract protected function connectSocket();
	abstract protected function disconnectSocket();

	protected function _checkSocket() {
		if(!$this->socket)
			$this->connectSocket();
	}

	public function stopEngine($code = 0, $reason = ""): bool
	{
		$this->_checkSocket();
		return $this->sendCommand("stop " . serialize([$code, $reason])) ? true : false;
	}

	public function isEngineStopped(&$code, &$reason): bool {
		$this->_checkSocket();
		$r = $this->sendCommand("stopped ".serialize([]));
		if(is_array($r)) {
			list($code, $reason) = $r;
			return true;
		}
		return false;
	}

	public function putCommand(string $command, $info = false)
	{
		$this->_checkSocket();
		return $this->sendCommand("putc " . serialize([$command, $info])) ? true : false;
	}

	public function hasCommand(string $command = NULL): bool
	{
		$this->_checkSocket();
		return $this->sendCommand("hasc " . serialize([$command])) ? true : false;
	}

	public function getCommand(string $command)
	{
		$this->_checkSocket();
		return unserialize( $this->sendCommand("getc " . serialize([$command])) );
	}

	public function clearCommand(string $command = NULL)
	{
		$this->_checkSocket();
		return $this->sendCommand("clearc " . serialize([$command])) ? true : false;
	}

	public function putValue($value, $key, $domain)
	{
		$this->_checkSocket();
		return $this->sendCommand("putv " . serialize([$value, $key, $domain])) ? true : false;
	}

	public function hasValue($domain, $key = NULL): bool
	{
		$this->_checkSocket();
		return $this->sendCommand("hasv " . serialize([$domain, $key])) ? true : false;
	}

	public function fetchValue($domain, $key = NULL)
	{
		$this->_checkSocket();
		return $this->sendCommand("getv " . serialize([$domain, $key]));
	}

	public function triggerAlert(AlertInterface $alert)
	{
		$this->_checkSocket();
		$this->alerts[ $alert->getID() ] = $alert;
		$pl = $alert->getAffectedPlugin();

		$info = [
			$this->identifier,
			$alert->getID(),
			$alert->getCode(),
			$alert->getMessage(),
			$alert->getTimeStamp(),
			$pl instanceof PluginInterface ? $pl->getIdentifier() : ""
		];

		return $this->sendCommand("alrt " . serialize($info)) ? true : false;
	}

	public function recoverAlert($alert): bool
	{
		$this->_checkSocket();
		if(is_string($alert)) {
			$this->sendCommand("alrtq ".serialize([$alert]));
			return parent::recoverAlert($alert);
		} elseif($alert instanceof AlertInterface)
			return parent::recoverAlert($alert);

		return false;
	}

	/**
	 * @
	 */
	public function beginCycle() {
		if($this->alerts) {
			if($recovered = $this->sendCommand("ialrtq " . serialize([]))) {
				/** @var AlertInterface $alert */
				$alerts = $this->alerts;
				foreach($alerts as $alert) {
					if(!in_array("$this->identifier::".$alert->getID(), $recovered)) {
						if($alert instanceof AlertRecoveryInterface)
							$alert->resume();
						unset($this->alerts[$alert->getID()]);
					}
				}
				unset($recovered["#"]);
				$this->pendentAlertCount = count($recovered);
			}
		}
		$this->pendentAlertCount = 0;
	}

	/**
	 * Gets called by ikarus/sps version 1.1 and above
	 */
	public function tearDown() {
		if($this->socket)
			$this->disconnectSocket();
	}

	/**
	 * @return array
	 */
	public function getAlerts(): array
	{
		$this->_checkSocket();
		$alrts = [];
		if($alerts = $this->sendCommand("alrtget " . serialize([]))) {
			foreach($alerts as $key => $value) {
				if($key == '#')
					continue;

				list($code, $msg, $ts, $plugin) = $value;
				list($project, $key) = explode("::", $key, 2);

				$alrts[] = [
					'uid' => $key,
					'code' => $code,
					'message' => $msg,
					'brick' => $plugin,
					'date' => date("d.m.Y", $ts),
					"time" => date("G:i", $ts),
					"project" => $project
				];
			}
		}
		return $alrts;
	}

	/**
	 * @return int
	 */
	public function getPendentAlertCount(): int
	{
		return $this->pendentAlertCount;
	}
}