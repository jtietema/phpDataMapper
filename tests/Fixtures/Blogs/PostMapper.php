<?PHP
class Fixtures_Blogs_PostMapper extends phpDataMapper_TestMapper
{
	protected $_dataSource = 'blogs_posts';
	
	public $id            = array('type' => 'integer', 'primary' => true, 'serial' => true);
	public $blog_id       = array('type' => 'integer', 'key' => true, 'required' => true);
	public $title         = array('type' => 'string', 'required' => true);
	public $body          = array('type' => 'text', 'required' => true);
	public $date_created  = array('type' => 'datetime');
	public $state         = array('type' => 'string', 'required' => true, 'default' => 'DRAFT');
	
	// Each post entity 'hasMany' comment entities
	public $comments = array(
		'type' => 'relation',
		'relation' => 'HasMany',
		'mapper' => 'Fixtures_Blogs_PostCommentsMapper',
		'where' => array('post_id' => 'entity.id'),
		'order' => array('date_created' => 'ASC')
	);
	
	// Each post entity 'hasMany' tags through a 'post_tags' relationship
	public $tags = array(
		'type' => 'relation',
		'relation' => 'HasMany',
		'mapper' => 'Fixtures_Blogs_PostTagsMapper',
		'where' => array('post_id' => 'entity.id'),
		'through' => 'post_tags'
	);
}
