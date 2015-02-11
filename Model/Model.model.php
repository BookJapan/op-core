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
	/**
	 * Wrapper PDO5 of OnePiece5
	 * 
	 * @return PDO5
	 */
	function pdo()
	{
		$pdo = parent::PDO();	
		if(!$pdo->isConnect()){
			
			//  get database config
			$database = $this->config()->database();
			
			//	databaes name
			if( isset($database->name) ){
				$database->database = $database->name;
			}
			
			//  database connection
			if(!$io = $pdo->Connect($database)){
				$this->p('![.red[Failed to connect database.]]');
				$this->d($database);
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
	function _database( $user_name=null )
	{
		$database = new Config();
		$database->driver	 = 'mysql';
		$database->host		 = 'localhost';
		$database->port		 = '3306';
		$database->name		 = 'onepiece';
		$database->charset	 = 'utf8';
		$database->user		 = $user_name ? $user_name: 'op_mdl_model';
		$database->password	 = md5($_SERVER['SERVER_ADDR'].', '.$database->name.', '.$database->user);
		return $database;
	}
	
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
