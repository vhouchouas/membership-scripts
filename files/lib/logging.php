<?php
if(!defined('ZWP_TOOLS')){  die(); }

interface Logger {
  public function log_info(string $message): void;
  public function log_error(string $message): void;
}

class ProdLogger implements Logger {
  private static $log_file = ZWP_TOOLS . "../HelloAsso_To_Mailchimp_glue.log";

  public function log_info(string $message): void{
    $this->log_to_console_and_file("[INF]" . $this->get_log_prefix() . $message);
  }

  public function log_error(string $message): void{
    $full_message = $this->get_log_prefix() . $message;
    $this->log_to_console_and_file("[ERR]" . $full_message);
    error_log($full_message, 4 /*write in apache logs*/);
    error_log($full_message, 1 /*send an email*/, ADMIN_EMAIL_FOR_ERRORS);
  }

  private function log_to_console_and_file(string $full_message): void{
    echo $full_message.  "\n";
    error_log($full_message . "\n", 3 /*write to file*/, self::$log_file);
  }

  private function get_log_prefix(): string{
    return "[" . date("o-m-d\TG:i:s", time()) . "] ";
  }
}

class DummyLogger implements Logger {
  public function log_info(string $message): void {
    echo "[INFO] $message\n";
  }
  public function log_error(string $message): void{
    echo "[WARN] $message\n";
  }
}

// Initialize to a dummy logger so it works fine in test. Overridable in prod
$loggerInstance = new DummyLogger();
