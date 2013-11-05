<?php
/**
 * Sample of how to make the model.
 *
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 *
 */
class Model_Account extends Model_Model
{
	private $_log = array();
	private $_status = null;
	
	/**
	 * @return Config_Account
	 */
	function Config($name='Config_Account')
	{
		return parent::Config($name);
	}
	
	function InitForm()
	{
		$config = $this->Config()->form_login();
		$this->form()->AddForm($config);
		return $this->Config()->form_name();
	}
	
	function GetAccountRecord( $id )
	{
		$config = $this->Config()->select();
		$config->where->account_id = $id;
		$config->limit = 1;
		$record = $this->pdo()->select($config);
		
		//	encrypt email
		$record['email'] = isset($record['account_enc']) ? $this->model('Blowfish')->Decrypt($record['account_enc']): null;
		
		return $record;
	}
	
	/**
	 * Auto authorizetion.
	 * 
	 * @return Boolean
	 */
	function Auto()
	{
		if(!$this->form()->Secure( $this->Config()->form_name() ) ){
		//	$this->Debug("Form5: " . $this->form()->getstatus( $this->Config()->form_name() ) );
			return false;
		}
		
		$form_name = $this->Config()->form_name();
		$account   = $this->form()->GetInputValue('account', $form_name);
		$password  = $this->form()->GetInputValue('password',$form_name);
		
		return $this->Auth( $account, $password );
	}
	
	/**
	 * Challenge authentication.
	 * 
	 * @param  string $account
	 * @param  string $password
	 * @return boolean|integer $account_id
	 */
	function Auth( $account=null, $password=null )
	{
		if( empty($account) or empty($password) ){
			$this->SetStatus("Empty id or password.");
			return false;
		}
		
		//	Convert
		$account  = md5($account);
		$password = md5($password);
		
		//	Check password from id.
		$select = $this->config()->select_auth( $account, $password );
		$record = $this->pdo()->select($select);
		
		//	login result
		$login = empty($record) ? false: true;
		
		if( $login ){
			$this->SetStatus('account and password is matched.');
		}else{
			$this->SetStatus('account and password is not matched.');
			
			//	update failed count
			$config = $this->Config()->update_failed($account, $login);
			$num = $this->pdo()->Update($config);
			return false;
		}
		
		//	Reset failed count.
		$config = $this->config()->update_failed_reset($account);
		if( $num = $this->pdo()->update($config) ){
			//	set status information
		//	$this->mark('Reset failed count','debug');
			$this->SetStatus("Reset failed count.");
			
			//	re select
			$select = $this->config()->select_auth( $account, $password );
			$record = $this->pdo()->select($select);
			$this->mark( $this->pdo()->qu() );
		}else{
			/*
			$this->mark("num: $num");
			$this->mark( $this->pdo()->qu() );
			$config->d();
			*/
		}
		
		//	Failed num
		$fail = $record[Config_Account::COLUMN_FAIL];
		
		//	limited of failed count
		$limit_count = $this->config()->limit_count();
		
		//	failed.
		if( $fail > $limit_count ){
		//	$this->d($record);
			$this->mark("Over the failed.($fail > $limit_count)",'debug');
			$this->SetStatus("Over the failed.");
			return false;
		}
		
		//	ID
		$id = $record[Config_Account::COLUMN_ID];
		$this->SetStatus("ID is $id");
		
		return $id;
	}
	
	function Debug( $log=null )
	{
		if( $log ){
			$this->_log[] = $log;
		}else{
			if( $this->admin() ){
				$call = $this->GetCallerLine();
				$this->p("Debug information ($call)",'div');
				Dump::d($this->_log);
			}
		}
	}
	
	function SetStatus( $status )
	{
		$this->_status = $status;
		$this->_log[]  = $status;
	}
	
	function GetStatus()
	{
		return $this->_status;
	}
}

class Config_Account extends Config_Model
{
	const TABLE_NAME   = 'account';
	const DB_USER_NAME = 'op_mdl_account';
	
	private $_form_name		 = 'model_account_login';
//	private $_table_prefix	 = 'op';
//	private $_table_name	 = 'account';
	private $_limit_second	 = 600; // ten minutes.
	private $_limit_count	 = 5; // failed.
	
	function SetTableName( $table_name )
	{
		$this->_table_name = $table_name;
	}
	
	function GetTableName( $label=null )
	{
		return $this->table_name();
	}
	
	/*
	function table_name( $key=null, $value=null )
	{
		return $this->_table_prefix.'_'.$this->_table_name;
	}
	*/
	
	function limit_date()
	{
		return $this->gmt(-$this->_limit_second);
	}
	
	function limit_count()
	{
		return $this->_limit_count;
	}
	
	function Database()
	{
		$args['user'] = self::DB_USER_NAME;
		$config = parent::Database($args);
		return $config;
	}
	
	function insert( $table_name=null )
	{
		$config = parent::insert( $this->table_name() );
		return $config;
	}
	
	function select( $table_name=null )
	{
		$config = parent::select( $this->table_name() );
		$config->limit = 1;
		return $config;
	}
	
	function select_auth( $account, $password )
	{
		$config = $this->select();
		$config->where->{self::COLUMN_MD5}		 = $account;
		$config->where->{self::COLUMN_PASSWORD}	 = $password;
		$config->cache = null;
		return $config;
	}
	
	/*
	function select_failed()
	{
		$config = $this->select();
		$config->where->{self::COLUMN_ID} = $id;
		$config->where->updated = '> '.$this->limit_date();
		
		return $config;
	}
	*/
	
	/**
	 * Reset login failed count.
	 * 
	 * @param  string $id
	 * @return Config
	 */
	function update_failed_reset( $account_md5 )
	{
		$config = parent::update();
	//	$config->set = null;
		$config->set->{self::COLUMN_FAIL} = 0;
		$config->where->{self::COLUMN_MD5} = $account_md5;
		$config->where->{self::COLUMN_FAILED} = '< '.$this->limit_date();
		return $config;
	}
	
	/**
	 * Increment or Reset failed count.
	 * 
	 * @param  string  $account_md5
	 * @param  boolean $login login success or fail
	 * @return Config
	 */
	function update_failed( $account_md5, $login )
	{
		$config = parent::update();
		$config->set = null;
		$config->where->{self::COLUMN_MD5} = $account_md5;
		if( $login ){
			$config->set->{self::COLUMN_FAIL}	 = 0;
		}else{
			$config->set->{self::COLUMN_FAIL}	 = 'INCREMENT(1)';
			$config->set->{self::COLUMN_FAILED}	 = $this->gmt();
		}
		return $config;
	}
	
	/*
	function update_success( $id )
	{
		$config = parent::update( $this->table_name() );
		$config->set->failed = null;
		return $config;
	}
	
	function update_failed( $id )
	{
		$config = parent::update( $this->table_name() );
		$config->set->failed = '+1';
		return $config;
	}
	*/
	
	function form_name( $key=null, $value=null )
	{
		return $this->_form_name;
	}
	
	function form_login()
	{
		$config = new Config();
		
		//	Form
		$config->name = $this->form_name();
		
		//	ID
		$name = 'account';
		$config->input->$name->type   = 'text';
		$config->input->$name->class  = 'op-input op-input-text mdl-account-account';
		$config->input->$name->cookie = true;
		$config->input->$name->validate->required	 = true;
		$config->input->$name->error->required		 = 'account is empty.';
		
		//	Password
		$name = 'password';
		$config->input->$name->type   = 'password';
		$config->input->$name->class  = 'op-input op-input-text op-input-password mdl-account-password';
		$config->input->$name->validate->required	 = true;
		$config->input->$name->error->required		 = 'password is empty.';
		
		//	Submit
		$name = 'submit';
		$config->input->$name->type   = 'submit';
		$config->input->$name->value  = ' Login ';
		$config->input->$name->class  = 'op-input op-input-button op-input-submit mdl-account-submit';
		
		return $config;
	}
	
	const COLUMN_ID			 = 'account_id';
	const COLUMN_MD5		 = 'account_md5';
	const COLUMN_ACCOUNT	 = 'account_enc';
	const COLUMN_PASSWORD	 = 'password_md5';
	const COLUMN_FAIL		 = 'fail';
	const COLUMN_FAILED		 = 'failed';
	
	function Selftest( $table_name=null )
	{
		$config = new Config();
		
		//	Form
		$config->form->title   = 'Wizard Magic';
		$config->form->message = 'Please enter root(or alter) password.';
		
		//	Database
		$config->database = $this->Database();
		
		//	table
		$table_name = $this->table_name();
		
		//	Column
		$name = self::COLUMN_ID;
		$config->table->$table_name->column->$name->type    = 'int';
		$config->table->$table_name->column->$name->ai      = true;

		$name = self::COLUMN_MD5;
		$config->table->$table_name->column->$name->type    = 'char';
		$config->table->$table_name->column->$name->length  = 32;
		$config->table->$table_name->column->$name->null    = null;
		$config->table->$table_name->column->$name->comment = 'MD5 hash';
		
		$name = self::COLUMN_ACCOUNT;
		$config->table->$table_name->column->$name->type    = 'text';
		$config->table->$table_name->column->$name->null    = null;
		$config->table->$table_name->column->$name->comment = 'Are encrypted.';
		
		$name = self::COLUMN_PASSWORD;
		$config->table->$table_name->column->$name->type    = 'char';
		$config->table->$table_name->column->$name->length  = 32;
		$config->table->$table_name->column->$name->null    = null;
		$config->table->$table_name->column->$name->comment = 'MD5 hash';

		$name = self::COLUMN_FAIL;
		$config->table->$table_name->column->$name->type	 = 'int';
		$config->table->$table_name->column->$name->null	 =  false;
		$config->table->$table_name->column->$name->default	 =  0;
		$config->table->$table_name->column->$name->comment	 = 'Count of failed';

		$name = self::COLUMN_FAILED;
		$config->table->$table_name->column->$name->type	 = 'datetime';
		$config->table->$table_name->column->$name->comment	 = 'Timestamp of failed';
		
		$name = 'created';
		$config->table->$table_name->column->$name->type	 = 'datetime';

		$name = 'updated';
		$config->table->$table_name->column->$name->type	 = 'datetime';

		$name = 'deleted';
		$config->table->$table_name->column->$name->type	 = 'datetime';

		$name = 'timestamp';
		$config->table->$table_name->column->$name->type	 = 'timestamp';
		
		return $config;
	}
}
