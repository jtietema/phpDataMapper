<?php
/**
 * DataMapper entity class - each item is fetched into this object
 * 
 * @package phpDataMapper
 * @link http://phpdatamapper.com
 * @link http://github.com/vlucas/phpDataMapper
 */
class phpDataMapper_Entity
{
	protected $_loaded = false;
	protected $_data = array();
	protected $_dataModified = array();
	protected $_getterIgnore = array();
	protected $_setterIgnore = array();
	
	
	protected $_new = true;
	
	
	/**
	 * Constructor function
	 */
	public function __construct($data = null)
	{
		// Set given data
		if($data !== null) {
			$this->data($data);
		}
	}
	
	
	/**
	 * Mark row as 'loaded'
	 * Any data set after row is loaded will be modified data
	 *
	 * @param boolean $loaded
	 */
	public function loaded($loaded)
	{
		$this->_loaded = (bool) $loaded;
	}
	
	
	/**
	 * Determines if this entity is dirty, i.e. if it has modified data that has
	 * not been saved to the database yet.
	 *
	 * @return bool
	 */
	public function dirty()
	{
	  return count($this->dataModified()) > 0;
	}
	
	
	public function isNew()
	{
	  return $this->_new;
	}
	
	
	public function setIsNew($is_new)
	{
	  $this->_new = (bool)$is_new;
	}
	
	
	/**
	 * Set multiple attributes at once.
	 * 
	 * @param array $data List of attributes to set (key/value pairs).
	 */
	public function data(array $data)
	{
	  foreach ($data as $key => $value) {
	    $this->$key = $value;
	  }
	  
	  return $this;
	}
	
	
	/**
	 * Returns an array of key/value pairs with data for dirty attributes, i.e. the
	 * attributes that have changed since last saving the entity to the database.
	 * 
	 * @return array
	 */
	public function dataModified()
	{
		return $this->_dataModified;
	}
	
	
	/**
	 * Internal callback that is called whenever the entity is successfully saved to
	 * the database. Allows the entity to update internal state.
	 *
	 * @return void
	 */
	public function wasSaved()
	{
	  $this->_data = array_merge($this->_data, $this->_dataModified);
	  $this->_dataModified = array();
	  
	  $this->setIsNew(false);
	}
	
	
	/**
	 * Returns the attribute values for this entity as an array of key/value pairs.
	 * 
	 * @return array
	 */
	public function toArray()
	{
		return array_merge($this->_data, $this->_dataModified);
	}
	
	
	/**
	 * Enable isset() for object properties
	 */
	public function __isset($key)
	{
		return ($this->$key !== NULL) ? true : false;
	}
	
	
	/**
	 * Getter
	 */
	public function __get($var)
	{
		// Check for custom getter method (override)
		$getMethod = 'get_' . $var;
		if(method_exists($this, $getMethod) && !array_key_exists($var, $this->_getterIgnore)) {
			$this->_getterIgnore[$var] = 1; // Tell this function to ignore the overload on further calls for this variable
			$result = $this->$getMethod(); // Call custom getter
			unset($this->_getterIgnore[$var]); // Remove ignore rule
			return $result;
		
		// Handle default way
		} else {
			if(isset($this->_dataModified[$var])) {
				return $this->_dataModified[$var];
			} elseif(isset($this->_data[$var])) {
				return $this->_data[$var];
			} else {
				return null;
			}
		}
	}
	
	
	/**
	 * Setter
	 */
	public function __set($var, $value)
	{
		// Check for custom setter method (override)
		$setMethod = 'set_' . $var;
		if(method_exists($this, $setMethod) && !array_key_exists($var, $this->_setterIgnore)) {
			$this->_setterIgnore[$var] = 1; // Tell this function to ignore the overload on further calls for this variable
			$result = $this->$setMethod($value); // Call custom setter
			unset($this->_setterIgnore[$var]); // Remove ignore rule
			return $result;
		
		// Handle default way
		} else {
			if($this->_loaded) {
				$this->_dataModified[$var] = $value;
			} else {
				$this->_data[$var] = $value;
			}
		}
	}
}