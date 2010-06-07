<?PHP
abstract class phpDataMapper_Property
{ 
  protected $_name;
  
  
  protected $_options;
  
  
  public function __construct($name, array $options = array())
  {
    $this->_name = $name;
    
    $fieldDefaults = $this->fieldDefaults();
    $illegalKeys = array_keys(array_diff_key($options, $fieldDefaults));
    
    if (count($illegalKeys) > 0) {
      throw new phpDataMapper_Exception("Unsupported options were set: " . implode(', ', $illegalKeys));
    }
    
    $this->_options = array_merge($fieldDefaults, $options);
    
    $this->validateOptions();
    
    $this->init();
  }
  
  
  /**
   * Extension of the constructor, but with no parameters. Override this if you want custom
   * initialization for a subclass. There is no need to call super; the default implementation
   * does nothing.
   *
   * @return void
   */
  protected function init() {}
  
  
  /**
   * Validates if there are no contradicting options. Throws an exception upon detection of an
   * error.
   *
   * @return void
   * @throws phpDataMapper_Exception
   * @todo Support composite (named) indexes
   */
  protected function validateOptions()
  {
    $indexOptionValues = array($this->option('unique'), $this->option('index'), $this->option('primary'));
    if (count(array_filter($indexOptionValues)) > 1) {
      throw new phpDataMapper_Exception("Only one index type can be defined per property at once.");
    }
  }
  
  
  /**
   * Constructs a list of field defaults. Subclasses can override this method to add their own
   * specific options. Note that those implementations should first call super and extend the
   * array returned from that.
   *
   * @return array
   */
  protected function fieldDefaults()
  {
    return array(
      'default'   => NULL,
      'required'  => false,

      'primary'   => false,
      'index'     => false,
      'unique'    => false,
      'key'       => false
    );
  }
  
  
  public function name()
  {
    return $this->_name;
  }
  
  
  public function hasOption($key)
  {
    return array_key_exists($key, $this->_options);
  }
  
  
  /**
   * Returns the option value for the specified key.
   *
   * @param string $key 
   * @param mixed $default Optional parameter to specify default value to return if key was not found.
   * @return mixed
   */
  public function option($key)
  {
    // Unfortunately, the only way to be entirely sure whether a second parameter was passed in.
    $args = func_get_args();
    if (count($args) == 2) {
      $defaultValue = $args[1];
    }
    
    if (!$this->hasOption($key)) {
      if (!isset($defaultValue)) {
        throw new InvalidArgumentException("Requested option '$key' does not exist.");
      }
      
      return $defaultValue;
    }
    
    return $this->_options[$key];
  }
  
  
  /**
   * Returns the type for this property.
   *
   * @return string Lowercase string
   */
  public function type()
  {
    $className = get_class($this);
    $parts = explode('_', $className);
    return strtolower($parts[count($parts) - 1]);
  }
}
