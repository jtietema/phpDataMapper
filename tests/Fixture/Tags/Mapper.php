<?php
/**
 * Tags Mapper
 */
class Fixture_Tags_Mapper extends TestMapper
{
	protected $_dataSource = 'test_tags';
	
	public $id = array('type' => 'integer', 'primary' => true);
	public $name = array('type' => 'string', 'required' => true, 'unique' => true);
}