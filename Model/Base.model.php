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
					$e = new OpException();
					throw $e;
				}else{
					$this->d($config);
				}
			}
		}
		return $pdo;
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
	
	private $_const = array();
	
	function SetConst( $key, $var )
	{
		$this->_const[$key] = $var;
	}
	
	function GetConst( $key )
	{
		return isset($this->_const[$key]) ? $this->_const[$key]: null;
	}
}

class Config_Base extends OnePiece5
{
	
}
