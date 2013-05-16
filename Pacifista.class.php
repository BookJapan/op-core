<?php

class Pacifista extends OnePiece5
{	
	function Init()
	{
		parent::init();
		
		//	Env
		$this->SetEnv('pacifista',true);
		$this->SetEnv('cli',true);
		
		//	Change Current Directory.(into a calling file)
		$temp = debug_backtrace();
		chdir(dirname( $temp[count($temp)-1]['file'] ));
		
		//	SERVER
		$_SERVER['REMOTE_ADDR']  = '127.0.0.1';
		$_SERVER['SERVER_ADDR']  = '127.0.0.1';
		$_SERVER['SERVER_ADMIN'] = 'root';
		$_SERVER['SERVER_PORT']  = null;
		$_SERVER['HTTP_HOST']    = null;
		$_SERVER['REQUEST_URI']  = null;
		$_SERVER['QUERY_STRING'] = null;
	}
	
	function GetArgs()
	{
		return isset($_SERVER['argv']) ? $_SERVER['argv']: array();
	}
}

