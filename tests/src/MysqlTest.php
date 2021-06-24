<?php
declare(strict_types=1);
if (!defined('ZWP_TOOLS')){
  define('ZWP_TOOLS', __DIR__ . '/../../files/');
}
require_once(ZWP_TOOLS . 'lib/mysql.php');

use PHPUnit\Framework\TestCase;

final class MysqlTest extends TestCase {
  public function test_helloAssoStringDateToPhpDateWithSlashFormat(){
    $expected = new DateTime('1993-12-04 00:00:00');
    $this->assertExpectedDate($expected, '04/12/1993');
  }

  public function test_helloAssoStringDateToPhpDateWithrDashFormat(){
    $expected = new DateTime('1993-12-04 00:00:00');
    $this->assertExpectedDate($expected, '1993-12-04 00:00:00.0000000');
  }

  /**
   * The dates we have aren't precise to the hour so we allow up to 24h of difference (= 86400 seconds).
   * (Anyway the point of this test is to test that we can parse)
   */
  public function assertExpectedDate(DateTime $expected, string $input){
    $actual = MysqlConnector::helloAssoStringDateToPhpDate($input);
    $this->assertEqualsWithDelta($expected->getTimestamp(), $actual->getTimestamp(), 86400);
  }
}
