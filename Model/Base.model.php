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
				}else{
					$this->mark('![.red[Connect was denied.]]');
					$this->d($config);
				}
			}
		}
		return $pdo;
	}
}

class Config_Base extends OnePiece5
{
	function database($args=null)
	{
		if(empty($args['driver'])){
			$args['driver'] = 'mysql';
		}
		
		if(empty($args['host'])){
			$args['host'] = 'localhost';
		}
		
		if(empty($args['user'])){
			$args['user'] = 'op_mdl_base';
		}else if($args['driver']==='mysql' and strlen($args['user']) > 16){
			$this->StackError("user name is over 16 character.");
		}
		
		if(empty($args['password'])){
			$key = $_SERVER['HTTP_HOST'].', '.$args['user'].', '.$this->GetEnv('admin-mail');
			$password = md5($key);
			$args['password'] = $password;
		}
		
		if(empty($args['database'])){
			$args['database'] = 'onepiece';
		}
		
		if(empty($args['charset'])){
			$args['charset'] = 'utf8';
		}
		
		return $args;
	}
}
