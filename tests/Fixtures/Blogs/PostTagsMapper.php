<?PHP
class Fixtures_Blogs_PostTagsMapper extends phpDataMapper_TestMapper
{
	protected $_dataSource = 'blogs_post_tags';
	
	public $post_id = array('type' => 'integer', 'primary' => true, 'serial' => true);
	public $tag_id  = array('type' => 'integer', 'key' => true);
	
	public $post_tags = array(
		'type' => 'relation',
		'relation' => 'HasMany',
		'mapper' => 'Fixtures_Blogs_TagsMapper',
		'where' => array('tag_id' => 'entity.tag_id')
	);
}
