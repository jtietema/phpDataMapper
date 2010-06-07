<?PHP
class Fixture_Dog_Mapper extends TestMapper
{
  protected $_dataSource = 'test_dog';
  
  public $id = array('type' => 'integer', 'primary' => true, 'serial' => true);
  public $name = array('type' => 'string', 'required' => true);
  public $name_hash = array('type' => 'string', 'required' => true);
  public $silly_property = array('type' => 'string');
  public $created_at = array('type' => 'date');
  public $updated_at = array('type' => 'date');
  
  
  public function beforeValidate(phpDataMapper_Entity $entity)
  {
    $entity->name_hash = md5($entity->name);
  }
  
  
  public function beforeInsert(phpDataMapper_Entity $entity) 
  {
    $entity->created_at = date($this->adapter()->dateFormat());
    
    if ($entity->name == 'Chuck') {
      return false;
    }
  }
  
  
  public function beforeUpdate(phpDataMapper_Entity $entity)
  {
    $entity->updated_at = date($this->adapter()->dateFormat());
  }
  
  
  public function afterSave(phpDataMapper_Entity $entity)
  {
    $entity->silly_property = $entity->name . '_123';
  }
}
