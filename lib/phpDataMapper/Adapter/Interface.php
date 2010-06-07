<?php
/**
 * Adapter Interface
 * 
 * @package phpDataMapper
 * @link http://phpdatamapper.com
 */
interface phpDataMapper_Adapter_Interface
{
  /**
  * @param mixed $host Host string or pre-existing PDO object
  * @param string $database Optional if $host is PDO object
  * @param string $username Optional if $host is PDO object
  * @param string $password Optional if $host is PDO object
  * @param array $options
  * @return void
  */
  public function __construct($host, $database = null, $username = null, $password = null, array $options = array());
	
	
	/**
	 * Get database connection
	 */
	public function connection();
	
	
	/**
	 * Get database DATE format for PHP date() function
	 */
	public function dateFormat();
	
	
	/**
	 * Get database TIME format for PHP date() function
	 */
	public function timeFormat();
	
	
	/**
	 * Get database full DATETIME for PHP date() function
	 */
	public function dateTimeFormat();
	
	
	/**
	 * Escape/quote direct user input
	 *
	 * @param string $string
	 */
	public function escape($string);
	
	
	/**
	 * Insert entity
	 */
	public function create($source, array $data);
	
	
	/**
	 * Read from data source using given query object
	 */
	public function read(phpDataMapper_Query $query);
	
	
	/**
	 * Update entity
	 */
	public function update($source, array $data, array $where = array());
	
	
	/**
	 * Delete entity
	 */
	public function delete($source, array $where);
	
	
	/**
	 * This will delete all rows from the specified data source and reset the value of any
	 * AUTO_INCREMENT columns to 0.
	 *
	 * @param string $dataSource The name of the data source to drop.
	 * @return bool
	 */
	public function truncateDataSource($dataSource);
	
	
	/**
	 * Completely removes a data source from the database.
	 *
	 * @param string $dataSource The name of the data source to drop.
	 * @return bool
	 */
	public function dropDataSource($dataSource);
	
	
	/**
	 * @param string $database The name of the database to create.
	 * @return bool
	 */
	public function createDatabase($database);
	
	
	/**
   * @param string $database The name of the database to drop.
   * @return bool
   */
	public function dropDatabase($database);
}