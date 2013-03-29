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
	const SESSION_KEY = 'loggedin-id';
	
	function SetLoginId($id)
	{
		if(!$id){
			$this->StackError('ID is empty. logout use $this->Logout()');
			return false;
		}
		$io = $this->SetSession( self::SESSION_KEY, $id );
		
		return $io ? true: false;
	}
	
	function GetLoginId()
	{
		$id = $this->GetSession( self::SESSION_KEY );
		return $id;
	}
	
	function Logout()
	{
		$io = $this->SetSession( self::SESSION_KEY, null );
		return $io ? true: false;
	}
	
	function isLoggedin()
	{
		$id = $this->GetSession( self::SESSION_KEY );
		return $id ? true: false;
	}
}
