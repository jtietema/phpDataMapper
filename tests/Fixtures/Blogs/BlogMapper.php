<?PHP
class Fixtures_Blogs_BlogMapper extends phpDataMapper_TestMapper
{
  protected $_dataSource = 'blogs_blogs';
  
  public $id    = array('type' => 'integer', 'primary' => true, 'serial' => true);
  public $name  = array('type' => 'string', 'required' => true);
  
  public $posts = array(
		'type' => 'relation',
		'relation' => 'HasMany',
		'mapper' => 'Fixtures_Blogs_PostMapper',
		'where' => array('blog_id' => 'entity.id')
	);
}
