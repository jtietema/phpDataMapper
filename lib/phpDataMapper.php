<?PHP
class phpDataMapper {
  private static $_queryLog = array();
  
  
  /**
	 * Attempt to load class file based on phpDataMapper naming conventions.
	 */
	public static function loadClass($className)
	{
		$loaded = false;
		
		// If class has already been defined, skip loading
		if(class_exists($className, false)) {
			$loaded = true;
		} else {
			// Require phpDataMapper_* files by assumed folder structure (naming convention)
			if(strpos($className, "phpDataMapper") !== false) {
				$classFile = str_replace("_", "/", $className);
				$loaded = require_once(dirname(__FILE__) . "/" . $classFile . ".php");
			}
		}
		
		// Ensure required class was loaded
		/*
		if(!$loaded) {
			throw new Exception(__METHOD__ . " Failed: Unable to load class '" . $className . "'!");
		}
		*/
		
		return $loaded;
	}
	
	
	/**
	 * Prints all executed SQL queries - useful for debugging.
	 */
	public static function debug()
	{
		echo "<p>Executed " . $this->queryCount() . " queries:</p>";
		echo "<pre>\n";
		print_r(self::$_queryLog);
		echo "</pre>\n";
	}
	
	
	/**
	 * Get count of all queries that have been executed.
	 * 
	 * @return int
	 */
	public static function queryCount()
	{
		return count(self::$_queryLog);
	}
	
	
	/**
	 * Log a query.
	 *
	 * @param string $sql The SQL query that was executed.
	 * @param array $data An optional list of data that was set during the query.
	 */
	public static function logQuery($sql, $data = null)
	{
		self::$_queryLog[] = array(
			'query' => $sql,
			'data' => $data
		);
	}
}

/**
 * Register static 'loadClass' function as an autoloader for files prefixed with 'phpDataMapper_'
 */
spl_autoload_register(array('phpDataMapper', 'loadClass'));
