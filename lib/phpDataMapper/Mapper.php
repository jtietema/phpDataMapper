<?php
/**
 * Abstract mapper superclass.
 * 
 * @package phpDataMapper
 * @link http://phpdatamapper.com
 * @link http://github.com/vlucas/phpDataMapper
 */
abstract class phpDataMapper_Mapper
{
	// Class Names for required classes - Here so they can be easily overridden
	protected $_entityClass = 'phpDataMapper_Entity';
	protected $_queryClass = 'phpDataMapper_Query';
	protected $_collectionClass = 'phpDataMapper_Collection';
	protected $_exceptionClass = 'phpDataMapper_Exception';
	
	// Stored adapter connections
	protected $_adapter;
	protected $_adapterRead;
	
	
	/**
	 * A list of aliases for field types. This array maps the aliases to the actual types.
	 *
	 * @var array
	 */
	protected $_fieldTypeAliases = array(
	  'int'     => 'integer',
	  'decimal' => 'float',
	  'bool'    => 'boolean'
	);
	
	
	// Array of error messages and types
	protected $_errors = array();
	
	// Store cached field info
	protected $_fields = NULL;
	protected $_relations = NULL;
	protected $_primaryKey = NULL;
	
	// Data source setup info
	protected $_datasource;
	
	
	/**
	 *	Constructor Method
	 */
	public function __construct(phpDataMapper_Adapter_Interface $adapter, $adapterRead = null)
	{
		$this->_adapter = $adapter;
		
		// Slave adapter if given (usually for reads)
		if(null !== $adapterRead) {
			if($adapterRead instanceof phpDataMapper_Adapter_Interface) {
				$this->_adapterRead = $adapterRead;
			} else {
				throw new InvalidArgumentException("Secondary/Slave adapter must implement 'phpDataMapper_Adapter_Interface'");
			}
		}
		
		// Ensure table has been defined
		if(!$this->_datasource) {
			throw new $this->_exceptionClass("Error: Datasource name must be defined - please define the \$_datasource"
			  . " variable. This can be a database table name, collection or bucket name, a file name, or a URL, depending on"
			  . " your adapter.");
		}
		
		// Ensure fields have been defined for current table
		if(!$this->fields()) {
			throw new $this->_exceptionClass("Error: Fields must be defined");
		}
		
		// Call init for extension without overriding constructor
		$this->init();
	}
	
	
	/**
	 * Initialization function, run immediately after __construct() so that the constructor is never overridden
	 */
	public function init()
	{
		
	}
	
	
	/**
	 * Get current adapter object
	 */
	public function adapter()
	{
		return $this->_adapter;
	}
	
	
	/**
	 * Get adapter object that will serve as the 'slave' for reads
	 */
	public function adapterRead()
	{
		if($this->_adapterRead) {
			return $this->_adapterRead;
		} else {
			return $this->_adapter;
		}
	}
	
	
	/**
	 * Get entity class name to use
	 * 
	 * @return string
	 */
	public function entityClass()
	{
		return $this->_entityClass;
	}
	
	
	/**
	 * Get query class name to use
	 * 
	 * @return string
	 */
	public function queryClass()
	{
		return $this->_queryClass;
	}
	
	
	/**
	 * Get collection class name to use
	 * 
	 * @return string
	 */
	public function collectionClass()
	{
		return $this->_collectionClass;
	}
	
	
	/**
	 * Get name of the data source
	 */
	public function datasource()
	{
		return $this->_datasource;
	}
	
	
	/**
	 * Get formatted fields with all neccesary array keys and values.
	 * Merges defaults with defined field values to ensure all options exist for each field.
	 *
	 * @return array Defined fields plus all defaults for full array of all possible options
	 */
	public function fields()
	{
		if($this->_fields !== NULL) {
			$returnFields = $this->_fields;
		} else {
		  $this->_fields = array();
		  $this->_relations = array();
		  $this->_primaryKey = array();
		  
			$getFields = create_function('$obj', 'return get_object_vars($obj);');
			$fields = $getFields($this);
			
			$returnFields = array();
			foreach($fields as $fieldName => $fieldOpts) {
			  $fieldType = $fieldOpts['type'];
			  unset($fieldOpts['type']);
			  
			  // Store relations (and remove them from the mix of regular fields)
				if ($fieldType == 'relation') {
					$this->_relations[$fieldName] = $fieldOpts;
					continue; // skip, not a field
				}
				
				while (isset($this->_fieldTypeAliases[$fieldType])) {
				  $fieldType = $this->_fieldTypeAliases[$fieldType];
				}
				
				$fieldClassName = 'phpDataMapper_Property_' . $fieldType;
				$field = new $fieldClassName($fieldName, $fieldOpts);
				
				if ($field->option('primary') === true) {
					$this->_primaryKey[] = $field;
				}
				
				$returnFields[$fieldName] = $field;
			}
			$this->_fields = $returnFields;
		}
		return $returnFields;
	}
	
	
	/**
	 * Get defined relations
	 */
	public function relations()
	{
		if($this->_relations === NULL) {
		  // Fields and relations haven't been initialized yet.
			$this->fields();
		}
		return $this->_relations;
	}
	
	
  /**
   * Get the values of the primary key fields for the supplied entity.
   *
   * @param phpDataMapper_Entity $entity
   * @return array
   */
	public function primaryKey(phpDataMapper_Entity $entity)
	{
		$pkFields = $this->primaryKeyFields();
		$values = array();
		foreach ($pkFields as $pkField) {
		  $pkFieldName = $pkField->name();
		  $values[$pkFieldName] = $entity->$pkFieldName;
		}
		return $values;
	}
	
	
	/**
	 * Get the objects respresenting the primary key fields.
	 *
	 * @return array
	 */
	public function primaryKeyFields()
	{
	  if ($this->_primaryKey === NULL) {
	    $this->fields();
	  }
		return $this->_primaryKey;
	}
	
	
	/**
	 * Returns the names of the primary key fields.
	 *
	 * @return array
	 */
	public function primaryKeyFieldNames()
	{
	  $names = array();
	  foreach ($this->primaryKeyFields() as $field) {
	    $names[] = $field->name();
	  }
	  return $names;
	}
	
	
	/**
	 * Check if field exists in defined fields
	 */
	public function fieldExists($field)
	{
		return array_key_exists($field, $this->fields());
	}
	
	
	/**
	 * Get an entity by primary key. This method expects as many parameters as there are
	 * primary key fields, in the order in which the fields are defined on the model.
	 * 
	 * When no parameters are supplied, a new record is returned.
	 *
	 * @param mixed $value,...
	 * @return phpDataMapper_Entity
	 */
	public function get()
	{
	  $pkValues = func_get_args();
	  
		// Create new row object
		if(count($pkValues) == 0) {
			$entity = new $this->_entityClass();
			
			// Set default values.
			foreach ($this->fields() as $fieldName => $field) {
			  $defaultValue = $field->option('default');
			  if ($defaultValue !== NULL) {
			    $entity->$fieldName = $defaultValue;
			  }
			}
			
			$entity->loaded(true);
		
		// Find record by primary key
		} else {		  
		  $pkFields = $this->primaryKeyFieldNames();
		  if (count($pkFields) != count($pkValues)) {
		    throw new InvalidArgumentException(__METHOD__ . " Expected " . count($pkFields) . " primary key values, got "
		      . count($pkValues));
		  }
		  
		  $conditions = array_combine($pkFields, $pkValues);
		  
			$entity = $this->first($conditions);
		}
		return $entity;
	}
	
	
	/**
	 * Load defined relations 
	 */
	public function getRelationsFor(phpDataMapper_Entity $entity)
	{
		$relatedColumns = array();
		$rels = $this->getEntityRelationWithValues($entity);
		if(count($rels) > 0) {
			foreach($rels as $column => $relation) {
				$mapperName = isset($relation['mapper']) ? $relation['mapper'] : false;
				if(!$mapperName) {
					throw new $this->_exceptionClass("Relationship mapper for '" . $column . "' has not been defined.");
				}
				
				// Set conditions for relation query
				$relConditions = array();
				if(isset($relation['where'])) {
					$relConditions = $relation['where'];
				}
				
				// Create new instance of mapper
				$mapper = new $mapperName($this->adapter());
				
				// Load relation class
				$relationClass = 'phpDataMapper_Relation_' . $relation['relation'];
				
				// Set column equal to relation class instance
				$relationObj = new $relationClass($mapper, $relConditions, $relation);
				$relatedColumns[$column] = $relationObj;
				
			}
		}
		return (count($relatedColumns) > 0) ? $relatedColumns : false;
	}
	
	
	/**
	 * Replace entity value placeholders on relation definitions
	 * Currently replaces 'entity.[col]' with the column value from the passed entity object
	 */
	public function getEntityRelationWithValues(phpDataMapper_Entity $entity)
	{
		$rels = $this->relations();
		if(count($rels) > 0) {
			foreach($rels as $column => $relation) {
				// Load foreign keys with data from current row
				// Replace 'entity.[col]' with the column value from the passed entity object
				if(isset($relation['where'])) {
					foreach($relation['where'] as $relationCol => $col) {
						if(is_string($col) && strpos($col, 'entity.') !== false) {
							$col = str_replace('entity.', '', $col);
							$rels[$column]['where'][$relationCol] = $entity->$col;
						}
					}
				}
			}
		}
		return $rels;
	}
	
	
	/**
	 * Find records with given conditions
	 * If all parameters are empty, find all records
	 *
	 * @param array $conditions Array of conditions in column => value pairs
	 */
	public function all(array $conditions = array())
	{
		return $this->select()->where($conditions);
	}
	
	
	/**
	 * Find first record matching given conditions
	 *
	 * @param array $conditions Array of conditions in column => value pairs
	 */
	public function first(array $conditions = array())
	{
		$query = $this->select()->where($conditions)->limit(1);
		$entitys = $this->adapterRead()->read($query);
		if($entitys) {
			return $entitys->first();
		} else {
			return false;
		}
	}
	
	
	/**
	 * Find records with custom SQL query
	 *
	 * @param string $sql SQL query to execute
	 * @param array $binds Array of bound parameters to use as values for query
	 * @throws phpDataMapper_Exception
	 */
	public function query($sql, array $binds = array())
	{
		// Add query to log
		phpDataMapper::logQuery($sql, $binds);
		
		// Prepare and execute query
		if($stmt = $this->adapter()->prepare($sql)) {
			$results = $stmt->execute($binds);
			if($results) {
				$r = $this->getResultSet($stmt);
			} else {
				$r = false;
			}
			
			return $r;
		} else {
			throw new $this->_exceptionClass(__METHOD__ . " Error: Unable to execute SQL query - failed to create"
			  . " prepared statement from given SQL");
		}
		
	}
	
	
	/**
	 * Begin a new database query - get query builder
	 * Acts as a kind of factory to get the current adapter's query builder object
	 * 
	 * @param mixed $fields String for single field or array of fields
	 */
	public function select($fields = "*")
	{
		$query = new $this->_queryClass($this);
		$query->select($fields, $this->datasource());
		return $query;
	}
	
	
	/**
	 * Save related rows of data
	 */
	protected function saveRelatedRowsFor($entity, array $fillData = array())
	{
		$relationColumns = $this->getRelationsFor($entity);
		foreach($entity->toArray() as $field => $value) {
			if($relationColumns && array_key_exists($field, $relationColumns) && (is_array($value) || is_object($value))) {
				foreach($value as $relatedRow) {
					// Determine relation object
					if($value instanceof phpDataMapper_Relation) {
						$relatedObj = $value;
					} else {
						$relatedObj = $relationColumns[$field];
					}
					$relatedMapper = $relatedObj->mapper();
					
					// Row object
					if($relatedRow instanceof phpDataMapper_Entity) {
						$relatedRowObj = $relatedRow;
						
					// Associative array
					} elseif(is_array($relatedRow)) {
						$relatedRowObj = new $this->_entityClass($relatedRow);
					}
					
					// Set column values on row only if other data has been updated (prevents queries for unchanged existing rows)
					if(count($relatedRowObj->dataModified()) > 0) {
						$fillData = array_merge($relatedObj->foreignKeys(), $fillData);
						$relatedRowObj->data($fillData);
					}
					
					// Save related row
					$relatedMapper->save($relatedRowObj);
				}
			}
		}
	}
	
	
	/**
	 * Save record
	 * Will update if primary key found, insert if not
	 * Performs validation automatically before saving record
	 *
	 * @param mixed $entity Entity object or array of field => value pairs
	 */
	public function save($entity)
	{
		if(is_array($entity)) {
			$entity = $this->get()->data($entity);
		}
		
		if(!($entity instanceof phpDataMapper_Entity)) {
			throw new $this->_exceptionClass(__METHOD__ . " first argument must be entity object or array");
		}
		
		// Run validation
		if($this->validate($entity)) {
		  if ($this->beforeSave($entity) === false) {
		    return false;
		  }
		  
			if($entity->isNew()) {
				$result = $this->insert($entity);
			} else {
				$result = $this->update($entity);
			}
			
			if ($result) {
			  $this->afterSave($entity);
			}
		} else {
			$result = false;
		}
		
		return (bool)$result;
	}
	
	
	/**
	 * Returns the field object if and only if exactly one primary key field is found
	 * that is also a serial. Otherwise, NULL is returned.
	 *
	 * @return mixed A phpDataMapper_Property instance or NULL.
	 */
	private function singleSerialPrimaryKeyField()
	{
	  $pkFields = $this->primaryKeyFields();
	  if (count($pkFields) == 1) {
	    $pkField = $pkFields[0];
	    if ($pkField->option('serial', false)) {
	      return $pkField;
	    }
	  }
	  
	  return NULL;
	}
	
	
	/**
	 * Insert an entity in the database.
	 *
	 * @param phpDataMapper_Entity $entity
	 * @return bool
	 */
	private function insert(phpDataMapper_Entity $entity)
	{		
		if ($this->beforeInsert($entity) === false) {
		  return false;
		}
		
		$data = array();
		$entityData = $entity->toArray();
		foreach($entityData as $field => $value) {
			if($this->fieldExists($field)) {
				// Empty values will be NULL (easier to be handled by databases)
				$data[$field] = $this->isEmpty($value) ? null : $value;
			}
		}
		
		// Ensure there is actually data to update
		if(count($data) > 0) {
			$result = $this->adapter()->create($this->datasource(), $data);
			
			// Update primary key on row
		  $pkField = $this->singleSerialPrimaryKeyField();
		  if ($pkField) {
		    $pkFieldName = $pkField->name();
		    $entity->$pkFieldName = $result;
		  }
			
			// Load relations for this row so they can be used immediately
			$relations = $this->getRelationsFor($entity);
			if($relations && is_array($relations) && count($relations) > 0) {
				foreach($relations as $relationCol => $relationObj) {
					$entity->$relationCol = $relationObj;
				}
			}
		} else {
			$result = false;
		}
		
		// Save related rows
		if($result) {
		  $entity->wasSaved();
			$this->saveRelatedRowsFor($entity);
			
			$this->afterInsert($entity);
		}
		
		return (bool)$result;
	}
	
	
	/**
	 * Update given row object
	 * 
	 * @return bool
	 */
	private function update(phpDataMapper_Entity $entity)
	{
	  if ($this->beforeUpdate($entity) === false) {
	    return false;
	  }
	  
		// Ensure fields exist to prevent errors
		$binds = array();
		foreach($entity->dataModified() as $field => $value) {
			if($this->fieldExists($field)) {
				// Empty values will be NULL (easier to be handled by databases)
				$binds[$field] = $this->isEmpty($value) ? null : $value;
			}
		}
		
		// Handle with adapter
		if(count($binds) > 0) {
			$result = $this->adapter()->update($this->datasource(), $binds, $this->primaryKey($entity));
		} else {
			$result = true;
		}
		
		// Save related rows
		if($result) {
		  $entity->wasSaved();
			$this->saveRelatedRowsFor($entity);
			
			$this->afterUpdate($entity);
		}
		
		return (bool)$result;
	}
	
	
	/**
	 * Delete items matching given conditions
	 *
	 * @param mixed $conditions Array of conditions in column => value pairs or Entity object
	 */
	public function delete($conditions)
	{
		if($conditions instanceof phpDataMapper_Entity) {
			$conditions = array(
				0 => array('conditions' => $this->primaryKey($conditions))
			);
		}
		
		if (!is_array($conditions)) {
		  throw new InvalidArgumentException(__METHOD__ . " Array or phpDataMapper_Entity object expected, got "
		    . gettype($conditions));
		}
		
		if ($this->beforeDelete($conditions) === false) {
		  return false;
		}
		
		if(is_array($conditions)) {
			$result = $this->adapter()->delete($this->datasource(), $conditions);
			
			if ($result) {
			  $this->afterDelete($conditions);
			}
		} else {
			throw new $this->_exceptionClass(__METHOD__ . " conditions must be entity object or array, given "
			  . gettype($conditions));
		}
		
		return $result;
	}
	
	
	/**
	 * Truncate data source
	 * Should delete all rows and reset serial/auto_increment keys to 0
	 */
	public function truncateDatasource() {
		return $this->adapter()->truncateDatasource($this->datasource());
	}
	
	
	/**
	 * Drop/delete data source
	 * Destructive and dangerous - drops entire data source and all data
	 */
	public function dropDatasource() {
		return $this->adapter()->dropDatasource($this->datasource());
	}
	
	
	/**
	 * Run set validation rules on fields
	 * 
	 * @todo A LOT more to do here... More validation, break up into classes with rules, etc.
	 */
	public function validate(phpDataMapper_Entity $entity)
	{
	  $this->beforeValidate($entity);
	  
		// Check validation rules on each feild
		foreach($this->fields() as $fieldName => $field) {
			if($field->option('required') === true) {
				// Required field
				if($this->isEmpty($entity->$fieldName)) {
					$this->error($field, "Required field '" . $fieldName . "' was left blank");
				}
			}
		}
		
		// Check for errors
		if($this->hasErrors()) {
			return false;
		} else {
			return true;
		}
	}
	
	
	/**
	 * Migrate table structure changes from model to database
	 */
	public function migrate()
	{
		return $this->adapter()->migrate($this->datasource(), $this->fields());
	}
	
	
	/**
	 * Magic template method that is called just before validating an object.
	 * Override this to fill in any "magic" required properties that would otherwise
	 * be missing and thus fail validation.
	 *
	 * @param phpDataMapper_Entity $entity The entity that will be validated.
	 * @return void
	 */
	public function beforeValidate(phpDataMapper_Entity $entity) {}
	
	
	/**
	 * Called before a new entity is inserted in the database. Insertion is cancelled
	 * if and only if this method returns the boolean false.
	 *
	 * @param phpDataMapper_Entity $entity The entity that will be inserted.
	 * @return mixed
	 */
	public function beforeInsert(phpDataMapper_Entity $entity) {}
	
	
	/**
	 * Called after successful insertion of an entity. Since the entity is already
	 * inserted in the database when this method is called, there is no point in
	 * returning false. There is no rollback mechanism.
	 *
	 * @param phpDataMapper_Entity $entity The entity that was inserted.
	 * @return void
	 */
	public function afterInsert(phpDataMapper_Entity $entity) {}
	
	
	/**
	 * Called before an existing entity's changes are updated in the database. The update
	 * is cancelled if and only if this method returns the boolean false.
	 *
	 * @param phpDataMapper_Entity $entity The entity that will be updated.
	 * @return mixed
	 */
	public function beforeUpdate(phpDataMapper_Entity $entity) {}
	
	
	/**
	 * Called after an entity is successfully updated. Since the entity is already
 	 * updated in the database when this method is called, there is no point in
 	 * returning false. There is no rollback mechanism.
	 *
	 * @param phpDataMapper_Entity $entity The entity that was updated.
	 * @return void
	 */
	public function afterUpdate(phpDataMapper_Entity $entity) {}
	
	
	/**
	 * Called before an entity is saved to the databse. This can be either an
	 * insertion of a new entity, or an update of an existing entity. Saving is
	 * cancelled if and only if this method returns the boolean false.
	 *
	 * @param phpDataMapper_Entity $entity The entity that will be saved.
	 * @return mixed
	 */
	public function beforeSave(phpDataMapper_Entity $entity) {}
	
	
	/**
	 * Called after an entity is successfully saved to the database. Since the
	 * entity is already saved to the database when this method is called,
	 * there is no point in returning false. There is no rollback mechanism.
	 *
	 * @param phpDataMapper_Entity $entity The entity that was saved.
	 * @return void
	 */
	public function afterSave(phpDataMapper_Entity $entity) {}
	
	
	/**
	 * Called before a delete query is performed on the database. Note that the
	 * {@link phpDataMapper_Mapper::delete()} method differs from other CRUD methods
	 * in the Base class in that it expects an array of conditions instead of an
	 * entity. Deletion can be cancelled by returning the boolean false from this
	 * method.
	 *
	 * @param array $conditions 
	 * @return mixed
	 */
	public function beforeDelete(array $conditions) {}
	
	
	/**
	 * Called after a delete query is performed on the database. Since the
 	 * query is already executed when this method is called, there is no point
 	 * in returning false. There is no rollback mechanism.
	 *
	 * @param array $conditions
	 * @return void
	 * @see phpDataMapper_Mapper::beforeDelete()
	 */
	public function afterDelete(array $conditions) {}
	
	
	/**
	 * Check if a value is empty, excluding 0 (annoying PHP issue)
	 *
	 * @param mixed $value
	 * @return boolean
	 */
	public function isEmpty($value)
	{
		return (empty($value) && '0' !== $value && 0 !== $value);
	}
	
	
	/**
	 * Check if any errors exist
	 * 
	 * @param string $field OPTIONAL field name 
	 * @return boolean
	 */
	public function hasErrors($field = null)
	{
		if(null !== $field) {
			return isset($this->_errors[$field]) ? count($this->_errors[$field]) : false;
		}
		return count($this->_errors);
	}
	
	
	/**
	 * Get array of error messages
	 *
	 * @return array
	 */
	public function errors($msgs = null)
	{
		// Return errors for given field
		if(is_string($msgs)) {
			return isset($this->_errors[$msgs]) ? $this->_errors[$msgs] : array();
		
		// Set error messages from given array
		} elseif(is_array($msgs)) {
			foreach($msgs as $field => $msg) {
				$this->error($field, $msg);
			}
		}
		return $this->_errors;
	}
	
	
	/**
	 * Add an error to error messages array
	 *
	 * @param string $field Field name that error message relates to
	 * @param mixed $msg Error message text - String or array of messages
	 */
	public function error($field, $msg)
	{
		if(is_array($msg)) {
			// Add array of error messages about field
			foreach($msg as $msgx) {
				$this->_errors[$field][] = $msgx;
			}
		} else {
			// Add to error array
			$this->_errors[$field][] = $msg;
		}
	}
}
