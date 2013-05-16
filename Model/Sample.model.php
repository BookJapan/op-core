<?php
/**
 * Sample of how to make the model.
 *
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 *
 */
class Test_Model extends Model_Model
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

	function Insert($config)
	{
		return $this->pdo()->insert($config);
	}
	
	function Select($config)
	{
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
	
	function insert($table_name)
	{
		$config = parent::insert($table_name);
		return $config;
	}
	
	function select($table_name)
	{
		$config = parent::select($table_name);
		return $config;
	}
}
