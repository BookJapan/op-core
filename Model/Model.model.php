<?php
/**
 * Model.model.php
 * 
 * Creation: 2015-02-09
 * 
 * @version   1.0
 * @package   op-core
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2015 (C) Tomoaki Nagahara All right reserved.
 */

/**
 * Model_Model
 * 
 * Creation: 2015-02-09
 * 
 * @version   1.0
 * @package   op-core
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2015 (C) Tomoaki Nagahara All right reserved.
 */
abstract class Model_Model extends OnePiece5
{
	function pdo()
	{
		static $pdo;
		if(!$pdo){
			$pdo = parent::PDO();	
			if(!$pdo->isConnect()){
				
				//  get database config
				$config = $this->config()->database();
				$config->password = ';';
				$this->d($config);
				
				//  database connection
				if(!$io = $pdo->Connect($config)){
					$this->mark();
				}
			}
		}
		return $pdo;
	}
}

/**
 * Config_Model
 * 
 * Creation: 2015-02-09
 * 
 * @version   1.0
 * @package   op-core
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2015 (C) Tomoaki Nagahara All right reserved.
 */
abstract class Config_Model extends OnePiece5
{
	function _select()
	{
		$config = new Config();
		$config->where->deleted = null;
		$config->limit = 1;
		$config->cache = 1;
		return $config;
	}
	
	function _insert()
	{
		$config = new Config();
		return $config;
	}
	
	function _update()
	{
		$config = new Config();
		return $config;
	}
	
	function _delete()
	{
		$config = new Config();
		return $config;
	}
}
