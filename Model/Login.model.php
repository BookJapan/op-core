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
		$this->SetSession( self::SESSION_KEY, null );
		return true;
	}
	
	function isLoggedin()
	{
		$id = $this->GetSession( self::SESSION_KEY );
		return $id ? true: false;
	}
	
	function GetLoginFormConfig()
	{
		$config = new Config();
		
		//	Form
		$config->name = 'model_login';
		
		//	ID
		$name = 'id';
		$config->input->$name->type = 'text';
		$config->input->$name->validate->required = true;
		
		//	Password
		$name = 'password';
		$config->input->$name->type = 'password';
		$config->input->$name->validate->required = true;
		
		//	Submit
		$name = 'submit';
		$config->input->$name->type  = 'submit';
		$config->input->$name->value = ' Login ';
		
		return $config;
	}
}
