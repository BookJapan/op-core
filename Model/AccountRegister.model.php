<?php

class Model_AccountRegister extends Model_Model
{
	const STATUS_ACCOUNT_EXISTS = 'Account is exists.';
	const STATUS_ACCOUNT_CREATE = 'Account was created.';
	const STATUS_ACCOUNT_FAILED = 'Create account was failed.';
	
	private $_status = null;
	
	function Config( $var='AccountRegisterConfig' )
	{
		return parent::Config($var);
	}
	
	function Create( $account, $password )
	{
		//	Get table name
		$table_name = $this->model('Account')->Config()->GetTableName();
		
		//	Check unique account.
		$select = $this->Config()->select($table_name);
		$select->where->account_md5 = md5($account);
		$num = $this->pdo()->count($select);
		if( $num ){
			$this->_status = self::STATUS_ACCOUNT_EXISTS;
			return false;
		}
		
		//	Get insert config
		$insert = $this->Config()->insert($table_name);
		$insert->set->account_enc  = $this->model('Blowfish')->Encrypt($account);
		$insert->set->account_md5  = md5($account);
		$insert->set->password_md5 = md5($password);
		
		//	Execute insert
		$id = $this->pdo()->Insert($insert);
		if( $id ){
			$this->_status = self::STATUS_ACCOUNT_CREATE;
		}else{
			$this->_status = self::STATUS_ACCOUNT_FAILED;
		}
		
		//	return id
		return $id;
	}
	
	function GetStatus()
	{
		return $this->_status;
	}
}

class AccountRegisterConfig extends ConfigModel
{
	static function Database()
	{
		$config = parent::Database();
		$config->user = 'op_model_account';
		return $config;
	}
}
