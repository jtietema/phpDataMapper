<?PHP
class phpDataMapper {
  private static $_queryLog = array();
  
  
  /**
   * Attempt to load a class based on the phpDataMapper naming conventions. Since
   * this uses require to load the class, it is possible that this method will never
   * return because an error is raised by PHP.
   *
   * @param string $className The name of the class to load.
   * @return bool True upon successful load, false otherwise.
   */
	public static function loadClass($className)
	{
		// If class has already been defined, skip loading.
		if (class_exists($className, false)) {
			return true;
		}
		
		// Require phpDataMapper_* files by assumed folder structure (naming convention).
		if (self::startsWith($className, 'phpDataMapper_')) {
			$classFile = str_replace('_', '/', $className) . '.php';
			
			// Include relative from the current class.
			require dirname(__FILE__) . '/' . $classFile;
			
			return true;
		}
		
		return false;
	}
	
	
	private static function startsWith($string, $prefix)
	{
	  return substr($string, 0, strlen($prefix)) == $prefix;
	}
	
	
	public static function warn($message)
	{
	  trigger_error($message, E_USER_WARNING);
	}
	
	
	/**
	 * Prints all executed SQL queries - useful for debugging.
	 */
	public static function debug()
	{
		echo "<p>Executed " . self::queryCount() . " queries:</p>";
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
