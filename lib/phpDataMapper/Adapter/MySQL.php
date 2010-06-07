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
	 * Maps phpDataMapper property types to actual adapter types.
	 *
	 * @var array
	 */
	protected $_fieldTypeMap = array(
	  'boolean'   => 'BOOL',
	  'date'      => 'DATE',
	  'datetime'  => 'DATETIME',
	  'float'     => 'FLOAT',
	  'integer'   => 'INT',
	  'string'    => 'VARCHAR',
	  'text'      => 'TEXT'
	);
	
	
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
	protected $_collatedTypes = array('string', 'text');
	
	
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
	 * @param string $table 
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
	 * Syntax for CREATE TABLE with given fields and column syntax
	 *
	 * @param string $table Table name
	 * @param array $fields Array of field objects for this table.
	 * @return string SQL syntax
	 */
	protected function migrateSyntaxTableCreate($table, array $fields)
	{
		$syntax = "CREATE TABLE IF NOT EXISTS `{$table}` (\n";
		
		// Columns
		$columnsSyntax = array();
		foreach ($fields as $field) {
			$columnsSyntax[] = $this->migrateSyntaxColumnDefinition($field);
		}
		$syntax .= implode(",\n", $columnsSyntax);
		
		// Keys
		$usedKeyNames = array();
		$primaryKey = array();
		foreach ($fields as $field) {
		  $fieldName = $field->name();
		  
		  // Determine key field name (can't use same key name twice, so we have to append a number)
			$fieldKeyName = $fieldName;
			$keyIndex = 0;
			while (in_array($fieldKeyName, $usedKeyNames)) {
				$fieldKeyName = $fieldName . '_' . $keyIndex++;
			}
			
			// Key type
			if ($field->option('primary')) {
			  $primaryKey[] = $fieldName;
			}
			
			if ($field->option('unique')) {
				$syntax .= "\n, UNIQUE KEY `{$fieldKeyName}` (`{$fieldName}`)";
				$usedKeyNames[] = $fieldKeyName;
			}
			
			if ($field->option('index')) {
				$syntax .= "\n, KEY `{$fieldKeyName}` (`{$fieldName}`)";
				$usedKeyNames[] = $fieldKeyName;
			}
		}
		
		// Build primary key
		$syntax .= "\n, PRIMARY KEY(" . implode(',', array_map(array($this, 'quoteName'), $primaryKey)) . ")";
		
		// Extra
		$syntax .= "\n) ENGINE={$this->_engine} DEFAULT CHARSET={$this->_charset} COLLATE={$this->_collate};";
		
		return $syntax;
	}
	
	
	/**
	 * Creates the column definition syntax portion for exactly one property.
	 *
	 * @param phpDataMapper_Property $field Object representing the field.
	 * @return string SQL syntax
	 */
	protected function migrateSyntaxColumnDefinition(phpDataMapper_Property $field)
	{
		// Ensure field type is supported
		$fieldType = $field->type();
		if(!isset($this->_fieldTypeMap[$fieldType])) {
			throw new phpDataMapper_Exception("Field type '$fieldType' not supported by this adapter.");
		}
		
		$adapterColumnType = $this->_fieldTypeMap[$fieldType];
		
		// Base definition
		$syntax = "`" . $field->name() . "` " . $adapterColumnType;
		
		// Length
		if ($field->hasOption('length')) {
		  $syntax .= '(' . $field->option('length') . ')';
		}
		
		// Unsigned
		if ($field->hasOption('unsigned')) {
		  $syntax .= ' UNSIGNED';
		}
		
		// Collation
		if (in_array($fieldType, $this->_collatedTypes)) {
		  $syntax .= " COLLATE {$this->_collate}";
		}
		
		// Nullable
		if ($field->option('required')) {
		  $syntax .= ' NOT NULL';
		}
		
		// Default value
		if ($field->option('default') !== NULL) {
		  $syntax .= ' DEFAULT ' . $this->convertPHPValue($field->option('default'));
		}
		
		// Auto increment
		if ($field->option('primary') && $field->option('serial', false)) {
		  $syntax .= ' AUTO_INCREMENT';
		}
		
		return $syntax;
	}
	
	
	/**
	 * Syntax for ALTER TABLE with given fields and column syntax
	 *
	 * @param string $table Table name
	 * @param array $fields Array of fields with all settings
	 * @return mixed SQL syntax as string, or false if there is nothing to update.
	 */
	protected function migrateSyntaxTableUpdate($table, array $fields)
	{		
		$lines = array_merge(
		  $this->collectColumnsSyntaxForTableUpdate($table, $fields),
		  $this->collectIndicesSyntaxForTableUpdate($table, $fields)
		);
		
    if (count($lines) == 0) {
      // Nothing to update
      return false;
    }
    
    return "ALTER TABLE `{$table}`\n" . implode(",\n", $lines) . ";";
	}
	
	
	/**
	 * Compares the supplied list of fields with the actual column definitions for
	 * the table. Returns an array with SQL statements that should be part of an
	 * ALTER TABLE statement.
	 *
	 * @param string $table 
	 * @param array $fields
	 * @return array
	 */
	protected function collectColumnsSyntaxForTableUpdate($table, array $fields)
	{
	  $columnInfo = $this->columnInfoForTable($table);
	  
	  $fieldsToAdd = array();
	  $fieldsToAlter = array();
	  $validFieldNames = array();
	  
	  foreach ($fields as $field) {
	    $fieldName = $field->name();
	    if (!array_key_exists($fieldName, $columnInfo)) {
	      $fieldsToAdd[] = $field;
	    }
	    elseif ($this->shouldUpdateColumnDefinition($columnInfo[$fieldName], $field)) {	      
        $fieldsToAlter[] = $field;
	    }
	    
	    $validFieldNames[] = $fieldName;
	  }
	  
	  $fieldNamesToDrop = array_diff(array_keys($columnInfo), $validFieldNames);
		
		$columnsSyntax = array();
		
		foreach ($fieldNamesToDrop as $fieldName) {
		  $columnsSyntax[] = "DROP COLUMN `{$fieldName}`";
		}
		foreach ($fieldsToAlter as $field) {
		  $columnsSyntax[] = "MODIFY COLUMN " . $this->migrateSyntaxColumnDefinition($field);
		}
		foreach ($fieldsToAdd as $field) {
		  $columnsSyntax[] = "ADD COLUMN " . $this->migrateSyntaxColumnDefinition($field);
		}
		
		return $columnsSyntax;
	}
	
	
	/**
	 * Collects SQL syntax lines to update the database schema to reflect current mapper
	 * definition in an ALTER TABLE statement.
	 *
	 * @param string $table Table name
	 * @param array $fields
	 * @return array
	 * @todo Support composite (named) indexes
	 */
	protected function collectIndicesSyntaxForTableUpdate($table, array $fields)
	{
	  $lines = array();
	  
	  $indexInfo = $this->indexInfoForTable($table);
	  
	  // Collect expected index information
		$primaryKeyFields = array();
		$uniqueIndexFields = array();
		$indexFields = array();
		foreach ($fields as $field) {
		  $fieldName = $field->name();
		  
		  if ($field->option('primary')) {
		    $primaryKeyFields[] = $fieldName;
		  }
		  
		  if ($field->option('unique')) {
		    $uniqueIndexFields[] = $fieldName;
		  }
		  
		  if ($field->option('index')) {
		    $indexFields[] = $fieldName;
		  }
		}
	  
	  // Collect actual index information
		$primaryKeyColumns = array();
		$uniqueIndexColumns = array();
		$indexColumns = array();
		foreach ($indexInfo as $info) {
		  if ($info['Key_name'] == 'PRIMARY') {
		    $primaryKeyColumns[] = $info['Column_name'];
		  }
		  elseif ($info['Non_unique'] == 0) {
		    // Unique index
		    $uniqueIndexColumns[$info['Key_name']][] = $info['Column_name'];
		  }
		  else {
		    // Regular index
		    $indexColumns[$info['Key_name']][] = $info['Column_name'];
		  }
		}
		
		// Update primary key?
		$isEqualPK = count($primaryKeyColumns) == count($primaryKeyFields) &&
		  count(array_diff($primaryKeyColumns, $primaryKeyFields)) == 0;
		if (!$isEqualPK) {
		  $lines[] = "DROP PRIMARY KEY";
		  $lines[] = "ADD PRIMARY KEY(" . implode(',', array_map(array($this, 'quoteName'), $primaryKeyFields)) . ")";
		}
		
		// Indices
		$usedIndexNames = array();
		
		$indexData = array(
		  array($uniqueIndexColumns, $uniqueIndexFields, true),
		  array($indexColumns, $indexFields, false)
		);
		foreach ($indexData as $data) {
		  $currentColumns = $data[0];
		  $currentFields = $data[1];
		  $unique = $data[2];
		  
		  $existingIndexColumns = array();
  		foreach ($currentColumns as $indexName => $columnNames) {
  		  if (count($columnNames) > 1 || !in_array($columnNames[0], $currentFields)) {
  		    // Drop all composite key indices for now. Support will follow soon.
  		    $lines[] = "DROP INDEX `{$indexName}`";
  		  }
  		  else {
  		    $usedIndexNames[] = $indexName;
  		    $existingIndexColumns[] = $columnNames[0];
  		  }
  		}
  		$indicesToAdd = array_diff($currentFields, $existingIndexColumns);

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
	 * @param phpDataMapper_Property $field
	 * @return bool
	 */
	protected function shouldUpdateColumnDefinition(array $columnInfo, phpDataMapper_Property $field)
	{
    // Field type
	  $expectedType = $this->_fieldTypeMap[$field->type()];
	  if (strtolower($expectedType) != $columnInfo['DATA_TYPE']) {
	    return true;
	  }
	  
	  // Length
	  if (preg_match('/^[a-z]+\((\d+)\)$/', $columnInfo['COLUMN_TYPE'], $regex_matches)) {
      if ($field->hasOption('length')) {
        $expectedLength = $field->option('length');
      }
      elseif (isset($this->_defaultLengths[$expectedType])) {
        $expectedLength = $this->_defaultLengths[$expectedType];
      }
      
      if (isset($expectedLength) && $expectedLength != $regex_matches[1]) {
        return true;
      }
	  }    
	  
	  // Default value
	  $defaultValue = $field->option('default');
	  if ($field->option('required') && $defaultValue === NULL) {
	    $expectedDefault = '';
	  }
	  else {
	    $expectedDefault = $defaultValue;
	  }
	  if ($expectedDefault != $columnInfo['COLUMN_DEFAULT']) {
	    return true;
	  }
	  
	  // Nullable
	  $expectedNullable = $field->option('required') || $field->option('primary') ? 'NO' : 'YES';
	  if ($expectedNullable != $columnInfo['IS_NULLABLE']) {
	    return true;
	  }
	  
	  // Auto increment
	  $isSerialInDB = preg_match('/\bauto_increment\b/', $columnInfo['EXTRA']);
	  if ($isSerialInDB != $field->option('serial', false)) {
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