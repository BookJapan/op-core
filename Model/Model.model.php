<?php

abstract class Model_Model extends OnePiece5
{
	//  Config object
	private $config = null;
	
	//  Config Manager
	private $cmgr   = null;
	
	//  Status
	private $statusStack = null;
	
	function Init()
	{
		parent::Init();
		
		//  init config
		$this->config = new Config();
	//	$this->config();
	}

	function Test()
	{
		$this->mark( $this->GetCallerLine() );
		$this->mark('Called test method: ' . get_class($this));
		return true;
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
					$this->Selftest();
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
				throw new OpModelException("Failed to instance of the $name.");
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
		$this->statusStack[] = $status;
	}
	
	function GetStatus()
	{
		return $this->statusStack[count($this->statusStack)-1];
	}
}

class ConfigModel extends ConfigMgr
{
//	const TABLE_PREFIX = 'op';
	private $_table_prefix = 'op';
	
	static function Database()
	{
		$password  = OnePiece5::GetEnv('admin-mail');
		$password .= isset($this) ? get_class($this): null;
		
		$config = parent::database();
		$config->user     = 'op_model';
		$config->password = md5( $password );
		return $config;
	}

	function SetPrefix( $prefix )
	{
		$this->_table_prefix = $prefix;
	}
	
	function GetTableName( $label )
	{
		return 'op' .'_'. $label;
	}
}

class OpModelException extends Exception
{
	
}
