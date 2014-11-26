<?php
/**
 * Sample.model.php
 * 
 * Sample of how to make the model.
 *
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 */

/**
 * Model_Sample
 * 
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 */
class Model_Sample extends Model_Model
{
	function Insert($config)
	{
		return $this->pdo()->insert($config);
	}
	
	function Select($config)
	{
		return $this->pdo()->select($config);
	}
}

/**
 * Config_Sample
 * 
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 */
class Config_Sample extends Config_Model
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
