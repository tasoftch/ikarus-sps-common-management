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


use Ikarus\SPS\Common\Exception\SocketConnectionException;

class UnixCommonPluginManagement extends AbstractSocketCommonPluginManagement
{
	/** @var string */
	private $unixName;

	public function __construct($identifier, string $unixName)
	{
		parent::__construct($identifier);
		$this->unixName = $unixName;
	}

	/**
	 * @return string
	 */
	public function getUnixName(): string
	{
		return $this->unixName;
	}

	/**
	 * @param string $unixName
	 * @return static
	 */
	public function setUnixName(string $unixName)
	{
		$this->unixName = $unixName;
		return $this;
	}


	protected function connectSocket()
	{
		$this->socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
		if(!$this->socket)
			throw (new SocketConnectionException("Can not create socket of type AF_INET", 889));
		if(!@socket_connect($this->socket, $this->getUnixName())) {
			throw (new SocketConnectionException("Can not connect to %s", 890, NULL, $this->getUnixName()))->setSocket($this->socket);
		}
	}

	protected function disconnectSocket()
	{
		@socket_write($this->socket, 'exit', 4);
		@socket_close($this->socket);
		unlink($this->getUnixName());
	}
}