<?php
require_once dirname(__FILE__) . '/../init.php';

/**
 * Basic CRUD Tests
 * Create, Read, Update, Delete
 */
class Test_CRUD extends PHPUnit_Framework_TestCase
{
	protected $backupGlobals = false;
	protected $blogMapper;
	protected $dogMapper;
	
	/**
	 * Setup/fixtures for each test
	 */
	public function setUp()
	{
		// New mapper instance
		$this->blogMapper = fixture_mapper('Blog');
		$this->dogMapper = fixture_mapper('Dog');
	}
	public function tearDown() {
	  $this->dogMapper->truncateDatasource();
	}
	
	
	public function testAdapterInstance()
	{
		$this->assertTrue(fixture_adapter() instanceof phpDataMapper_Adapter_Interface);
	}
	
	public function testMapperInstance()
	{
		$this->assertTrue($this->blogMapper instanceof phpDataMapper_Mapper);
	}
	
	public function testSampleNewsInsert()
	{
		$mapper = $this->blogMapper;
		$post = $mapper->get();
		$post->title = "Test Post";
		$post->body = "<p>This is a really awesome super-duper post.</p><p>It's really quite lovely.</p>";
		$post->date_created = date($mapper->adapter()->dateTimeFormat());
		$result = $mapper->save($post);
		
		$this->assertTrue($result);
	}
	
	public function testSampleNewsInsertWithEmptyNonRequiredFields()
	{
		$mapper = $this->blogMapper;
		$post = $mapper->get();
		$post->title = "Test Post With Empty Values";
		$post->body = "<p>Test post here.</p>";
		$post->date_created = null;
		try {
			$result = $mapper->save($post);
		} catch(Exception $e) {
			$mapper->debug();
		}
		
		$this->assertTrue($result);
	}
	
	public function testSampleNewsUpdate()
	{
		$mapper = $this->blogMapper;
		$post = $mapper->first(array('title' => "Test Post"));
		
		$this->assertTrue($post instanceof phpDataMapper_Entity);
		
		$post->title = "Test Post Modified";
		$result = $mapper->save($post);
		
		$this->assertTrue($result);
	}
	
	public function testSampleNewsDelete()
	{
		$mapper = $this->blogMapper;
		$post = $mapper->first(array('title' => "Test Post Modified"));
		$result = $mapper->delete($post);
		
		$this->assertTrue($result);
	}
	
	public function testHandlesDefaultValues()
	{
	  $mapper = $this->blogMapper;
	  $post = $mapper->get();
	  $this->assertEquals('DRAFT', $post->state);
	  $post->title = 'title';
	  $post->body = 'body';
	  $result = $mapper->save($post);
    $this->assertTrue($result, "Required value with default prevented entity from being saved.");
	}
	
	public function testTracksDirtiness()
	{
	  $mapper = $this->blogMapper;
	  $post = $mapper->first();
	  $this->assertFalse($post->dirty(), "Entity shouldn't be marked as dirty after loading.");
	  $post->title = 'test';
	  $this->assertTrue($post->dirty(), "Entity should be marked as dirty after changing an attribute.");
	  $result = $mapper->save($post);
	  $this->assertFalse($post->dirty(), "Entity should be marked as non-dirty after saving the entity.");
	}
	
	public function testExecutesHooks()
	{
	  $mapper = $this->dogMapper;
	  $dog = $mapper->get();
	  $dog->name = 'Rufus';
	  
	  $mapper->validate($dog);
	  $this->assertEquals(md5($dog->name), $dog->name_hash);
	  $this->assertNull($dog->silly_property);
	  
	  $mapper->save($dog);
	  $this->assertEquals(date($mapper->adapter()->dateFormat()), $dog->created_at);
	  $this->assertEquals(NULL, $dog->updated_at);
	  $this->assertEquals('Rufus_123', $dog->silly_property);
	  
	  $dog->name = 'Brutus';
	  $mapper->save($dog);
	  $this->assertEquals(md5($dog->name), $dog->name_hash);
	  $this->assertEquals(date($mapper->adapter()->dateFormat()), $dog->updated_at);
	  $this->assertEquals('Brutus_123', $dog->silly_property);
	  
	  $dog2 = $mapper->get();
	  $dog2->name = 'Chuck'; // Check defined in hook to prevent this name from being used.
	  $result = $mapper->save($dog2);
	  $this->assertFalse($result);
	  $this->assertNotEquals('Chuck_123', $dog->silly_property);
	  
	  $this->assertFalse($mapper->first(array('name' => 'Chuck')));
	}
}