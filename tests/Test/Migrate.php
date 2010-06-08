<?php
require_once dirname(__FILE__) . '/../init.php';

class Test_Migrate extends PHPUnit_Framework_TestCase
{
  protected $backupGlobals = false;
  protected $logEntryMapper;
  
  public function setUp()
  {
    $this->logEntryMapper = phpDataMapper_TestHelper::mapper('LogEntry');
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
