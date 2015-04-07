<?php
/**
 * DoctorX.class.php
 * 
 * Creation: 2015-03-02
 * 
 * @version   1.0
 * @package   op-core
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2015 (C) Tomoaki Nagahara All right reserved.
 */

/**
 * DoctorX
 * 
 * Creation: 2015-03-02
 *
 * @version   1.0
 * @package   op-core
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2015 (C) Tomoaki Nagahara All right reserved.
 */
class DoctorX extends OnePiece5
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
		$this->_error = array();
	}
	
	function Debug()
	{
		$this->d($this->_log);
	}
	
	function Log($message, $result=null)
	{
		if( $message ){
			$log['call']	 = $this->GetCallerLine();
			$log['result']	 = $result;
			$log['message']	 = $message;
			$this->_log[]	 = $log;
		}
		
		if( $result === false ){
			$error = $this->FetchError();
			$error['call'] = $this->GetCallerLine();
			unset($error['backtrace']);
			$this->_error[] = $error;
		}
	}
	
	function PrintLog()
	{
		$this->p("![.bold[Display Operation build log:]] ![.gray .small[".$this->GetCallerLine()."]]");
		
		if(!$this->_log){
			$this->p("![ margin-left:0.6em .gray [Nothing.]]");
			return;
		}
		
		print '<ol>';
		foreach($this->_log as $log){
			$result	 = $log['result'];
			$message = $log['message'];
			if( is_null($result) ){
				$class = 'gray';
			}else if(is_bool($result)){
				$class = $result ? 'blue': 'red';
			}else{
				$class = $result;
			}
			print $this->p("![li .{$class} .small[$message]]");
		}
		print '</ol>';
		
		$this->_log = null;
	}
	
	function PrintError()
	{
		$this->p("![.bold[Display Operation error log:]] ![.gray .small[".$this->GetCallerLine()."]]");
		
		if( empty($this->_error) ){
			$this->p("![margin:0.7em .gray[Nothing]]");
			return;
		}
		
		$nl = "\n";
		print '<ol>';
		foreach($this->_error as $error){
			$from	 = $error['translation'];
			$temp	 = explode($nl, $error['message'].$nl);
			$message = $this->i18n()->Bulk($temp[0], $from);
			$query = trim($temp[1],'\\');
			print $this->p("![li .small[{$message}{$nl} ![.gray[{$query}]] ]]");
		}
		print '</ol>';
		
		$this->_error = null;
	}
	
	function Root($user, $password)
	{
		$this->_user	 = $user;
		$this->_password = $password;
		$this->Log("user:$user, password:$password");
	}
	
	function Build($blueprint)
	{
		//	
		if(!$blueprint){
			return;
		}
		
		if(!$this->_user){
			return;
		}
		
		if(!$this->_password){
			return;
		}
		
		//	Database connection config.
		$database = $blueprint->config->database;
		$database->user     = $this->_user;
		$database->password = $this->_password;
		
		try{
			//	Database connection at root.
			if(!$io = $this->PDO()->Connect($database)){
				return false;
			}
			
			//	Log
			$user = $database->user;
			$message = "Database connection: user={$user}";
			$this->Log($message, $io);
			
			//	Rebuild
			$this->CreateUser($blueprint);
			$this->CreateDatabase($blueprint);
			$this->CreateTable($blueprint);
			$this->CreatePkeyDrop($blueprint);
			$this->CreateAlter($blueprint);
			$this->CreatePkeyAdd($blueprint);
			$this->CreateIndex($blueprint);
			$this->CreateAI($blueprint);
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
		foreach( $blueprint->table as $table ){
			
			if( isset($table->rename) ){
				//	Rename table
				$io = $this->PDO()->RenameTable($table);
			}else{
				//	Create table
				$io = $this->PDO()->CreateTable($table);
			}
			
			//	log
			$this->Log($this->PDO()->Qu(), $io);
		}
	}
	
	function CreateAlter($blueprint)
	{
		foreach( $blueprint->alter as $alter ){
			
			//	Alter table
			$io = $this->PDO()->AlterTable($alter);
			
			//	log
			$this->Log($this->PDO()->Qu(), $io);
		}
	}
	
	function CreateIndex($blueprint)
	{
		//	Drop
		foreach($blueprint->index->drop as $alter){
			$io = $this->PDO()->DropIndex($alter);
			//	log
			$this->Log($this->PDO()->Qu(), $io);
		}
		
		//	Add
		foreach($blueprint->index->add as $alter){
			$io = $this->PDO()->AddIndex($alter);
			//	log
			$this->Log($this->PDO()->Qu(), $io);
		}
	}

	function CreatePkeyAdd($blueprint)
	{
		foreach($blueprint->pkey->add as $alter){
			$io = $this->PDO()->AddPrimaryKey($alter);
			//	log
			$this->Log($this->PDO()->Qu(), $io);
		}
	}
	
	function CreatePkeyDrop($blueprint)
	{
		foreach($blueprint->pkey->drop as $alter){
			$io = $this->PDO()->DropPrimarykey($alter);
			//	log
			$this->Log($this->PDO()->Qu(), $io);
		}
	}
	
	function CreateAI($blueprint)
	{
		//	Alter table by each column
		foreach($blueprint->ai as $alter){
			$io = $this->PDO()->AlterTable($alter);
			//	log
			$this->Log($this->PDO()->Qu(), $io);
		}
	}
	
	function CreateGrant($blueprint)
	{
		//	Grant user
		foreach( $blueprint->grant as $grant ){
			$io = $this->PDO()->Grant($grant);
			//	log
			$this->Log($this->PDO()->Qu(), $io);
		}
	}
}
