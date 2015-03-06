<?php
/**
 * Carpenter.class.php
 * 
 * Creation: 2015-03-02
 * 
 * @version   1.0
 * @package   op-core
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2015 (C) Tomoaki Nagahara All right reserved.
 */

/**
 * Carpenter
 * 
 * Creation: 2015-03-02
 *
 * @version   1.0
 * @package   op-core
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2015 (C) Tomoaki Nagahara All right reserved.
 */
class Carpenter extends OnePiece5
{
	/**
	 * Error message.
	 * 
	 * @var string
	 */
	private $_error;
	
	/**
	 * Blue print
	 *
	 * @var Config
	 */
	private $_blueprint;
	
	/**
	 * Root user name.
	 * 
	 * @var string
	 */
	private $_user;
	
	/**
	 * Root user's password.
	 * 
	 * @var string
	 */
	private $_password;

	private $_log;
	
	function Init()
	{
		parent::Init();
		if(!$this->Admin()){
			$this->StackError("Not admin call.");
		}
		
		$this->_log = array();
	}
	
	function Debug()
	{
		$this->d($this->_log);
	}
	
	function Log($message, $result=null)
	{
		$log['call']	 = $this->GetCallerLine();
		$log['result']	 = $result;
		$log['message']	 = $message;
		$this->_log[]	 = $log;
		
		if( $result === false ){
			$error = $this->FetchError();
			unset($error['backtrace']);
			$this->_error = $error['message'];
		}
	}
	
	function PrintLog()
	{
		$this->p("![.bold[Display build log:]] ![.gray .small[".$this->GetCallerLine()."]]");
		
		$i = 0;
		foreach($this->_log as $log){
			$i++;
			$result	 = $log['result'];
			$message = $log['message'];
			if( is_null($result) ){
				$class = 'gray';
			}else if(is_bool($result)){
				$class = $result ? 'blue': 'red';
			}else{
				$class = $result;
			}
			print $this->Wiki2("![div .{$class}[{$i}: {$message}]]");
		}
		$this->_log = null;
	}
	
	function GetError()
	{
		return $this->_error;
	}
	
	function Root($password, $user='root')
	{
		$this->_user = $user;
		$this->_password = $password;
		$this->Log("user:$user, password:$password");
	}
	
	function Build($blueprint)
	{
		//	
		if(!$blueprint){
			$this->_error = 'Empty blue print.';
			return;
		}
		
		//	Database connection config.
		$database = $blueprint->config->database;
		$database->user     = $this->_user;
		$database->password = $this->_password;
		
		//	
		try{
			//	Database connection at root.
			$io = $this->PDO()->Connect($database);
			
			//	Log
			$user = $database->user;
			$message = "Database connection: user={$user}";
			$this->Log($message, $io);
			
			//	Error
			if(!$io){
				$this->SetError();
				return false;
			}
			
			//	Rebuild
			$this->CreateUser($blueprint);
			$this->CreateDatabase($blueprint);
			$this->CreateTable($blueprint);
			$this->CreateColumn($blueprint);
			$this->CreateIndex($blueprint);
			$this->CreateAlter($blueprint);
			$this->CreateGrant($blueprint);
			
		}catch( OpException $e ){
			$this->_error = $e->getMessage();
			return false;
		}
		
		return true;
	}

	function CreateUser($blueprint)
	{
		$io = true;
	
		foreach( $blueprint->user as $user ){
			
			$this->d($user);
			
			continue;
			
			//	execute
			$io = $this->PDO()->CreateUser($user);
			
			//	log
			$this->Log($this->PDO()->Qu(), $io);
		}
	
		return $io;
	}
	
	function CreateDatabase($blueprint)
	{
		foreach( $blueprint->database as $database ){
			//	execute
			$io = $this->PDO()->CreateDatabase($database);
			
			//	log
			$this->Log($this->PDO()->Qu(), $io);
		}
	}
	
	function CreateTable($blueprint)
	{
		
	}
	
	function CreateColumn($blueprint)
	{
		
	}
	
	function CreateIndex($blueprint)
	{
		
	}
	
	function CreateAlter($blueprint)
	{
	
	}
	
	function CreateGrant($blueprint)
	{
		$config = clone($blueprint->config->database);
		foreach( $blueprint->grant as $grant ){
			//	execute
			$io = $this->PDO()->Grant($grant);
			
			//	log
			$this->Log($this->PDO()->Qu(), $io);
		}
	}
}
