<?php

abstract class Model_Model extends OnePiece5
{
	//  Config Manager
	private $cmgr   = null;
	
	//  Status
	private $_status = null;
	
	//	Config class name
	public $_config_name = 'Config_Model';
	
	function Init()
	{
		parent::Init();
		
		//	Do selftest is only admin
		if( $this->Admin() ){
			//	Model
			if( method_exists( $this, 'Selftest') ){
				$this->Selftest();
			}
			//	Config
			if( method_exists( $this->Config(), 'Selftest') ){
				$config = $this->Config()->Selftest();
				$this->Wizard()->SetSelftest( get_class($this), $config );
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
				if( method_exists( $this->config(), 'selftest') ){
					/*
					$selftest = $this->Selftest();
					$selftest->d();
					*/
					/*
					if( $selftest instanceof Config ){
						$this->model('Selftest')->Save( get_class($this), $selftest );
						$e = new OpException();
						$e->isSelftest(true);
						throw $e;
					}
					*/
				}else{
					if( $config instanceof Config ){
						$config->d();
					}else{
						$this->d($config);
					}
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
				
			//	$this->mark( $this->GetCallerLine() );
				
				if( $this->_config_name ){
					$name = $this->_config_name;
				}else{
					throw new OpModelException("Config name is empty.");
				}
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
	private $_host_name		 = 'localhost';
	private $_port_number	 = '3306';
	private $_database_name	 = 'onepiece';
	private $_password		 =  null;
	private $_database_user	 = 'op_mdl';
	private $_table_prefix	 = 'op';
	public  $_table_name	 =  null;
	private $_user_single	 =  null;
	
	//	secret key
	private $_secret_key = null;
	
	function Init()
	{
		parent::Init();
		
		$config = $this->GetEnv('config-database');
		foreach( $config as $key => $var ){
			if( empty($var) ){
				continue;
			}
			switch( $key ){
				case 'host':
					$this->_host_name = $var;
					break;
				case 'port':
					$this->_port_number = $var;
					break;
				case 'user':
					$this->_database_name = $var;
					break;
				case 'password':
					$this->_password = $var;
					break;
				case 'database':
					$this->_database_name = $var;
					break;
				case 'user_single':
					$this->_user_single = $var;
					break;
				case 'prefix':
					$this->_table_prefix = $var;
					break;
				default:
					$this->StackError("undefined key. ($key)");
			}
		}
	}
	
	function SetSecretKey( $var )
	{
		$this->_secret_key = md5($var);
	}
	
	function GetSecretKey()
	{
		return $this->_secret_key ? $this->_secret_key: md5($this->GetEnv('admin-mail'));
	}
	
	/**
	 * return GMT timestamp (Y-m-d H:i:s)
	 *
	 * @return string
	 */
	function gmt($sec=null)
	{
		return date('Y-m-d H:i:s',$this->gmtime($sec));
	}
	
	/**
	 * return GMT time (seconds)
	 *
	 * @return number
	 */
	function gmtime($sec=null)
	{
		return time() - date('Z') + $sec;
	}
	
	function pdo($name=null)
	{
		if(!$this->_init_pdo){
			$config = $this->database();
			parent::pdo()->Connect($config);
			$this->_init_pdo = true;
		}
		return parent::pdo($name);
	}
	
	function database( $args=null )
	{
		//	init password
		$password  = OnePiece5::GetEnv('admin-mail');
		$password .= isset($this) ? get_class($this): null;
		
		//	Init config
		$config = new Config();
		
		//	Init database
		$config = new Config();
		$config->driver   = 'mysql';
		$config->host     = $this->_database_host_name();
		$config->port     = $this->_database_port_number();
		$config->database = $this->_database_name();
		$config->user     = $this->_database_user_name();
		$config->password = md5($password);
		$config->charset  = 'utf8';
		
		if(!$io = $this->_database_user_single()){
			if( isset($args['user']) ){
				$config->user = $args['user'];
			}
		}
		
		return $config;
	}
	
	private function _database_host_name($value=null)
	{
		if( $value ){
			$this->_host_name = $value;
		}
		return $this->_host_name;
	}

	private function _database_port_number($value=null)
	{
		if( $value ){
			$this->_port_number = $value;
		}
		return $this->_port_number;
	}

	private function _database_name($value=null)
	{
		if( $value ){
			$this->_database_name = $value;
		}
		return $this->_database_name;
	}

	private function _database_user_name($value=null)
	{
		if( $value ){
			$this->_database_user = $value;
		}
		return $this->_database_user;
	}
	
	private function _database_user_single($value=null)
	{
		if( $value ){
			$this->_user_single = $value ? true: false;
		}
		return $this->_user_single;
	}
	
	private function table_prefix($prefix=null)
	{
		return self::prefix($prefix);
	}
	
	private function prefix($prefix=null)
	{
		if( $prefix ){
			$this->_table_prefix = $prefix;
		}
		return $this->_table_prefix;
	}
	
	function table_name()
	{
		//	get table name by extends class
		$class = get_class($this);
		$table = $class::TABLE_NAME;
		
		//	get table prefix by self class
		$prefix = $this->_table_prefix;
		
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
		$deleteds = array();
		$timestamps = array();
		
		if(!$table_name){
			$table_name = $this->table_name();
		}
		
		//	Avoid of ambiguous.
		if( $table_name ){
			if( $pos = strpos( $table_name, '=' ) ){
				//  Join table
				foreach( explode('=',$table_name) as $temp ){
					//  perse　table, column name
					if( strpos( $temp,'.') ){
						list( $name, $column ) = explode('.',$temp);
					}else{
						$name = trim($temp);
					}
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
		$config->column->{'*'} = true;
		
		//	deleted
		foreach( $deleteds as $deleted ){
			$config->where->$deleted = null;
		}
	
		//	timestamp
		foreach( $timestamps as $timestamp ){
			$config->where->$timestamp = '! null';
		}
	
		//	default cache seconds
		$config->cache = 10;
	
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
	/*
	function _selftest( $table_name=null )
	{
		$config = new Config();
	
		//	Form
		$config->form->title   = 'Wizard Magic';
		$config->form->message = 'Please enter root(or alter) password.';
		
		//	Database
		$config->database = $this->Database();
	
		//	Column
		if( $table_name ){
			$name = 'created';
			$config->table->$table_name->column->$name->type = 'datetime';
				
			$name = 'updated';
			$config->table->$table_name->column->$name->type = 'datetime';
				
			$name = 'deleted';
			$config->table->$table_name->column->$name->type = 'datetime';
				
			$name = 'timestamp';
			$config->table->$table_name->column->$name->type = 'timestamp';
		}
	
		return $config;
	}
	*/
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
