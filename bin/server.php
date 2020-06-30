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


$max_clients = 10;
$client = [];

switch ($argv[1]) {
	case 'unix':
		$socket = @socket_create(AF_UNIX, SOCK_STREAM, 0);
		if(!$socket) {
			echo "Failed to create socket\n";
			exit(-1);
		}
		if(!@socket_bind($socket, $argv[2])) {
			echo "Failed to bind socket.\n";
			exit(-2);
		}
		$socketHandler = function() use ($argv) {
			if(file_exists($argv[2]))
				unlink( $argv[2] );
			exit();
		};
		break;
	case 'inet':
		$socket = @socket_create(AF_INET, SOCK_STREAM, 0);
		if(!$socket) {
			echo "Failed to create socket\n";
			exit(-1);
		}
		if(!@socket_bind($socket, $argv[2], $argv[3])) {
			echo "Failed to bind socket.\n";
			exit(-2);
		}
		$socketHandler = function() {
			exit();
		};
		break;
	default:
		throw new RuntimeException("No server type specified.");
}


socket_listen($socket, $max_clients);

if(function_exists('pcntl_signal')) {
	$handler = function() use (&$client, $socketHandler) {
		foreach($client as $c) {
			if(isset($c["sock"]))
				socket_close($c["sock"]);
		}
		$socketHandler();
		echo "All closed. Bye\n";
		exit(0);
	};
	pcntl_signal(SIGINT, $handler, false);
	pcntl_signal(SIGTERM, $handler, false);
}


$STORAGE = [];
$CONTROL = [];
$COMMAND = [];
$ALERT = [];

while (1) {
	$read = [$socket];

	for ($i = 0; $i < $max_clients; $i++)
	{
		if (isset($client[$i]))
			if ($client[$i]['sock']  != null)
				$read[$i + 1] = $client[$i]['sock'] ;
	}
	$write = NULL;
	$except = NULL;

	declare(ticks=1) {
		$ready = @socket_select($read, $write, $except, $tv_sec = NULL);
	}

	if (in_array($socket, $read)) {
		for ($i = 0; $i < $max_clients; $i++) {
			if (!isset($client[$i])) {
				$client[$i] = [];
				$client[$i]['sock'] = socket_accept($socket);
				socket_getpeername($client[$i]['sock'], $name, $port);
				$client[$i]['name'] = $name;
				$client[$i]['port'] = $port;
				echo("Accepting connection from $name:$port...\n");
				break;
			} elseif ($i == $max_clients - 1)
				print ("too many clients");
		}
		continue;
	}

	for ($i = 0; $i < $max_clients; $i++) // for each client
	{
		if (isset($client[$i])) {
			if (in_array($client[$i]['sock'], $read)) {
				$input = socket_read($client[$i]['sock'], 1024);
				if ($input == NULL) {
					continue;
				}
				$n = trim($input);
				if ($n == 'exit') {
					echo("Client requested disconnect\n");
					socket_close($client[$i]['sock']);
					unset($client[$i]);
					continue;
				}
				$output = false;

				if(preg_match("/^(\w+)\s+/i", $n, $ms)) {
					$cmd = $ms[1];
					$args = unserialize( substr($n, strlen($ms[0])) );
					if(function_exists($cmd))
						$output = call_user_func_array($cmd, $args);
					else
						echo "Invalid CMD: $cmd\n";
				}

				$output = serialize($output);
				socket_write($client[$i]['sock'], $output, strlen($output)+1);
			}
		}
	}
}

function putv($value, $key, $domain) {
	global $STORAGE;
	$STORAGE[$domain][$key] = $value;
	return true;
}

function hasv($domain, $key) {
	global $STORAGE;
	return array_key_exists($domain, $STORAGE) && array_key_exists($key, $STORAGE[$domain]);
}

function getv($domain, $key) {
	global $STORAGE;
	if(NULL == $key)
		return $STORAGE[$domain];
	else
		return $STORAGE[$domain][$key];
}

function stop($code, $reason) {
	global $CONTROL;
	$CONTROL["stopped"] = [
		$code,
		$reason
	];
	return true;
}

function stopped() {
	global $CONTROL;
	return $CONTROL["stopped"] ?: false;
}

function putc($cmd, $info) {
	global $COMMAND;
	$COMMAND[$cmd] = $info;
	return true;
}

function hasc($cmd) {
	global $COMMAND;
	return array_key_exists($cmd, $COMMAND);
}

function getc($cmd) {
	global $COMMAND;
	return $COMMAND[$cmd] ?? false;
}

function clearc($cmd) {
	global $COMMAND;
	if(hasc($cmd))
		unset($COMMAND[$cmd]);
	return true;
}


function alrt($pid, $aid, $code, $msg, $ts, $plugin) {
	global $ALERT;
	$ALERT["#"][$aid][] = "$pid::$aid";
	$ALERT["$pid::$aid"] = [$code, $msg, $ts, $plugin];
	return true;
}

function alrtq($aid) {
	global $ALERT;
	if($alerts = $ALERT["#"][$aid] ?? NULL) {
		foreach($alerts as $a) {
			unset($ALERT[$a]);
		}
		unset($ALERT["#"][$aid]);
	}
	return true;
}

function ialrtq($pid, $aid) {
	global $ALERT;
	return isset($ALERT["$pid::$pid::$aid"]) ? false : true;
}

socket_close($socket);