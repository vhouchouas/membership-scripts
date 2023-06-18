<?php
/*
Copyright (C) 2020-2022  Zero Waste Paris

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <https://www.gnu.org/licenses/>.
*/

if(!defined('ZWP_TOOLS')){  die(); }
require_once(ZWP_TOOLS . 'lib/util.php');
require_once(ZWP_TOOLS . 'config.php');

class MysqlConnector {
  const OPTIONS_TABLE = "script_options";
  const OPTION_LASTSUCCESSFULRUN_KEY = "last_successful_run_date";
  private $debug;

  /**
   * @param $debug When set to TRUE, only read operations will be performed. The write ones
   *               will be ignored
   */
  public function __construct(bool $debug=true){
    global $loggerInstance;
    $this->debug = $debug;
    try {
      $cnxString = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME;
      $this->dbo = new PDO($cnxString, DB_USER, DB_PASSWORD);
      $this->dbo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

      /**
       * set wait_timeout in order to avoid having queries failing with
       *    PDOStatement::execute(): MySQL server has gone away
       * when the whole script is taking a long time to execute.
       * (it can be the case in particular when we delete old member: the whole script
       *  take a few minutes to run, and may otherwise fail when trying to update the ending date)
       * if we still encounter this error, we might try to reconnect instead (ie: build a new PDO object)
       */
      $this->dbo->query("SET wait_timeout=1200;");
    } catch(PDOException $e){
      $loggerInstance->log_error("Failed to connect to mysql: " . $e->getMessage());
      die();
    }
  }

  function __destruct(){
    $this->dbo = NULL;
  }

  public function readLastSuccessfulRunStartDate() : DateTime {
    global $loggerInstance;
    $data = $this->readOption(self::OPTION_LASTSUCCESSFULRUN_KEY);
    $date = unserialize($data);
    if ($date === FALSE){
      $loggerInstance->log_error("Failed to deserialize last successful run start date. data in db may be corrupted. Got: $data");
      die();
    }
    return $date;
  }

  private function readOption(string $key) : string {
    global $loggerInstance;
    try {
      $stmtOpt = $this->dbo->prepare("SELECT value FROM " . self::OPTIONS_TABLE . " WHERE `key`= :key");
      $stmtOpt->bindParam(':key', $key);
      $stmtOpt->execute();
      $row = $stmtOpt->fetch();
      if ( $row === FALSE ){
        $loggerInstance->log_error("Failed to load from sql option with key $key because it seems absent");
        die();
      }
      return $row["value"];
    } catch(PDOException $e){
      $loggerInstance->log_error("Failed load from sql option with key $key because of error $e->getMessage())");
    }
  }

  public function writeLastSuccessfulRunStartDate(DateTime $startDate) : void{
    $this->writeOption(self::OPTION_LASTSUCCESSFULRUN_KEY, serialize($startDate));
  }

  private function writeOption(string $key, string $value) : void {
    global $loggerInstance;
    try {
      $stmtOpt = $this->dbo->prepare("UPDATE " . self::OPTIONS_TABLE . " SET value=:value WHERE `key`=:key");
      $stmtOpt->bindParam(':value', $value);
      $stmtOpt->bindParam(':key', $key);
      $this->executeWriteOrDeleteStatement($stmtOpt, "write option $key");
    } catch(PDOException $e){
      $loggerInstance->log_error("Failed write to sql option with key $key and value $value because of error $e->getMessage())");
    }
  }

  private function executeWriteOrDeleteStatement($stmt, $description){
    global $loggerInstance;
    if($this->debug){
      $loggerInstance->log_info("debug mode: we don't $description");
    } else {
      $ret = $stmt->execute();
      if ($ret === FALSE) {
        $loggerInstance->log_error("Failed query to $description. Something unexpected went wrong");
        die();
      }
    }
  }
}
