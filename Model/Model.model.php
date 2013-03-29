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
		$this->config();
	}

	function Test()
	{
		$this->mark(__METHOD__ . ', '.get_class($this));
		$this->mark( $this->GetCallerLine() );
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
					$this->d($config);
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
		if(!$name){
			return;
		}
		
		if(!$this->cmgr ){
			if(!class_exists( $name, true ) ){
				$this->StackError("Does not exists this class.($name)");
				return false;
			}
			
			if(!$this->cmgr = new $name()){
				throw new OpModelException("Failed to instance of the $name.");
			}
			
			//throw new OpModelException('Does not init ConfigMgr.');
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
	const TABLE_PREFIX = 'op';
	
	static function database()
	{
		$password  = OnePiece5::GetEnv('admin-mail');
		$password .= isset($this) ? get_class($this): null;
		
		$config = parent::database();
		$config->user     = 'op_model';
		$config->password = md5( $password );
		return $config;
	}
}

class OpModelException extends Exception
{
	
}
