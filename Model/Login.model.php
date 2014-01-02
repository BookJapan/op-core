<?php
/**
 * Manages the login status.
 * Only do it.
 * 
 * ex.
 * if( Model_Login::isLoggedin() ){
 *   //  Already logged in.
 *   $id = Model_Login::GetLoginId();
 * }else{
 *   //  Does not login.
 *   if( DO_ACCOUNT_CHECK($id,$pass) ){
 *     Model_Login::SetLoginId($id);
 *   }
 * }
 * 
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 *
 */
class Model_Login extends Model_Model
{
	const SESSION_LOGIN_ID         = 'loggedin-id';
	const SESSION_FIRST_LOGIN_TIME = 'first-login-time';
	const COOKIE_LAST_ACCESS_TIME  = 'last-access-time';
	
	const MESSAGE_EXPIRE_SESSION = 'Session has expire. (Session has destroyed)';
	const MESSAGE_EXPIRE_LAST    = 'Login has expire. (From the last access)';
	const MESSAGE_EXPIRE_FIRST   = 'Login has expire. (From the first login)';
	
	private $_message = null;
	private $_expire  = 1200;  // 60 sec * 20 min
	private $_effect  = 10800; // 60 sec * 60 min * 3 hour
	
	function Init()
	{
		parent::Init();
		
		//	check expire
	//	$this->mark( $this->GetCookie(self::COOKIE_KEY) );
	//	$this->mark( time()-$this->_expire );
		if( $this->GetCookie(self::COOKIE_LAST_ACCESS_TIME) > (time() - $this->_expire) ){
		//	$this->mark('OK');
		}else{
		//	$this->mark('NG');
			$this->_message = self::MESSAGE_EXPIRE_LAST;
			$this->Logout();
		}
		$this->SetCookie(self::COOKIE_LAST_ACCESS_TIME, time(), 0);
	}
	
	/**
	 * Set login-ID
	 * 
	 * @param  string|number $id
	 * @return boolean
	 */
	function SetLoginId($id)
	{
		//	Check
		if(!$id){
			$this->StackError('ID is empty. logout use $this->Logout()');
			return false;
		}
		
		//	Set first login time
		if( $_id = $this->GetSession(self::SESSION_LOGIN_ID) ){
			//	
		}else{
			if( $_id != $id ){
				$this->SetSession( self::SESSION_FIRST_LOGIN_TIME, time() );
			}
		}
		
		//	Set login-ID to session
		$io = $this->SetSession( self::SESSION_LOGIN_ID, $id );
		
		return $io ? true: false;
	}
	
	function ID()
	{
		return $this->GetLoginID();
	}
	
	/**
	 * Get login-ID
	 * 
	 * @return string|number
	 */
	function GetLoginId()
	{
		//	Get login-ID from session
		$id = $this->GetSession( self::SESSION_LOGIN_ID );
		
		//	If empty
		if( $id ){
			//	Check login effective
		//	$this->mark( $this->GetSession(self::SESSION_FIRST_LOGIN_TIME) );
		//	$this->mark( time() - $this->_effect );
			if( $this->GetSession(self::SESSION_FIRST_LOGIN_TIME) < (time() - $this->_effect) ){
				$id = null;
				$this->_message = self::MESSAGE_EXPIRE_FIRST;
			}
		}else{
			//	Will session has destroy?
			if( $this->GetCookie(self::COOKIE_LAST_ACCESS_TIME) ){
				$this->_message = self::MESSAGE_EXPIRE_SESSION;
			}
		}
		
		return $id;
	}
	
	/**
	 * To logout
	 */
	function Logout()
	{
		$this->SetSession( self::SESSION_LOGIN_ID, null );
		return true;
	}
	
	/**
	 * Return to boolean
	 * 
	 * @return boolean
	 */
	function isLoggedin()
	{
		return $this->GetLoginId() ? true: false;
	}
	
	/**
	 * Get status message
	 * 
	 * @return string
	 */
	function GetMessage()
	{
		return $this->_message;
	}
}
