<?PHP
class Fixtures_Blogs_PostCommentsMapper extends phpDataMapper_TestMapper
{
	protected $_dataSource = 'blogs_post_comments';
	
	public $id            = array('type' => 'integer', 'primary' => true, 'serial' => true);
	public $post_id       = array('type' => 'integer', 'index' => true, 'required' => true);
	public $name          = array('type' => 'string', 'required' => true);
	public $email         = array('type' => 'string', 'required' => true);
	public $body          = array('type' => 'text', 'required' => true);
	public $date_created  = array('type' => 'datetime');
}
