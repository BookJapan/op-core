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
	 * @return Config_Model;
	 */
	function Config()
	{
		static $config;
		if(!$config){
			$config = new OnePiece5();
		}
		return $config;
	}
	
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
				//	Set selftest flag
				$this->Wizard()->isFailed(true);
				$error = $this->FetchError();
				throw new OpException($error['message']);
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
		$database->password	 = $this->_password($database);
		return $database;
	}
	
	function _password($database)
	{
		return md5($_SERVER['SERVER_ADDR'].', '.$database->name.', '.$database->user);
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
		$config->set->created = gmdate('Y-m-d H:i:s');
		return $config;
	}
	
	function _update()
	{
		$config = new Config();
		$config->set->updated = gmdate('Y-m-d H:i:s');
		$config->limit = 1;
		return $config;
	}
	
	function _delete()
	{
		$config = new Config();
		$config->set->deleted = gmdate('Y-m-d H:i:s');
		$config->limit = 1;
		return $config;
	}
}
