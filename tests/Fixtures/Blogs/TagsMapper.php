<?PHP
class Fixtures_Blogs_TagsMapper extends phpDataMapper_TestMapper
{
	protected $_dataSource = 'blogs_tags';
	
	public $id    = array('type' => 'integer', 'primary' => true);
	public $name  = array('type' => 'string', 'required' => true, 'unique' => true);
}
