<?php
require_once dirname(__FILE__) . '/init.php';

class MigrateTest extends PHPUnit_Framework_TestCase
{
  protected $logEntryMapper;
  
  public function setUp()
  {
    $this->logEntryMapper = fixture_mapper('LogEntry');
  }
  
  public function tearDown()
  {
    $this->logEntryMapper->dropDatasource();
  }
  
  public function testCreatesMultiplePrimaryKeys()
  {
    $this->logEntryMapper->migrate();
  }
}
