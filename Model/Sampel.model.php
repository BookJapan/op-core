<?php
/**
 * Sample of how to make the model.
 *
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 *
 */
class Test_model extends Model_model
{
	function Init()
	{
		$this->Config('TestConfig');
	}
	
	function Test()
	{
		$this->mark(__METHOD__);
		return true;
	}

	function Insert($text=null)
	{
		$config = $this->config()->insert($text);
		return $this->pdo()->insert($config);
	}
	
	function Select()
	{
		$config = $this->config()->select();
		return $this->pdo()->select($config);
	}
	
}

class TestConfig extends ConfigMgr
{
	private $table_name = 't_test';
	
	function database()
	{
		$config = parent::database();
		$config->user     = 't_test';
		$config->password = 't_test';
		return $config;
	}
	
	function insert($text)
	{
		$config = parent::insert();
		$config->table = $this->table_name;
		$config->set->text = $text;
		return $config;
	}
	
	function select()
	{
		$config = parent::select();
		$config->table = $this->table_name;
		return $config;
	}
}