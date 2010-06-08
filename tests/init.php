<?PHP
require_once 'PHPUnit/Framework.php';
require dirname(__FILE__) . '/../lib/phpDataMapper.php';


class phpDataMapper_TestHelper
{
  private static $_adapter = NULL;
  
  
  private static $_mappers = array();
  
  
  public static function adapter()
  {
    if (self::$_adapter === NULL) {
      self::$_adapter = new phpDataMapper_Adapter_MySQL('localhost', 'phpdatamapper_test', 'root', '');
    }
    return self::$_adapter;
  }
  
  
  public static function mapper($name)
  {
    if (!isset(self::$_mappers[$name])) {
      $className = 'Fixture_' . $name . '_Mapper';
      self::$_mappers[$name] = new $className(self::adapter());
    }
    return self::$_mappers[$name];
  }
  
  
  public static function loadClass($className)
  {
    $classFile = str_replace('_', '/', $className) . '.php';
    require dirname(__FILE__) . '/' . $classFile;
  }
}

spl_autoload_register(array('phpDataMapper_TestHelper', 'loadClass'));


class TestMapper extends phpDataMapper_Mapper
{
	// Auto-migrate upon instantiation
	public function init()
	{
		$this->migrate();
	}
}
