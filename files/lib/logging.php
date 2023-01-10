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

require_once __DIR__ . '/../vendor/autoload.php';
use Monolog\Handler\SlackHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

interface ZWP_Logger {
  public function log_info(string $message): void;
  public function log_error(string $message): void;
}

class ProdLogger implements ZWP_Logger {
  private $logger;
  private $file_logger;
  private $slack_logger;
  private $debug;

  public function __construct(bool $debug, $bot_token, $channel_id){
    $this->debug = $debug;
    $this->logger = new Logger("log");
    $this->logger->pushHandler(new RotatingFileHandler(ZWP_TOOLS . "../HelloAsso_To_Mailchimp_glue.log", 30));

    if ( !$this->debug ){
      $this->logger->pushHandler(new SlackHandler($bot_token, $channel_id, null, true, null, LOGGER::ERROR));
    }

    $this->log_info("Using prodLogger. Debug flag is: " . self::boolToStr($this->debug));
  }

  private static function boolToStr(bool $bool) {
    return $bool ? "TRUE" : "FALSE";
  }

  public function log_info(string $message): void{
    echo $message . "\n";
    $this->logger->info($message);
  }

  public function log_error(string $message): void{
    echo $message . "\n";
    $this->logger->error($message);
  }
}

class DummyLogger implements ZWP_Logger {
  public function log_info(string $message): void {
    echo "[INFO] $message\n";
  }
  public function log_error(string $message): void{
    echo "[WARN] $message\n";
  }
}

// Initialize to a dummy logger so it works fine in test. Overridable in prod
$loggerInstance = new DummyLogger();
