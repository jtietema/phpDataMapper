<?PHP
/**
 * Base PDO adapter
 *
 * @package phpDataMapper
 * @link http://phpdatamapper.com
 * @link http://github.com/vlucas/phpDataMapper
 */
abstract class phpDataMapper_Adapter_PDO implements phpDataMapper_Adapter_Interface
{
	// Format for date columns, formatted for PHP's date() function
	protected $format_date;
	protected $format_time;
	protected $format_datetime;
	
	
	// Connection details
	protected $connection;
	protected $host;
	protected $database;
	protected $username;
	protected $password;
	protected $options;
	
	
  /**
  * @param mixed $host Host string or pre-existing PDO object
  * @param string $database Optional if $host is PDO object
  * @param string $username Optional if $host is PDO object
  * @param string $password Optional if $host is PDO object
  * @param array $options
  * @return void
  */
  public function __construct($host, $database = null, $username = null, $password = null, array $options = array())
  {
  	if($host instanceof PDO) {
  		$this->connection = $host;
  	} else {
		$this->host = $host;
		$this->database = $database;
		$this->username = $username;
		$this->password = $password;
		$this->options = $options;
		
		// Establish connection
		try {
			$this->connection = new PDO($this->dsn(), $this->username, $this->password, $this->options);
			// Throw exceptions by default
			$this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		/*
		} catch(PDOException $e) {
			if($e->getCode() == 1049) {
				// Database not found, try connection with no db specified
				$this->connection = new PDO($this->getDsn(), $this->username, $this->password, $this->options);
			} else {
				throw new phpDataMapper_Exception($e->getMessage());
			}
		*/
		} catch(Exception $e) {
			throw new phpDataMapper_Exception($e->getMessage());
		}
  	}
  }
	
	
	/**
	 * Get database connection
	 * 
	 * @return object PDO
	 */
	public function connection()
	{
		return $this->connection;
	}
	
	
	/**
	 * Get DSN string for PDO to connect with
	 * 
	 * @return string
	 */
	abstract public function dsn();
	
	
	/**
	 * Get database format
	 *
	 * @return string Date format for PHP's date() function
	 */
	public function dateFormat()
	{
		return $this->format_date;
	}
	
	
	/**
	 * Get database time format
	 *
	 * @return string Time format for PHP's date() function
	 */
	public function timeFormat()
	{
		return $this->format_time;
	}
	
	
	/**
	 * Get database format
	 *
	 * @return string DateTime format for PHP's date() function
	 */
	public function dateTimeFormat()
	{
		return $this->format_datetime;
	}
	
	
	/**
	 * Escape/quote direct user input
	 *
	 * @param string $string
	 */
	public function escape($string)
	{
		return $this->connection()->quote($string);
	}
	
	
	/**
	 * Migrate table structure changes to database. Creates the table if it doesn't already exist,
	 * updates the schema otherwise.
	 * 
	 * @param string $table The name of the table to migrate.
	 * @param array $properties List of {@link phpDataMapper_Property} objects representing the model definition.
	 */
	public function migrate($table, array $properties)
	{
		if($this->tableExists($table)) {
			// Update table
			$this->migrateTableUpdate($table, $properties);
		} else {
			// Create table
			$this->migrateTableCreate($table, $properties);
		}
	}
	
	
	abstract protected function tableExists($table);
	
	
	/**
	 * Creates the table specified.
	 *
	 * @param string $table The name of the table to create.
	 * @param array $properties List of {@link phpDataMapper_Property} objects representing the model definition.
	 * @return bool
	 */
	protected function migrateTableCreate($table, array $properties)
	{
		// Get syntax for table with fields/columns
		$sql = $this->migrateSyntaxTableCreate($table, $properties);
		
		// Add query to log
		phpDataMapper::logQuery($sql);
		
		$this->connection()->exec($sql);
		
		return true;
	}
	
	
	/**
	 * Creates an SQL statement to create the table specified, based on the property objects
	 * supplied.
	 *
	 * @param string $table The name of the table to create.
	 * @param array $properties List of {@link phpDataMapper_Property} objects representing the model definition.
	 * @return string SQL statement.
	 */
	abstract protected function migrateSyntaxTableCreate($table, array $properties);
	
	
	/**
	 * Updates a table schema based on a model definition (i.e. the list of a model's properties).
	 *
	 * @param string $table The name of the table of which to update the schema.
	 * @param array $properties List of {@link phpDataMapper_Property} objects representing the model definition.
	 * @return bool True if any updates were performed, false otherwise.
	 */
	protected function migrateTableUpdate($table, array $properties)
	{		
		$sql = $this->migrateSyntaxTableUpdate($table, $properties);
		
		if (!$sql) {
		  return false;
		}
		
		// Add query to log
		phpDataMapper::logQuery($sql);
		
		// Run SQL
		$this->connection()->exec($sql);
		
		return true;
	}
	
	
	/**
	 * Possibly creates an SQL statement to alter the table specified. Introspects the current
	 * table definition to determine if any changes are needed, and only performs the necessary
	 * changes.
	 *
	 * @param string $table The name of the table to alter.
	 * @param array $properties List of {@link phpDataMapper_Property} instances representing the model definition.
	 * @return mixed SQL statement, or false if there is nothing to update.
	 */
	abstract protected function migrateSyntaxTableUpdate($table, array $properties);
	
	
	/**
	 * Prepare an SQL statement 
	 */
	public function prepare($sql)
	{
		return $this->connection()->prepare($sql);
	}
	
	/**
	 * Create new row object with set properties
	 */
	public function create($source, array $data)
	{
		$binds = $this->statementBinds($data);
		// build the statement
		$sql = "INSERT INTO " . $source .
			" (" . implode(', ', array_keys($data)) . ")" .
			" VALUES(:" . implode(', :', array_keys($binds)) . ")";
		
		// Add query to log
		phpDataMapper::logQuery($sql, $binds);
		
		// Prepare update query
		$stmt = $this->connection()->prepare($sql);
		
		if($stmt) {
			// Execute
			if($stmt->execute($binds)) {
				$result = $this->connection()->lastInsertId();
			} else {
				$result = false;
			}
		} else {
			$result = false;
		}
		
		return $result;
	}
	
	
	/**
	 * Build a select statement in SQL
	 * Can be overridden by adapters for custom syntax
	 *
	 * @todo Add support for JOINs
	 */
	public function read(phpDataMapper_Query $query)
	{
		$conditions = $this->statementConditions($query->conditions);
		$binds = $this->statementBinds($query->params());
		$order = array();
		if($query->order) {
			foreach($query->order as $oField => $oSort) {
				$order[] = $oField . " " . $oSort;
			}
		}
		
		$sql = "
			SELECT " . $this->statementFields($query->fields) . "
			FROM " . $query->source . "
			" . ($conditions ? 'WHERE ' . $conditions : '') . "
			" . ($query->group ? 'GROUP BY ' . implode(', ', $query->group) : '') . "
			" . ($order ? 'ORDER BY ' . implode(', ', $order) : '') . "
			" . ($query->limit ? 'LIMIT ' . $query->limit : '') . " " . ($query->limit && $query->limitOffset ? 'OFFSET '
			  . $query->limitOffset: '') . "
			";
		
		// Unset any NULL values in binds (compared as "IS NULL" and "IS NOT NULL" in SQL instead)
		if($binds && count($binds) > 0) {
			foreach($binds as $field => $value) {
				if(null === $value) {
					unset($binds[$field]);
				}
			}
		}
		
		// Add query to log
		phpDataMapper::logQuery($sql, $binds);
		
		// Prepare update query
		$stmt = $this->connection()->prepare($sql);
		
		if($stmt) {
			// Execute
			if($stmt->execute($binds)) {
				$result = $this->toCollection($query, $stmt);
			} else {
				$result = false;
			}
		} else {
			$result = false;
		}
		
		return $result;
	}
	
	/**
	 * Update entity
	 */
	public function update($source, array $data, array $where = array())
	{
		// Get "col = :col" pairs for the update query
		$placeholders = array();
		$binds = array();
		foreach($data as $field => $value) {
			$placeholders[] = $field . " = :" . $field . "";
			$binds[$field] = $value;
		}
		
		// Where clauses
		$sqlWheres = array();
		if(count($where) > 0) {
			foreach($where as $wField => $wValue) {
				$sqlWheres[] = $wField . " = :w_" . $wField;
				$binds['w_' . $wField] = $wValue;
			}
		}
		
		// Ensure there are actually updated values on THIS table
		if(count($binds) > 0) {
			// Build the query
			$sql = "UPDATE " . $source .
				" SET " . implode(', ', $placeholders) .
				" WHERE " . implode(' AND ', $sqlWheres);
			
			// Add query to log
			phpDataMapper::logQuery($sql, $binds);
			
			// Prepare update query
			$stmt = $this->connection()->prepare($sql);
			
			if($stmt) {
				// Execute
				if($stmt->execute($binds)) {
					$result = true;
				} else {
					$result = false;
				}
			} else {
				$result = false;
			}
		} else {
			$result = false;
		}
		
		return $result;
	}
	
	
	/**
	 * Delete entities matching given conditions
	 *
	 * @param string $source Name of data source
	 * @param array $conditions Array of conditions in column => value pairs
	 */
	public function delete($source, array $data)
	{
		$binds = $this->statementBinds($data);
		$conditions = $this->statementConditions($data);
		
		$sql = "DELETE FROM " . $source . "";
		$sql .= ($conditions ? ' WHERE ' . $conditions : '');
		
		// Add query to log
		phpDataMapper::logQuery($sql, $binds);
		
		$stmt = $this->connection()->prepare($sql);
		if($stmt) {
			// Execute
			if($stmt->execute($binds)) {
				$result = true;
			} else {
				$result = false;
			}
		} else {
			$result = false;
		}
		return $result;
	}
	
	
	/**
	 * @see phpDataMapper_Adapter_Interface::truncateDataSource()
	 */
	public function truncateDataSource($dataSource)
	{
		$sql = "TRUNCATE TABLE `{$dataSource}`";
		
		// Add query to log
		phpDataMapper::logQuery($sql);
		
		return $this->connection()->exec($sql);
	}
	
	
	/**
	 * @see phpDataMapper_Adapter_Interface::dropDataSource()
	 */
	public function dropDataSource($dataSource)
	{
		$sql = "DROP TABLE `{$dataSource}`";
		
		// Add query to log
		phpDataMapper::logQuery($sql);
		
		return $this->connection()->exec($sql);
	}
	
	
	/**
	 * @see phpDataMapper_Adapter_Interface::createDatabase()
	 */
	public function createDatabase($database) {
		$sql = "CREATE DATABASE " . $database;
		
		// Add query to log
		phpDataMapper::logQuery($sql);
		
		return $this->connection()->exec($sql);
	}
	
	
  /**
   * @see phpDataMapper_Adapter_Interface::dropDatabase()
   */
	public function dropDatabase($database) {
		$sql = "DROP DATABASE " . $database;
		
		// Add query to log
		phpDataMapper::logQuery($sql);
		
		return $this->connection()->exec($sql);
	}
	
	
	/**
	 * Quotes an SQL identifier (such as a field name) in backticks so we don't get errors
	 * when we use reserved words as identifiers. Useful when quoting an array of identifiers.
	 *
	 * @param string $name 
	 * @return string
	 */
	public function quoteName($name)
	{
	  return "`$name`";
	}
	
	
	/**
	 * Converts a list of column names to a string for insertion into an SQL statement. Useful
	 * to create SELECT clauses.
	 * 
	 * @param array $fields
	 * @return string
	 */
	public function statementFields(array $fields = array())
	{
		return count($fields) > 0 ? implode(', ', $fields) : "*";
	}
	
	
	/**
	 * Builds an SQL string given conditions
	 */
	public function statementConditions(array $conditions = array())
	{
		if(count($conditions) == 0) { return; }
		
		$sqlStatement = "";
		$defaultColOperators = array(0 => '', 1 => '=');
		$ci = 0;
		$loopOnce = false;
		foreach($conditions as $condition) {
			if(is_array($condition) && isset($condition['conditions'])) {
				$subConditions = $condition['conditions'];
			} else {
				$subConditions = $conditions;
				$loopOnce = true;
			}
			$sqlWhere = array();
			foreach($subConditions as $column => $value) {
				// Column name with comparison operator
				$colData = explode(' ', $column);
				if ( count( $colData ) > 2 ) {
					$operator = array_pop( $colData );
					$colData = array( join(' ', $colData), $operator );
				}
				$col = $colData[0];
				
				// Array of values, assume IN clause
				if(is_array($value)) {
					$sqlWhere[] = $col . " IN('" . implode("', '", $value) . "')";
				
				// NULL value
				} elseif(is_null($value)) {
					$sqlWhere[] = $col . " IS NULL";
				
				// Standard string value
				} else {
					$colComparison = isset($colData[1]) ? $colData[1] : '=';
					$columnSql = $col . ' ' . $colComparison;
					
					// Add to binds array and add to WHERE clause
					$colParam = preg_replace('/\W+/', '_', $col) . $ci;
					$sqlWhere[] = $columnSql . " :" . $colParam . "";
				}
				
				// Increment ensures column name distinction
				$ci++;
			}
			if ( $sqlStatement != "" ) {
				$sqlStatement .= " " . (isset($condition['setType']) ? $condition['setType'] : 'AND') . " ";
			}
			//var_dump($condition);
			$sqlStatement .= join(" " . (isset($condition['type']) ? $condition['type'] : 'AND') . " ", $sqlWhere );
			
			if($loopOnce) { break; }
		}
		
		return $sqlStatement;
	}
	
	
	/**
	 * Returns array of binds to pass to query function
	 */
	public function statementBinds(array $conditions = array())
	{
		if(count($conditions) == 0) { return; }
		
		$binds = array();
		$ci = 0;
		$loopOnce = false;
		foreach($conditions as $condition) {
			if(is_array($condition) && isset($condition['conditions'])) {
				$subConditions = $condition['conditions'];
			} else {
				$subConditions = $conditions;
				$loopOnce = true;
			}
			foreach($subConditions as $column => $value) {
				// Can't bind array of values
				if(!is_array($value) && !is_object($value)) {
					// Column name with comparison operator
					$colData = explode(' ', $column);
					if ( count( $colData ) > 2 ) {
						$operator = array_pop( $colData );
						$colData = array( join(' ', $colData), $operator );
					}
					$col = $colData[0];
					$colParam = preg_replace('/\W+/', '_', $col) . $ci;
					
					// Add to binds array and add to WHERE clause
					$binds[$colParam] = $value;
				}
				
				// Increment ensures column name distinction
				$ci++;
			}
			if($loopOnce) { break; }
		}
		return $binds;
	}
	
	
	/**
	 * Return result set for current query
	 */
	public function toCollection(phpDataMapper_Query $query, $stmt)
	{
		$mapper = $query->mapper();
		if($stmt instanceof PDOStatement) {
			$results = array();
			
			// Set object to fetch results into
			$stmt->setFetchMode(PDO::FETCH_CLASS, $mapper->entityClass());
			
			// Fetch all results into new DataMapper_Result class
			while($row = $stmt->fetch(PDO::FETCH_CLASS)) {
			  $row->setIsNew(false);
				
				// Load relations for this row
				$relations = $mapper->getRelationsFor($row);
				if($relations && is_array($relations) && count($relations) > 0) {
					foreach($relations as $relationCol => $relationObj) {
						$row->$relationCol = $relationObj;
					}
				}
				
				// Store in array for ResultSet
				$results[] = $row;
				
				// Mark row as loaded
				$row->loaded(true);
			}
			// Ensure set is closed
			$stmt->closeCursor();
			
			$collectionClass = $mapper->collectionClass();
			return new $collectionClass($results);
			
		} else {
			$mapper->addError(__METHOD__ . " - Unable to execute query " . implode(' | ', $this->adapterRead()->errorInfo()));
			return array();
		}
	}
	
	
	/**
	 * Bind array of field/value data to given statement
	 *
	 * @param PDOStatement $stmt
	 * @param array $binds
	 */
	protected function bindValues($stmt, array $binds)
	{
		// Bind each value to the given prepared statement
		foreach($binds as $field => $value) {
			$stmt->bindValue($field, $value);
		}
		return true;
	}
}