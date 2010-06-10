<?PHP
require_once dirname(__FILE__) . '/../init.php';

/**
 * Tests relations between models.
 *
 * @package tests
 */
class Test_Relations extends PHPUnit_Framework_TestCase
{
	protected $backupGlobals = false;
	protected $postMapper;
	protected static $blog;
	
	public static function setUpBeforeClass()
	{
	  $postMapper = phpDataMapper_TestHelper::mapper('Blogs', 'BlogMapper');
		self::$blog = $postMapper->get()->data(array('name' => 'Blog About Testing'));
		$postMapper->save(self::$blog);
	}
	
  public static function tearDownAfterClass()
  {
    $postMapper = phpDataMapper_TestHelper::mapper('Blogs', 'BlogMapper');
    $postMapper->truncateDataSource();
    self::$blog = NULL;
  }
	
	/**
	 * Setup/fixtures for each test
	 */
	public function setUp()
	{
		// New mapper instance
		$this->postMapper = phpDataMapper_TestHelper::mapper('Blogs', 'PostMapper');
	}
	public function tearDown() {}
	
	
	public function testBlogPostInsert()
	{
		$post = $this->postMapper->get();
		$post->blog_id = self::$blog->id;
		$post->title = "My Awesome Blog Post";
		$post->body = "<p>This is a really awesome super-duper post.</p><p>It's testing the relationship functions.</p>";
		$post->date_created = date($this->postMapper->adapter()->dateTimeFormat());
		$result = $this->postMapper->save($post);
		
		$this->assertTrue($result);
		$this->assertTrue(is_numeric($post->id));
		
		// Test selcting it to ensure it exists
		$postx = $this->postMapper->get($post->id);
		$this->assertTrue($postx instanceof phpDataMapper_Entity);
		
		return $post->id;
	}
	
	/**
	 * @depends testBlogPostInsert
	 */
	public function testBlogCommentsRelationInsertByObject($postId)
	{
		$post = $this->postMapper->get($postId);
		$commentMapper = phpDataMapper_TestHelper::mapper('Blogs', 'PostCommentsMapper');
		
		// Array will usually come from POST/JSON data or other source
		$commentSaved = false;
		$comment = $commentMapper->get()
			->data(array(
				'post_id' => $postId,
				'name' => 'Testy McTester',
				'email' => 'test@test.com',
				'body' => 'This is a test comment. Yay!',
				'date_created' => date($commentMapper->adapter()->dateTimeFormat())
			));
		try {
			$commentSaved = $commentMapper->save($comment);
			if(!$commentSaved) {
				print_r($commentMapper->errors());
				$this->fail("Comment NOT saved");
			}
		} catch(Exception $e) {
			echo $e->getTraceAsString();
			$commentMapper->debug();
			exit();
		}
		$this->assertTrue($commentSaved !== false);
	}
	
	/**
	 * @depends testBlogPostInsert
	 */
	public function testBlogCommentsRelationCountOne($postId)
	{
		$post = $this->postMapper->get($postId);
		$this->assertTrue(count($post->comments) == 1);
	}
	
	/**
	 * @depends testBlogPostInsert
	 */
	public function testBlogCommentsRelationReturnsRelationObject($postId)
	{
		$post = $this->postMapper->get($postId);
		$this->assertTrue($post->comments instanceof phpDataMapper_Relation);
	}
	
	/**
	 * @depends testBlogPostInsert
	 */
	public function testBlogCommentsRelationCanBeModified($postId)
	{
		$post = $this->postMapper->get($postId);
		$sortedComments = $post->comments->order(array('date_created' => 'DESC'));
		$this->assertTrue($sortedComments instanceof phpDataMapper_Query);
	}
}