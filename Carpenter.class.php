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
		$this->_log[] = $log;
	}
	
	function PrintLog()
	{
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
	
	function FetchError()
	{
		$error = parent::FetchError();
		$this->_error = $error['message'];
	}
	
	function GetError()
	{
		return $this->_error;
	}
	
	function Root($password, $user='root')
	{
		$this->_user = $user;
		$this->_password = $password;
	}
	
	function Build($blueprint)
	{
		//	
		if(!$blueprint){
			$this->_error = 'Empty blue print.';
			return;
		}
		
		//	
		$this->_blueprint = $blueprint;
		$this->_blueprint->database->user     = $this->_user;
		$this->_blueprint->database->password = $this->_password;
		
		//	
		try{
			//	Database connection at root.
			$io = $this->PDO()->Connect($this->_blueprint->database);
			//	Log
			$user = $this->_blueprint->database->user;
			$message = "Database connection: user={$user}";
			$this->Log($message, $io);
			//	Error
			if(!$io){
				$this->FetchError();
				return false;
			}
			//	Rebuild
			$this->CreateUser();
			
		}catch( OpException $e ){
			$this->_error = $e->getMessage();
			return false;
		}
		
		return true;
	}
	
	function CreateDatabase()
	{
		
	}
	
	function CreateTable()
	{
		
	}
	
	function CreateColumn()
	{
		
	}
	
	function CreateIndex()
	{
		
	}
	
	function CreateUser()
	{
		foreach( $this->_blueprint->user as $user ){
			$io = $this->PDO()->CreateUser($user);
			$this->Log($this->PDO()->Qu(), $io);
			
			if(!$io){
				$this->FetchError();
				break;
			}
		}
		
		return $io;
	}
	
	function CreateAlter()
	{
		
	}
}
