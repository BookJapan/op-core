<?php

abstract class Model_Model extends OnePiece5
{
	//  Config object
//	private $config = null;
	
	//  Config Manager
	private $cmgr   = null;
	
	//  Status
	private $_status = null;
	
	function Init()
	{
		parent::Init();
		
		//  init config
//		$this->config = new Config();
		
		//	selftest
		if( $this->Admin() ){			
			if( method_exists( $this, 'Selftest') ){				
				$this->Selftest();
			}
		}
		
	}
	
	function Test()
	{
		$this->mark( $this->GetCallerLine() );
		$this->mark('Called test method: ' . get_class($this));
		return true;
	}
	
	function Help()
	{
		$this->mark("Does not implements help.");
	}
	
	function pdo($name=null)
	{
		//  get pdo object
		$pdo = parent::pdo($name);
		
		//  check connection
		if(!$pdo->isConnect()){
			
			//  get database config
			$config = $this->config()->database();
			
			//  database connection
			if(!$io = $pdo->Connect($config)){
				
				//  Notice to admin
				$config->myname = get_class($this);
				$config->Caller = $this->GetCallerLine();
				
				//  Selftest
				if( method_exists( $this, 'Selftest') ){
					$selftest = $this->Selftest();
					if( $selftest instanceof Config ){
						$_SESSION['OnePiece5']['selftest'] = $selftest;
						$wz = new Wizard();
						$wz->Selftest($selftest);
					}
				}else{
					$config->d();
				}
			}
		}
		
		return $pdo;
	}
	
	/**
	 * 
	 * @param  string $name
	 * @throws OpModelException
	 * @return ModelConfig|boolean
	 */
	function Config($name=null)
	{
		if(!$this->cmgr ){
			
			//	Check
			if(!$name){
				throw new OpModelException("Config name is empty.");
			}
			
			if(!class_exists( $name, true ) ){
				throw new OpModelException("Does not exists this class.($name)");
			}
			
			if(!$this->cmgr = new $name()){
				throw new OpModelException("Failed to instance of the $name.");
			}
		}
		
		return $this->cmgr;
	}
	
	function SetStatus( $status )
	{
		$this->_status[] = $status;
	}
	
	function GetStatus()
	{
		return $this->_status[count($this->_status)-1];
	}
}

/**
 * Separated from the ConfigMgr.
 */
class Config_Model extends OnePiece5
{
	private $_prefix = 'op';
	private $_table  = null;
	private $_dbuser = 'op_mdl';
	
	function pdo($name=null)
	{
		if(!$this->_init_pdo){
			$config = $this->database();
			parent::pdo()->Connect($config);
			$this->_init_pdo = true;
		}
		return parent::pdo($name);
	}
	
	function prefix($prefix=null)
	{
		if( $prefix ){
			$this->_prefix = $prefix;
		}
		return $this->_prefix;
	}
	
	function Database()
	{
		$dbuser = $this->_dbuser;
		
		//	init password
		$password  = OnePiece5::GetEnv('admin-mail');
		$password .= isset($this) ? get_class($this): null;
	
		//	Init config
		$config = new Config();
		
		//	Init database
		$config = new Config();
		$config->driver   = 'mysql';
		$config->host     = 'localhost';
		$config->database = 'onepiece';
		$config->user     = $dbuser;
		$config->password = md5($password);
		$config->charset  = 'utf8';
		
		return $config;
	}
	
	function dbuser($dbuser)
	{
		if( $dbuser ){
			$this->_dbuser = $dbuser;
		}
		return $this->_dbuser;
	}
	
	function table($table=null)
	{
		if( $table ){
			$this->_table = $table;
		}
		return $this->_table;
	}
	
	function table_name()
	{
		$prefix = $this->prefix();
		$table  = $this->table();
		return "{$prefix}_{$table}";
	}
	
	function insert( $table_name=null )
	{
		if(!$table_name){
			$table_name = $this->table_name();
		}
		
		$config = new Config();
		$config->table = $table_name;
		$config->set->created    = gmdate('Y-m-d H:i:s');
		$config->update->updated = gmdate('Y-m-d H:i:s');
		return $config;
	}
	
	function select( $table_name=null )
	{
		if(!$table_name){
			$table_name = $this->table_name();
		}
		
		//	Avoid of ambiguous.
		if( $table_name ){
			if( $pos = strpos( $table_name, '=' ) ){
				//  Join table
				foreach( explode('=',$table_name) as $temp ){
					//  perse　table, column name
					list( $name, $column ) = explode('.',$temp);
					$tables[] = trim($name,'<> ');
				}
	
				//	Disambiguation (Avoid ambiguous)
				foreach( $tables as $name ){
					$deleteds[]   = "$name.deleted";
					$timestamps[] = "$name.timestamp";
				}
			}else{
				//  Single table
				$deleteds[]   = isset($table_name) ? "$table_name.deleted":   'deleted';
				$timestamps[] = isset($table_name) ? "$table_name.timestamp": 'timestamp';
			}
		}else{
			$deleteds = array();
		}
	
		//	Create select config.
		$config = new Config();
		$config->table = $table_name;
	
		//	deleted
		foreach( $deleteds as $deleted ){
			$config->where->$deleted = null;
		}
	
		//	timestamp
		foreach( $timestamps as $timestamp ){
			$config->where->$timestamp = '! null';
		}
	
		//	default cache seconds
		$config->cache = 1;
	
		return $config;
	}
	
	function update( $table_name=null )
	{
		if(!$table_name){
			$table_name = $this->table_name();
		}
		
		$config = new Config();
		$config->table = $table_name;
		$config->set->updated = gmdate('Y-m-d H:i:s');
		$config->limit = 1;
		return $config;
	}
	
	function delete( $table_name=null )
	{
		if(!$table_name){
			$table_name = $this->table_name();
		}
		
		$config = new Config();
		$config->table = $table_name;
		$config->set->deleted = gmdate('Y-m-d H:i:s');
		return $config;
	}
}

/**
 * Old style.
 */
class ConfigModel extends ConfigMgr
{
	//	table prefix
	private $_table_prefix = 'op';

	//	secret key
	private $_secret_key = null;
	
	function SetSecretKey( $var )
	{
		$this->_secret_key = md5($var);
	}
	
	function GetSecretKey()
	{
		return $this->_secret_key;
	}
	
	static function Database()
	{
		$password  = OnePiece5::GetEnv('admin-mail');
		$password .= isset($this) ? get_class($this): null;
		
		$config = parent::database();
		$config->user     = 'op_model';
		$config->password = md5( $password );
		
		return $config;
	}
	
	function GetDatabaseConfig()
	{
		$config = parent::GetDatabaseConfig();
		$config->user = 'op_model';
		return $config;
	}
	
	function GetTablePrefix()
	{
		return $this->_table_prefix;
	}
	
	function SetTablePrefix($prefix)
	{
		$this->_table_prefix = $prefix;
	}
	
}

class OpModelException extends Exception
{
	
}
