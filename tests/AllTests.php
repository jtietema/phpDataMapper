<?PHP
require_once dirname(__FILE__) . '/init.php';

class AllTests
{
  public static function suite()
  {
    $suite = new PHPUnit_Framework_TestSuite('phpDataMapper');
    
    $suite->addTestSuite('Test_Conditions');
    $suite->addTestSuite('Test_CRUD');
    $suite->addTestSuite('Test_Relations');
    $suite->addTestSuite('Test_Migrate');
    
    return $suite;
  }
}
