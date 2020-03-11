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


use Ikarus\SPS\Alert\AbstractAlert;
use Ikarus\SPS\Alert\AlertInterface;
use Ikarus\SPS\Helper\CyclicPluginManager;
use Ikarus\SPS\Plugin\PluginInterface;
use TASoft\Util\PDO;

class MySQLCommonPluginManagement extends CyclicPluginManager implements CommonPluginManagementInterface
{
    /** @var PDO */
    private $PDO;
    /** @var AlertInterface[] */
    private $alerts = [];

    /**
     * MySQLCommonPluginManagement constructor.
     * @param PDO $PDO
     */
    public function __construct(PDO $PDO = NULL)
    {
        $this->PDO = $PDO;
    }

    /**
     * @param PDO $PDO
     */
    public function setPDO(PDO $PDO): void
    {
        $this->PDO = $PDO;
    }

    /**
     * @return PDO
     */
    public function getPDO(): PDO
    {
        return $this->PDO;
    }


    public function fetchValue($domain, $key = NULL)
    {
        if($key) {
            $raw = $this->getPDO()->selectOne("SELECT reg_data FROM VALUE_REGISTER WHERE reg_key = ? AND reg_domain = ?", [
                $key,
                $domain
            ]);
            if($raw)
                return unserialize( $raw["reg_data"] );
            return NULL;
        } else {
            $values = [];
            foreach($this->getPDO()->select("SELECT reg_data, reg_key FROM VALUE_REGISTER WHERE domain = ?", [
                $domain
            ]) as $record) {
                $values[ $record['reg_key'] ] = unserialize($record["reg_data"]);
            }
            return $values;
        }
    }

    public function triggerAlert(AlertInterface $alert)
    {
        parent::triggerAlert($alert);
        $this->getPDO()->transaction(function() use ($alert, &$aid) {
            $this->getPDO()->inject("INSERT INTO ALERT_REGISTER (date, code, message, affected_brick) VALUES (?, ?, ?, ?)")->send([
                date("Y-m-d G:i:s", $alert->getTimeStamp()),
                $alert->getCode(),
                $alert->getMessage(),
                $alert->getAffectedPlugin() instanceof PluginInterface ? $alert->getAffectedPlugin()->getIdentifier() : (string) $alert->getAffectedPlugin()
            ]);

            $aid = $this->getPDO()->lastInsertId("ALERT_REGISTER");
            if($alert instanceof AbstractAlert)
                $alert->setId( $aid );
        });
        return $aid;
    }

    public function putCommand(string $command, $info = false)
    {
        $this->getPDO()->transaction(function() use ($command, $info) {
            $this->getPDO()->inject("DELETE FROM COMMAND_REGISTER WHERE reg_command = ?")->send([
                $command
            ]);

            $this->getPDO()->inject("INSERT INTO COMMAND_REGISTER (reg_command, reg_info) VALUES (?, ?)")->send([
                $command,
                serialize($info)
            ]);
        });
    }

    public function clearCommand(string $command = NULL)
    {
        $this->getPDO()->transaction(function() use ($command) {
            $this->getPDO()->inject("DELETE FROM COMMAND_REGISTER WHERE reg_command = ?")->send([
                $command
            ]);
        });
    }

    public function recoverAlert($alert): bool
    {
        $ok = parent::recoverAlert($alert);
        if($alert = $this->alerts[ $alert instanceof AlertInterface ? $alert->getId() : $alert] ?? NULL) {
            $this->getPDO()->transaction(function() use ($alert) {
                $aid = $alert->getID();
                $this->getPDO()->inject("UPDATE ALERT_REGISTER SET recovered = 1 WHERE id = ?")->send([
                    $aid
                ]);
            });
            return true;
        }
        return $ok;
    }

    public function putValue($value, $key, $domain)
    {
        parent::putValue($value, $key, $domain);
        $this->getPDO()->transaction(function() use ($value, $domain, $key) {
            $this->getPDO()->inject("DELETE FROM VALUE_REGISTER WHERE reg_domain = ? AND reg_key = ?")->send([
                $domain,
                $key
            ]);

            $this->getPDO()->inject("INSERT INTO VALUE_REGISTER (reg_key, reg_domain, reg_data) VALUES (?,?,?)")->send([
                $key,
                $domain,
                serialize($value)
            ]);
        });
    }

    public function hasCommand(string $command = NULL): bool
    {
        return $this->getPDO()->selectFieldValue("SELECT count(*) AS C FROM COMMAND_REGISTER WHERE reg_command = ?", 'C', [$command]) > 0 ? true : false;
    }


    public function hasValue($domain, $key = NULL): bool
    {
        if($key)
            return $this->getPDO()->selectFieldValue("SELECT count(*) AS C FROM VALUE_REGISTER WHERE reg_domain = ? AND reg_key = ?", 'C', [$domain, $key]) > 0 ? true : false;
        else
            return $this->getPDO()->selectFieldValue("SELECT count(*) AS C FROM VALUE_REGISTER WHERE reg_domain = ?", 'C', [$domain]) > 0 ? true : false;
    }

    public function getCommand(string $command)
    {
        $cmd = $this->getPDO()->selectOne("SELECT reg_info FROM COMMAND_REGISTER WHERE reg_command = ?", [$command]);
        if($cmd)
            return unserialize( $cmd["reg_info"] );
        return NULL;
    }
}