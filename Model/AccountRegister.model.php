<?php

class Model_AccountRegister extends Model_Model
{
	function Config( $var='AccountRegisterConfig' )
	{
		return parent::Config($var);
	}
	
	function Create( $account, $password )
	{
		//	Get table name
		$table_name = $this->model('Account')->Config()->GetTableName();
		
		//	Get insert config
		$insert = $this->Config()->insert($table_name);
		$insert->set->account  = $account;
		$insert->set->password = $password;
		
		//	Execute insert
		$id = $this->pdo()->Insert($insert);
		
		//	return id
		return $id;
	}
	
	function Selftest()
	{
		return $this->Config()->Selftest();
	}
}

class AccountRegisterConfig extends ConfigModel
{
	static function Database()
	{
		$config = parent::Database();
		$config->user = 'op_mdl_register';
		return $config;
	}
	
	function Selftest()
	{
		var_dump( $this->model('Account')->Config() );
		
		$config = $this	->model('Account')
						->Config()
						->Selftest();
		$config->database = $this->Database();
		return $config;
	}
}
