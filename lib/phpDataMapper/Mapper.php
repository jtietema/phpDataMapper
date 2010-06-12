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
	
	
	// Array of error messages and types
	protected $_errors = array();
	
	// Store cached field info
	protected $_properties = NULL;
	protected $_relations = NULL;
	protected $_primaryKey = NULL;
	
	
	/**
	 * Data source identifier. This can be either a table name, a URL or something else,
	 * depending on the adapter type. This should be configured on every mapper subclass.
	 *
	 * @var string
	 */
	protected $_dataSource;
	
	
	/**
	 * Initializes a Mapper.
	 * 
	 * @param phpDataMapper_Adapter_Interface $adapter The adapter used to modify the table and, if no read adapter is
	 *                                                 specified, also to read.
	 * @param phpDataMapper_Adapter_Interface $adapterRead Optional slave adapter.
	 */
	public function __construct(phpDataMapper_Adapter_Interface $adapter,
	  phpDataMapper_Adapter_Interface $adapterRead = NULL)
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
		if(!$this->_dataSource) {
			throw new $this->_exceptionClass("Error: Data source name must be defined - please define the \$_dataSource"
			  . " variable. This can be a database table name, collection or bucket name, a file name, or a URL, depending on"
			  . " your adapter.");
		}
		
		// Ensure properties have been defined for current table
		if(!$this->properties()) {
			throw new $this->_exceptionClass("Error: Properties must be defined");
		}
		
		// Call init for extension without overriding constructor
		$this->init();
	}
	
	
	/**
	 * Initialization function, run immediately after __construct() so that there is no need to
	 * override the constructor to implement custom initialization in subclasses.
	 * 
	 * @return void
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
	
	
	public function dataSource()
	{
		return $this->_dataSource;
	}
	
	
	/**
	 * Returns a list of {@link phpDataMapper_Property} instances representing the model. Initializes
	 * the list lazily, so the properties are loaded when this method is first called.
	 * 
	 * Also reponsible for initializing relations and collecting the primary key definition.
	 * 
	 * @return array List of {@link phpDataMapper_Property} instances representing the model definition.
	 */
	public function properties()
	{
	  if ($this->_properties === NULL) {
	    $this->_properties = array();
		  $this->_relations = array();
		  $this->_primaryKey = array();
		  
			$getProperties = create_function('$obj', 'return get_object_vars($obj);');
			$properties = $getProperties($this);
			
			foreach ($properties as $name => $options) {
			  $type = $options['type'];
			  unset($options['type']);
			  
			  // Store relations (and remove them from the mix of regular properties)
				if ($type == 'relation') {
					$this->_relations[$name] = $options;
					continue; // skip, not a field
				}
				
				$className = 'phpDataMapper_Property_' . $type;
				$property = new $className($name, $options);
				
				if ($property->option('primary') === true) {
					$this->_primaryKey[] = $property;
				}
				
				$this->_properties[$name] = $property;
			}
	  }
	  
	  return $this->_properties;
	}
	
	
	/**
	 * Returns the {@link phpDataMapper_Property} instance for the supplied name.
	 *
	 * @param string $name 
	 * @return phpDataMapper_Property
	 * @throws InvalidArgumentException If there is no property with the supplied name.
	 */
	public function property($name)
	{
	  $properties = $this->properties();
	  
	  if (!$this->propertyExists($name)) {
      throw new InvalidArgumentException("Unknown property '{$name}' requested.");
	  }
	  return $properties[$name];
	}
	
	
	/**
	 * Get defined relations
	 */
	public function relations()
	{
		if($this->_relations === NULL) {
		  // Properties and relations haven't been initialized yet.
			$this->properties();
		}
		return $this->_relations;
	}
	
	
  /**
   * Get the values of the primary key properties for the supplied entity.
   *
   * @param phpDataMapper_Entity $entity
   * @return array
   */
	public function primaryKey(phpDataMapper_Entity $entity)
	{
		$pkProperties = $this->primaryKeyProperties();
		$values = array();
		foreach ($pkProperties as $pkProperty) {
		  $propertyName = $pkProperty->name();
		  $values[$propertyName] = $entity->$propertyName;
		}
		return $values;
	}
	
	
	/**
	 * Get the {@link phpDataMapper_Property} instances respresenting the primary key.
	 *
	 * @return array An array of {@link phpDataMapper_Property} instances.
	 */
	public function primaryKeyProperties()
	{
	  if ($this->_primaryKey === NULL) {
	    $this->properties();
	  }
		return $this->_primaryKey;
	}
	
	
	/**
	 * Returns the names of the primary key properties.
	 *
	 * @return array An array of strings
	 */
	public function primaryKeyPropertyNames()
	{
	  $names = array();
	  foreach ($this->primaryKeyProperties() as $property) {
	    $names[] = $property->name();
	  }
	  return $names;
	}
	
	
	/**
	 * @return bool
	 */
	public function propertyExists($name)
	{
		return array_key_exists($name, $this->properties());
	}
	
	
	/**
	 * Get an entity by primary key. This method expects as many parameters as there are
	 * primary key properties, in the order in which the properties are defined on the model.
	 * 
	 * When no parameters are supplied, a new entity is returned.
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
			foreach ($this->properties() as $propertyName => $property) {
			  $defaultValue = $property->option('default');
			  if ($defaultValue !== NULL) {
			    $entity->$propertyName = $defaultValue;
			  }
			}
			
			$entity->loaded(true);
		
		// Find record by primary key
		} else {		  
		  $pkPropertyNames = $this->primaryKeyPropertyNames();
		  if (count($pkPropertyNames) != count($pkValues)) {
		    throw new InvalidArgumentException("Expected " . count($pkPropertyNames) . " primary key values, got "
		      . count($pkValues));
		  }
		  
		  $conditions = array_combine($pkPropertyNames, $pkValues);
		  
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
		$entities = $this->adapterRead()->read($query);
		if($entities) {
			return $entities->first();
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
			throw new $this->_exceptionClass("Error: Unable to execute SQL query - failed to create"
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
		$query->select($fields, $this->dataSource());
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
	 * Convenience method to create a new entity, set its data and save it to the database.
	 *
	 * @param array $data Data as key/value pairs.
	 * @return mixed Either the newly created {@link phpDataMapper_Entity} instance, or false if
	 *               the entity couldn't be saved.
	 */
	public function create(array $data)
	{
	  $entity = $this->get()->data($data);
	  if ($this->save($entity)) {
	    return $entity;
	  }
	  return false;
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
			throw new $this->_exceptionClass("First argument must be either an entity object or an array.");
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
	 * Returns the {@link phpDataMapper_Property} object if and only if exactly one
	 * primary key property is found that is also serial. Otherwise, NULL is returned.
	 *
	 * @return mixed A {@link phpDataMapper_Property} instance or NULL.
	 */
	private function singleSerialPrimaryKeyProperty()
	{
	  $pkProperties = $this->primaryKeyProperties();
	  if (count($pkProperties) == 1) {
	    $property = $pkProperties[0];
	    if ($property->option('serial', false)) {
	      return $property;
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
		foreach($entityData as $propertyName => $value) {
			if($this->propertyExists($propertyName)) {
				// Empty values will be NULL (easier to be handled by databases)
				$data[$propertyName] = $this->isEmpty($value) ? NULL : $value;
			}
		}
		
		// Ensure there is actually data to update
		if(count($data) > 0) {
			$result = $this->adapter()->create($this->dataSource(), $data);
			
			// Update primary key on row
		  $pkProperty = $this->singleSerialPrimaryKeyProperty();
		  if ($pkProperty) {
		    $pkPropertyName = $pkProperty->name();
		    $entity->$pkPropertyName = $result;
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
	  
		// Ensure properties exist to prevent errors
		$binds = array();
		foreach($entity->dataModified() as $propertyName => $value) {
			if($this->propertyExists($propertyName)) {
				// Empty values will be NULL (easier to be handled by databases)
				$binds[$propertyName] = $this->isEmpty($value) ? NULL : $value;
			}
		}
		
		// Handle with adapter
		if(count($binds) > 0) {
			$result = $this->adapter()->update($this->dataSource(), $binds, $this->primaryKey($entity));
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
		  throw new InvalidArgumentException("Array or phpDataMapper_Entity object expected, got " . gettype($conditions));
		}
		
		if ($this->beforeDelete($conditions) === false) {
		  return false;
		}
		
		if(is_array($conditions)) {
			$result = $this->adapter()->delete($this->dataSource(), $conditions);
			
			if ($result) {
			  $this->afterDelete($conditions);
			}
		} else {
			throw new $this->_exceptionClass("Conditions must be entity object or array, given " . gettype($conditions));
		}
		
		return $result;
	}
	
	
	/**
	 * This will delete all rows from the mapper's data source and reset the value of any
	 * AUTO_INCREMENT columns to 0.
	 *
	 * @return bool
	 */
	public function truncateDataSource()
	{
		return $this->adapter()->truncateDataSource($this->dataSource());
	}
	
	
	/**
	 * Completely removes the mapper's data source from the database.
	 *
	 * @return bool
	 */
	public function dropDataSource()
	{
		return $this->adapter()->dropDataSource($this->dataSource());
	}
	
	
	/**
	 * Run set validation rules on fields
	 * 
	 * @todo A LOT more to do here... More validation, break up into classes with rules, etc.
	 */
	public function validate(phpDataMapper_Entity $entity)
	{
	  $this->_errors = array();
	  
	  $this->beforeValidate($entity);
	  
		// Check validation rules on each feild
		foreach($this->properties() as $propertyName => $property) {
		  $requirePrimary = ($property->option('primary') && !($property->option('serial', false) && $entity->isNew()));
			if ($property->option('required') || $requirePrimary) {
				// Required field
				if ($this->isEmpty($entity->$propertyName)) {
					$this->error($propertyName, "Required property '" . $propertyName . "' was left blank");
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
		return $this->adapter()->migrate($this->dataSource(), $this->properties());
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
	 * Check if a value is empty, excluding 0 (annoying PHP issue).
	 *
	 * @param mixed $value
	 * @return boolean
	 */
	public function isEmpty($value)
	{
		return (empty($value) && '0' !== $value && 0 !== $value);
	}
	
	
	/**
	 * Check if any errors exist.
	 * 
	 * @param string $propertyName OPTIONAL field name 
	 * @return boolean
	 */
	public function hasErrors($propertyName = NULL)
	{
		if(null !== $propertyName) {
			return isset($this->_errors[$propertyName]) ? count($this->_errors[$propertyName]) : false;
		}
		return count($this->_errors);
	}
	
	
	/**
	 * Get an array of error messages, where the keys are the property names and the values
	 * lists of error messages.
	 *
	 * @param string $msgs
	 * @return array
	 */
	public function errors($msgs = null)
	{
		// Return errors for given property
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
