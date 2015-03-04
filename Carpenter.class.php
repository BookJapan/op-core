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
	
	function Init()
	{
		parent::Init();
		if(!$this->Admin()){
			$this->StackError("Not admin call.");
		}
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
			if(!$this->PDO()->Connect($this->_blueprint->database)){
				$this->FetchError();
				return false;
			}
			
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
			$args['host'] = $user['host'];
			$args['user'] = $user['user'];
			$args['password'] = $user['password'];
			if(!$io = $this->PDO()->CreateUser($args)){
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
