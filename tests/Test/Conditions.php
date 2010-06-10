<?php
require_once dirname(__FILE__) . '/../init.php';

/**
 * Tests to stress the Query adapter and how it handles conditions
 */
class Test_Conditions extends PHPUnit_Framework_TestCase
{
	protected $backupGlobals = false;
	const NUM_POSTS = 10;
	
	protected $postMapper;
	
	/**
	 * Prepare the data
	 */
	public static function setUpBeforeClass()
	{
	  $blogMapper = phpDataMapper_TestHelper::mapper('Blogs', 'BlogMapper');
	  $blogMapper->truncateDataSource();
	  
		$postMapper = phpDataMapper_TestHelper::mapper('Blogs', 'PostMapper');
		$postMapper->truncateDatasource();
		
		$postCommentsMapper = phpDataMapper_TestHelper::mapper('Blogs', 'PostCommentsMapper');
		$postCommentsMapper->truncateDatasource();
		
		$blog = $blogMapper->get();
		$blog->name = 'Test Blog';
		$blogMapper->save($blog);
		
		// Insert blog dummy data
		for( $i = 0; $i < self::NUM_POSTS; $i++ ) {
			$postMapper->save(array(
			  'blog_id' => $blog->id,
				'title' => $i,
				'body' => $i,
				'date_created' => date($postMapper->adapter()->dateFormat())
			));
		}
	}
	
	
	public function setUp()
	{
	  $this->postMapper = phpDataMapper_TestHelper::mapper('Blogs', 'PostMapper');
	}
	
	
	public function testDefault()
	{
		$mapper = $this->postMapper;
		$post = $mapper->first(array('id' => 2));
		$this->assertEquals( $post->id, 2 );
	}
	
	public function testEquals()
	{
		$mapper = $this->postMapper;
		$post = $mapper->first(array('id =' => 2));
		$this->assertEquals( $post->id, 2 );
	}
	
	public function testArrayDefault() {
		$mapper = $this->postMapper;
		$post = $mapper->first(array('id' => array(2)));
		$this->assertEquals( $post->id, 2 );
	}
	
	public function testArrayInSingle() {
		$mapper = $this->postMapper;
		$post = $mapper->first(array('id IN' => array(2)));
		$this->assertEquals( $post->id, 2 );
		
		$post = $mapper->first(array('id IN' => array('a')));
		$this->assertFalse( $post );
	}
	
	public function testArrayNotInSingle() {
		$mapper = $this->postMapper;
		$post = $mapper->first(array('id NOT IN' => array(2)));
		$this->assertEquals( $post->id, 1 );
	}
	
	public function testArrayIn() {
		$mapper = $this->postMapper;
		$posts = $mapper->all(array('id IN' => array(3,4,5)));
		$this->assertEquals( $posts->count(), 3 );
	}
	
	public function testArrayNotIn() {
		$mapper = $this->postMapper;
		$posts = $mapper->all(array('id NOT IN' => array(3,4,5)));
		$this->assertEquals( $posts->count(), self::NUM_POSTS - 3 );
	}
	
	public function testOperators() {
		$mapper = $this->postMapper;
		$this->assertFalse( $mapper->first(array('id <' => 1)) );
		$this->assertFalse( $mapper->first(array('id >' => self::NUM_POSTS)) );
		
		$this->assertEquals( $mapper->all(array('id <' => 5))->count(), 4 );
		$this->assertEquals( $mapper->all(array('id >=' => 5))->count(), self::NUM_POSTS - 4 );
	}
	
	public function testMathFunctions() {
		$mapper = $this->postMapper;
		try {
			$this->assertEquals( $mapper->first(array('SQRT(id)' => 2))->id, 4 );
			$this->assertEquals( $mapper->first(array('COS(id-1)' => 1))->id, 1 );
			$this->assertEquals( $mapper->first(array('COS(id-1) + COS(id-1) =' => 2))->id, 1 );
		} catch(Exception $e) {
			phpDataMapper::debug();
		}
	}
}
