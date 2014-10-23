<?php

abstract class Model_Base extends OnePiece5
{
	function Init()
	{
		parent::Init();
		
		//	Selftest is execute only in the Admin.
		if( $this->Admin() ){
			//	Config
			if( method_exists( $this->Config(), 'Selftest') ){
				$this->Wizard()->SetSelftest( get_class($this), $this->Config()->Selftest() );
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
	
	function Config()
	{
		static $cmgr;
	
		if(!$cmgr){
			//	get config name
			$tmp = explode('_',get_class($this));
			$name = 'Config_'.$tmp[1];
			//	check
			if(!class_exists( $name, true ) ){
				throw new OpException("Does not exists this class.($name)");
			}
			//	instance
			if(!$cmgr = new $name()){
				throw new OpException("Failed to instance of the $name.");
			}
		}
		return $cmgr;
	}
	
	function pdo()
	{
		//  get pdo object
		$pdo = parent::pdo();
		
		//  check connection
		if(!$pdo->isConnect()){
			
			//	implement check
			$obj = $this->config();
			if(!method_exists($obj, 'database')){
				$this->StackError('Does not exists database method at '.get_class($obj));
				return parent::PDO();
			}
			
			//  get database config
			$config = $obj->database();
			
			//  database connection
			if(!$io = $pdo->Connect($config)){
				//  Selftest
				if( method_exists( $this->config(), 'selftest') ){
					$e = new OpException();
					throw $e;
				}else if($this->Admin()){
					$this->mark('![.red[Connect was denied.]]');
					$this->d($config);
				}
			}
		}
		return $pdo;
	}
}

abstract class Config_Base extends OnePiece5
{
	function database($args=null)
	{
		//	check type
		$type = gettype($args);
		if( $type === 'object' ){
			$args = Toolbox::toArray($args);
		}
		
		//	driver
		if(empty($args['driver'])){
			$args['driver'] = 'mysql';
		}
		
		//	host
		if(empty($args['host'])){
			$args['host'] = 'localhost';
		}
		
		//	user
		if(empty($args['user'])){
			//	Config_Name -> Name -> name -> op_mdl_name
			$temp = explode('_',get_class($this));
			$args['user'] = 'op_mdl_'.strtolower($temp[1]);
		}else if($args['driver']==='mysql' and strlen($args['user']) > 16){
			$this->StackError("user name is over 16 character.");
		}
		
		//	password
		if(!isset($args['password'])){
			$key = $_SERVER['HTTP_HOST'].', '.$args['user'].', '.$this->GetEnv('admin-mail');
			$password = md5($key);
			$args['password'] = $password;
		}
		
		//	database
		if(empty($args['database'])){
			$args['database'] = 'onepiece';
		}
		
		//	charset
		if(empty($args['charset'])){
			$args['charset'] = 'utf8';
		}
		
		//	to be converted, if necessary.
		if( $type === 'object' ){
			$args = Toolbox::toObject($args);
		}
		
		return $args;
	}
	
	function config($config=null)
	{
		//	init config
		if(empty($config)){
			$config = new Config();
		}
		
		//	table
		if(!property_exists($config,'table')){
			if(method_exists($this,'table_name')){
				$config->table = $this->table_name();
			}
		}
		
		return $config;
	}
	
	function insert($config=null)
	{
		$config = $this->config($config);
		
		//	default
		$config->set->created	 = gmdate('Y-m-d H:i:s');
	//	$config->set->timestamp	 = 'NOW()';
	//	$config->update->update  = true;
		
		return $config;
	}
	
	/**
	 * @param  Config $config
	 * @return Config
	 */
	function select($config=null)
	{
		$config = $this->config($config);
		
		//	default
		$config->limit = 1;
		$config->cache = 1;
		$config->where->deleted = null;
		
		return $config;
	}
	
	/**
	 * @param  Config $config
	 * @return Config
	 */
	function update($config=null)
	{
		$config = $this->config($config);
		
		$config->set->updated = gmdate('Y-m-d H:i:s');
		$config->limit = 1;
		
		return $config;
	}
	
	/**
	 * @param  Config $config
	 * @return Config
	 */
	function delete($config=null)
	{
		$config = $this->config($config);
		
		$config->set->deleted = gmdate('Y-m-d H:i:s');
		$config->limit = 1;
		
		return $config;
	}
}
