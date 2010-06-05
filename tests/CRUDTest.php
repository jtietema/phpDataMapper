<?php
require_once dirname(__FILE__) . '/init.php';

/**
 * Basic CRUD Tests
 * Create, Read, Update, Delete
 */
class CRUDTest extends PHPUnit_Framework_TestCase
{
	protected $backupGlobals = false;
	protected $blogMapper;
	
	/**
	 * Setup/fixtures for each test
	 */
	public function setUp()
	{
		// New mapper instance
		$this->blogMapper = fixture_mapper('Blog');
	}
	public function tearDown() {}
	
	
	public function testAdapterInstance()
	{
		$this->assertTrue(fixture_adapter() instanceof phpDataMapper_Adapter_Interface);
	}
	
	public function testMapperInstance()
	{
		$this->assertTrue($this->blogMapper instanceof phpDataMapper_Base);
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
}