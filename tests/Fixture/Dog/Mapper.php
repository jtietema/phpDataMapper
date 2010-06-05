<?PHP
class Fixture_Dog_Mapper extends TestMapper
{
  protected $_datasource = 'test_dog';
  
  public $id = array('type' => 'integer', 'primary' => true, 'serial' => true);
  public $name = array('type' => 'string', 'required' => true);
  public $name_hash = array('type' => 'string', 'required' => true);
  public $created_at = array('type' => 'date');
  public $updated_at = array('type' => 'date');
  
  
  public function __beforeValidate(phpDataMapper_Entity $entity)
  {
    $entity->name_hash = md5($entity->name);
  }
  
  
  public function __beforeInsert(phpDataMapper_Entity $entity) 
  {
    $entity->created_at = date($this->adapter()->dateFormat());
    
    if ($entity->name == 'Chuck') {
      return false;
    }
  }
  
  
  public function __beforeUpdate(phpDataMapper_Entity $entity)
  {
    $entity->updated_at = date($this->adapter()->dateFormat());
  }
}
