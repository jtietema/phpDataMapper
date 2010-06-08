<?php
/**
 * $Id$
 *
 * @package phpDataMapper
 * @link http://phpdatamapper.com
 * @link http://github.com/vlucas/phpDataMapper
 */
class phpDataMapper_Adapter_MySQL extends phpDataMapper_Adapter_PDO
{
	// Format for date columns, formatted for PHP's date() function
	protected $format_date = "Y-m-d";
	protected $format_time = " H:i:s";
	protected $format_datetime = "Y-m-d H:i:s";
	
	// Driver-Specific settings
	protected $_engine = 'InnoDB';
	protected $_charset = 'utf8';
	protected $_collate = 'utf8_unicode_ci';
	
	
	/**
	 * Maps phpDataMapper property types to actual database column types.
	 *
	 * @var array
	 */
	protected $_propertyColumnTypeMap = array(
	  'boolean'   => 'BOOL',
	  'date'      => 'DATE',
	  'datetime'  => 'DATETIME',
	  'float'     => 'FLOAT',
	  'integer'   => 'INT',
	  'string'    => 'VARCHAR',
	  'text'      => 'TEXT'
	);
	
	
	/**
	 * Lists database defaults of lengths for certain column types. We can use
	 * this to see if a column definition needs to be updated during migration.
	 *
	 * @var array
	 */
	protected $_defaultLengths = array(
	  'BOOL'      => 1,
	  'INT'       => 11,
	  'VARCHAR'   => 255
	);
	
	
	/**
	 * List of fields that support the COLLATE operator.
	 *
	 * @var array
	 */
	protected $_collatedTypes = array('VARCHAR', 'TEXT');
	
	
	/**
	 * Get DSN string for PDO to connect with
	 * 
	 * @return string
	 */
	public function dsn()
	{
		$dsn = 'mysql:host=' . $this->host . ';dbname=' . $this->database . '';
		return $dsn;
	}
	
	
	/**
	 * Set database engine (InnoDB, MyISAM, etc)
	 */
	public function engine($engine = null)
	{
		if(null !== $engine) {
			$this->_engine = $engine;
		}
		return $this->_engine;
	}
	
	
	/**
	 * Set character set and MySQL collate string
	 */
	public function characterSet($charset, $collate = 'utf8_unicode_ci')
	{
		$this->_charset = $charset;
		$this->_collate = $collate;
	}
	
	
  /**
   * @see phpDataMapper_Adapter_PDO::tableExists()
   */
	protected function tableExists($table)
	{
	  $sql = "SHOW TABLES FROM `{$this->database}` LIKE '{$table}'";
	  $stmt = $this->connection()->query($sql);
	  
	  phpDataMapper::logQuery($sql);
	  
	  return count($stmt->fetchAll()) > 0;
	}
	
	
	/**
	 * Introspects the database to get current column information.
	 *
	 * @param string $table Table name
	 * @return array A hash, where the keys are column names and the values are hashes of MySQL column info.
	 */
	protected function columnInfoForTable($table)
	{
	  $sql = "SELECT * FROM information_schema.columns WHERE table_schema = '{$this->database}'"
	    . " AND table_name = '{$table}'";
	  
		$tableColumns = array();
		$tblCols = $this->connection()->query($sql);
		
		phpDataMapper::logQuery($sql);
		
		if($tblCols) {
			while($columnData = $tblCols->fetch(PDO::FETCH_ASSOC)) {
				$tableColumns[$columnData['COLUMN_NAME']] = $columnData;
			}
			return $tableColumns;
		} else {
			return false;
		}
	}
	
	
	/**
	 * Introspects the database to get current index information for the specified table.
	 *
	 * @param string $table The name of the table to inspect.
	 * @return array An array, where the values are hashes of MySQL index info.
	 */
	protected function indexInfoForTable($table)
	{
	  $sql = "SHOW INDEX FROM `{$table}`";
	  $stmt = $this->connection()->query($sql);
	  
	  phpDataMapper::logQuery($sql);
	  
	  return $stmt ? $stmt->fetchAll() : false;
	}
	
	
	/**
	 * @see phpDataMapper_Adapter_PDO::migrateSyntaxTableCreate()
	 */
	protected function migrateSyntaxTableCreate($table, array $properties)
	{
		$lines = array();
		$usedIndexNames = array();
		$primaryKey = array();
		foreach ($properties as $property) {
		  $lines[] = $this->migrateSyntaxColumnDefinition($property);
		  
		  $propertyName = $property->name();
		  
		  // Determine key field name (can't use same key name twice, so we have to append a number)
			$keyName = $propertyName;
			$keyIndex = 0;
			while (in_array($keyName, $usedIndexNames)) {
				$keyName = $propertyName . '_' . $keyIndex++;
			}
			
			// Key type
			if ($property->option('primary')) {
			  $primaryKey[] = $propertyName;
			}
			
			if ($property->option('unique')) {
				$lines[] = "UNIQUE KEY `{$keyName}` (`{$propertyName}`)";
				$usedIndexNames[] = $keyName;
			}
			
			if ($property->option('index')) {
				$lines[] = "KEY `{$keyName}` (`{$propertyName}`)";
				$usedIndexNames[] = $keyName;
			}
		}
		
		// Build primary key
		$lines[] = "PRIMARY KEY(" . implode(',', array_map(array($this, 'quoteName'), $primaryKey)) . ")";
		
		$sql = "CREATE TABLE IF NOT EXISTS `{$table}` (\n";
		$sql .= implode(",\n", $lines);
		$sql .= "\n) ENGINE={$this->_engine} DEFAULT CHARSET={$this->_charset} COLLATE={$this->_collate};";
		
		return $sql;
	}
	
	
	/**
	 * Creates the column definition syntax portion for exactly one property.
	 *
	 * @param phpDataMapper_Property $property
	 * @return string SQL column definition.
	 */
	protected function migrateSyntaxColumnDefinition(phpDataMapper_Property $property)
	{
		// Ensure field type is supported
		$propertyType = $property->type();
		if(!isset($this->_propertyColumnTypeMap[$propertyType])) {
			throw new phpDataMapper_Exception("Property type '$propertyType' not supported by this adapter.");
		}
		
		$columnType = $this->_propertyColumnTypeMap[$propertyType];
		
		// Base definition
		$sql = "`" . $property->name() . "` " . $columnType;
		
		// Length
		if ($property->hasOption('length')) {
		  $sql .= '(' . $property->option('length') . ')';
		}
		
		// Unsigned
		if ($property->hasOption('unsigned')) {
		  $sql .= ' UNSIGNED';
		}
		
		// Collation
		if (in_array($columnType, $this->_collatedTypes)) {
		  $sql .= " COLLATE {$this->_collate}";
		}
		
		// Nullable
		if ($property->option('required')) {
		  $sql .= ' NOT NULL';
		}
		
		// Default value
		if ($property->option('default') !== NULL) {
		  $sql .= ' DEFAULT ' . $this->convertPHPValue($property->option('default'));
		}
		
		// Auto increment
		if ($property->option('primary') && $property->option('serial', false)) {
		  $sql .= ' AUTO_INCREMENT';
		}
		
		return $sql;
	}
	
	
	/**
	 * @see phpDataMapper_Adapter_PDO::migrateSyntaxTableUpdate()
	 */
	protected function migrateSyntaxTableUpdate($table, array $properties)
	{		
		$lines = array_merge(
		  $this->collectColumnsSyntaxForTableUpdate($table, $properties),
		  $this->collectIndicesSyntaxForTableUpdate($table, $properties)
		);
		
    if (count($lines) == 0) {
      // Nothing to update
      return false;
    }
    
    return "ALTER TABLE `{$table}`\n" . implode(",\n", $lines) . ";";
	}
	
	
	/**
	 * Compares the supplied list of properties with the actual column definitions for
	 * the table. Returns a list of SQL statements (ADD/MODIFY/DROP COLUMN) that should
	 * be part of an ALTER TABLE statement.
	 *
	 * @param string $table The name of the table to inspect.
	 * @param array $properties List of {@link phpDataMapper_Property} instances representing the model definition.
	 * @return array
	 */
	protected function collectColumnsSyntaxForTableUpdate($table, array $properties)
	{
	  $columnInfo = $this->columnInfoForTable($table);
	  
	  $propertiesToAdd = array();
	  $propertiesToAlter = array();
	  $propertyNames = array();
	  
	  foreach ($properties as $property) {
	    $propertyName = $property->name();
	    if (!array_key_exists($propertyName, $columnInfo)) {
	      $propertiesToAdd[] = $property;
	    }
	    elseif ($this->shouldUpdateColumnDefinition($columnInfo[$propertyName], $property)) {	      
        $propertiesToAlter[] = $property;
	    }
	    
	    $propertyNames[] = $propertyName;
	  }
	  
	  $propertyNamesToDrop = array_diff(array_keys($columnInfo), $propertyNames);
		
		$lines = array();
		foreach ($propertyNamesToDrop as $propertyName) {
		  $lines[] = "DROP COLUMN `{$propertyName}`";
		}
		foreach ($propertiesToAlter as $property) {
		  $lines[] = "MODIFY COLUMN " . $this->migrateSyntaxColumnDefinition($property);
		}
		foreach ($propertiesToAdd as $property) {
		  $lines[] = "ADD COLUMN " . $this->migrateSyntaxColumnDefinition($property);
		}
		
		return $lines;
	}
	
	
	/**
	 * Collects SQL syntax lines to update the database schema to reflect current mapper
	 * definition in an ALTER TABLE statement.
	 *
	 * @param string $table The name of the table to inspect.
	 * @param array $properties List of {@link phpDataMapper_Property} instances representing the model definition.
	 * @return array
	 * @todo Support composite (named) indexes
	 */
	protected function collectIndicesSyntaxForTableUpdate($table, array $properties)
	{
	  $lines = array();
	  
	  $indexInfo = $this->indexInfoForTable($table);
	  
	  // Collect expected index information
		$primaryKeyPropertyNames = array();
		$uniqueIndexPropertyNames = array();
		$indexPropertyNames = array();
		foreach ($properties as $property) {
		  $propertyName = $property->name();
		  
		  if ($property->option('primary')) {
		    $primaryKeyPropertyNames[] = $propertyName;
		  }
		  
		  if ($property->option('unique')) {
		    $uniqueIndexPropertyNames[] = $propertyName;
		  }
		  
		  if ($property->option('index')) {
		    $indexPropertyNames[] = $propertyName;
		  }
		}
	  
	  // Collect actual index information
		$primaryKeyColumnNames = array();
		$uniqueIndexColumnNames = array();
		$indexColumnNames = array();
		foreach ($indexInfo as $info) {
		  if ($info['Key_name'] == 'PRIMARY') {
		    $primaryKeyColumnNames[] = $info['Column_name'];
		  }
		  elseif ($info['Non_unique'] == 0) {
		    // Unique index
		    $uniqueIndexColumnNames[$info['Key_name']][] = $info['Column_name'];
		  }
		  else {
		    // Regular index
		    $indexColumnNames[$info['Key_name']][] = $info['Column_name'];
		  }
		}
		
		// Update primary key?
		$isEqualPK = count($primaryKeyColumnNames) == count($primaryKeyPropertyNames) &&
		  count(array_diff($primaryKeyColumnNames, $primaryKeyPropertyNames)) == 0;
		if (!$isEqualPK) {
		  $lines[] = "DROP PRIMARY KEY";
		  $lines[] = "ADD PRIMARY KEY(" . implode(',', array_map(array($this, 'quoteName'), $primaryKeyPropertyNames))
		    . ")";
		}
		
		// Indices
		$usedIndexNames = array();
		
		$indexData = array(
		  array($uniqueIndexColumnNames, $uniqueIndexPropertyNames, true),
		  array($indexColumnNames, $indexPropertyNames, false)
		);
		foreach ($indexData as $data) {
		  $currentColumnNames = $data[0];
		  $currentPropertyNames = $data[1];
		  $unique = $data[2];
		  
		  $existingIndexColumnNames = array();
  		foreach ($currentColumnNames as $indexName => $columnNames) {
  		  if (count($columnNames) > 1 || !in_array($columnNames[0], $currentPropertyNames)) {
  		    // Drop all composite indices for now. Support will follow soon.
  		    $lines[] = "DROP INDEX `{$indexName}`";
  		  }
  		  else {
  		    $usedIndexNames[] = $indexName;
  		    $existingIndexColumnNames[] = $columnNames[0];
  		  }
  		}
  		$indicesToAdd = array_diff($currentPropertyNames, $existingIndexColumnNames);

  		foreach ($indicesToAdd as $columnName) {
  		  $indexName = $columnName;
  		  $indexIndex = 0;
  		  while (in_array($indexName, $usedIndexNames)) {
  		    $indexName = $columnName . '_' . $indexIndex++;
  		  }

  		  $usedIndexNames[] = $indexName;

  		  $lines[] = "ADD " . ($unique ? 'UNIQUE ' : '') . "INDEX `{$indexName}`(`{$columnName}`)";
  		}
		}
		
		return $lines;
	}
	
	
	/**
	 * Determines whether the current column definition should be updated based on the property
	 * instance supplied.
	 *
	 * @param array $columnInfo An array containing the column properties as defined in MySQL's
	 *                          information_schema.COLUMNS table.
	 * @param phpDataMapper_Property $property
	 * @return bool
	 */
	protected function shouldUpdateColumnDefinition(array $columnInfo, phpDataMapper_Property $property)
	{
    // Field type
	  $expectedType = $this->_propertyColumnTypeMap[$property->type()];
	  if (strtolower($expectedType) != $columnInfo['DATA_TYPE']) {
	    return true;
	  }
	  
	  // Length
	  if (preg_match('/^[a-z]+\((\d+)\)$/', $columnInfo['COLUMN_TYPE'], $regex_matches)) {
      if ($property->hasOption('length')) {
        $expectedLength = $property->option('length');
      }
      elseif (isset($this->_defaultLengths[$expectedType])) {
        $expectedLength = $this->_defaultLengths[$expectedType];
      }
      
      if (isset($expectedLength) && $expectedLength != $regex_matches[1]) {
        return true;
      }
	  }    
	  
	  // Default value
	  $defaultValue = $property->option('default');
	  if ($property->option('required') && $defaultValue === NULL) {
	    $expectedDefault = '';
	  }
	  else {
	    $expectedDefault = $defaultValue;
	  }
	  if ($expectedDefault != $columnInfo['COLUMN_DEFAULT']) {
	    return true;
	  }
	  
	  // Nullable
	  $expectedNullable = ($property->option('required') || $property->option('primary')) ? 'NO' : 'YES';
	  if ($expectedNullable != $columnInfo['IS_NULLABLE']) {
	    return true;
	  }
	  
	  // Auto increment
	  $isSerialInDB = preg_match('/\bauto_increment\b/', $columnInfo['EXTRA']);
	  if ($isSerialInDB != $property->option('serial', false)) {
	    return true;
	  }
	  
	  return false;
	}
	
	
	/**
	 * Converts a PHP value for safe insertion into SQL syntax.
	 *
	 * @param mixed $value 
	 * @return mixed
	 */
	protected function convertPHPValue($value)
	{
	  if ($value === NULL) {
	    return 'NULL';
	  }
	  elseif (is_bool($value)) {
	    return (int)$value;
	  }
	  elseif (is_int($value)) {
	    return $value;
	  }
	  
	  return $this->escape($value);
	}
}