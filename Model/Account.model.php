<?php
/**
 * Account.model.php
 *
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright Copyright &copy; 2014 Tomoaki Nagahara
 * @version   1.0
 * @package   op-core
 */

/**
 * Model_Account
 * 
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright Copyright &copy; 2014 Tomoaki Nagahara
 * @version   1.0
 * @package   op-core
 */
class Model_Account extends Model_Model
{
	private $_log = array();
	private $_message = null;
	private $_success = null;
	
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
	
	function GetAccountRecord( $id, $keyword=null )
	{
		$config = $this->Config()->select();
		$config->where->account_id = $id;
		$config->limit = 1;
		$record = $this->pdo()->select($config);
		
		//	encrypt email
		$temp = array_slice($record,0,1);
		$temp['email'] = isset($record['account_enc']) ? 
							$this->model('Blowfish')->Decrypt($record['account_enc'],$keyword): 
							null;
		$record = array_merge( $temp, $record );
		
		return $record;
	}
	
	/**
	 * Auto authorizetion.
	 * 
	 * @return Boolean
	 */
	function Auto()
	{
		$this->InitForm();
		$form_name = $this->Config()->form_name();
		
		if(!$this->form()->Secure( $form_name ) ){
		//	$this->mark($this->form()->getstatus( $form_name ),'debug');
			return false;
		}
		
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
			$this->SetMessage("Empty id or password.");
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
			$this->_success = true;
			$this->SetMessage( Config_Account::GetConst('auth_success') );
		}else{
			$this->_success = false;
			$this->SetMessage( Config_Account::GetConst('auth_fail') );
			
			//	update failed count
			$config = $this->Config()->update_failed($account, $login);
			$num = $this->pdo()->Update($config);
			return false;
		}

		//	Failed num
		$fail = $record[Config_Account::COLUMN_FAIL];
		
		//	limited of failed count
		$limit_count = $this->config()->limit_count();
			
		//	failed.
		if( $fail > $limit_count ){
			$this->_success = false;
			$this->SetMessage( Config_Account::GetConst('auth_fail_limit') );
			$this->mark("Over the failed.($fail > $limit_count)",'debug');
			return false;
		}
		
		//	Reset failed count.
		if( $fail ){
			$config = $this->config()->update_failed_reset($account);
			$num = $this->pdo()->update($config);
			$this->mark( $this->pdo()->qu(),'debug');
			$this->SetMessage( Config_Account::GetConst('auth_fail_reset') );
			
			//	re select
			$select = $this->config()->select_auth( $account, $password );
			$record = $this->pdo()->select($select);
		}
		
		//	ID
		$id = $record[Config_Account::COLUMN_ID];
		
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
	
	function SetMessage( $status )
	{
		$this->_message = $status;
		$this->_log[]  = $status;
	}
	
	function GetMessage()
	{
		return $this->_message;
	}
	
	function ShowRecord( $limit=10, $offset=0, $order='account_id')
	{
		$select = $this->Config()->select_record( $limit, $offset, $order );
		$record = $this->pdo()->Select($select);
		foreach($record as $temp){
			$keyword = $this->form()->GetInputValue('keyword',Config_Account::GetConst('form_name'));
			$temp['decrypt'] = $this->model('Blowfish')->Decrypt($temp['account_enc'], $keyword);
			dump::d($temp);
		}
	}
	
	const STATUS_ACCOUNT_EXISTS = 'Account is exists.';
	const STATUS_ACCOUNT_CREATE = 'Account was created.';
	const STATUS_ACCOUNT_FAILED = 'Create account was failed.';
	
	function Create( $account, $password )
	{
		//	Get table name
		$table_name = $this->model('Account')->Config()->GetTableName();
	
		//	Check unique account.
		$select = $this->Config()->select($table_name);
		$select->where->account_md5 = md5($account);
		$num = $this->pdo()->count($select);
		if( $num ){
			$this->_message = Config_Account::GetConst('create_exists');
			return false;
		}
		
		//	Get insert config
		$insert = $this->Config()->insert($table_name);
		$insert->set->account_enc  = $this->model('Blowfish')->Encrypt($account);
		$insert->set->account_md5  = md5($account);
		$insert->set->password_md5 = md5($password);
		
		//	Execute insert
		$id = $this->pdo()->Insert($insert);
		$this->_success = $id ? true: false;
		$this->_message = $id ? Config_Account::GetConst('create_success'): Config_Account::GetConst('create_fail'); 
		
		//	return id
		return $id;
	}
	
	function isSuccess()
	{
		return $this->_success;
	}
}

class Config_Account extends Config_Model
{
	const TABLE_NAME   = 'account';
	const DB_USER_NAME = 'op_mdl_account';
	
	private $_form_name		 = 'model_account_login';
	private $_limit_second	 = 600; // ten minutes.
	private $_limit_count	 = 5; // failed.
	
	static function GetConst($key)
	{
		switch(strtolower($key)){
			
			case 'form_name':
				$var = 'model_account_login';
				break;
				
			case 'account':
			case 'form_input_account':
				$var = 'account';
				break;
			case 'password':
			case 'form_input_password':
				$var = 'password';
				break;
			case 'keyword':
				$var = 'keyword';
				break;
			case 'submit':
			case 'form_input_submit':
				$var = 'submit';
				break;
			
			case 'auth_success':
				$var = 'authorization is success.';
				break;
			case 'auth_fail':
				$var = 'authorization is fail.';
				break;
			case 'auth_fail_limit':
				$var = 'fail has exceeded the limit.';
				break;
			case 'auth_fail_reset':
				$var = 'reset the fail count.';
				break;
				
			case 'create_success':
			case 'create_successful':
				$var = 'create account is success.';
				break;
			case 'create_fail':
			case 'create_faild':
				$var = 'create account is failed.';
				break;
			case 'create_exists':
				$var = 'account was exists.';
				break;
			
			default:
				OnePiece5::Mark("Does not define constant. ($key)");
				$var = $key;
		}
		return $var;
	}
	
	function SetTableName( $table_name )
	{
		$this->_table_name = $table_name;
	}
	
	function GetTableName( $label=null )
	{
		return $this->table_name();
	}
	
	function GetDatabasePassword($config)
	{
		/*******************************************************************
		 * モデルが変わっても、同じユーザー名の場合は、同じパスワードが使えなければならない。
		 * ホスト.データベース毎に変わる。
		 */
		$host = $config->host;
		$user = $config->user;
		$database = $config->database;
		return md5("{$host}.{$database},{$user}");
	}
	
	function limit_date()
	{
		return $this->gmt(-$this->_limit_second);
	}
	
	function limit_count()
	{
		return $this->_limit_count;
	}
	
	function Database( $args=null )
	{
		$args['user'] = self::DB_USER_NAME;
		$config = parent::Database($args);
		$config->password = self::GetDatabasePassword($config);
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
	
	function select_record( $limit=10, $offset=0, $order='account_id' )
	{
		$select = $this->select();
		$select->column = 'account_id, account_enc, account_md5, password_md5';
		$select->limit  = $limit;
		$select->offset = $offset;
		$select->order  = $order;
		return $select;
	}
	
	function select_auth( $account, $password )
	{
		$config = $this->select();
		$config->where->{self::COLUMN_MD5}		 = $account;
		$config->where->{self::COLUMN_PASSWORD}	 = $password;
		$config->cache = null;
		return $config;
	}

	function select_account( $account )
	{
		$config = $this->select();
		$config->where->{self::COLUMN_MD5}		 = $account;
	//	$config->cache = null;
		return $config;
	}
	
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
		$config->where->{self::COLUMN_MD5} = $account_md5;
		
		unset($config->set);
		
		if( $login ){
			$config->set->{self::COLUMN_FAIL}	 = 0;
		}else{
			$config->set->{self::COLUMN_FAIL}	 = 'INCREMENT(1)';
			$config->set->{self::COLUMN_FAILED}	 = $this->gmt();
		}
		return $config;
	}
	
	function form_name( $key=null, $value=null )
	{
		return $this->_form_name;
	}
	
	const _FORM_INPUT_ACCOUNT_  = 'account';
	const _FORM_INPUT_PASSWORD_ = 'password';
	const _FORM_INPUT_SUBMIT_   = 'submit'; 
	
	function form_login()
	{
		$config = new Config();
		
		//	Form
		$config->name = $this->form_name();
		
		//	ID
		$name = self::GetConst('account');
		$config->input->$name->type   = 'text';
		$config->input->$name->class  = 'op-input op-input-text mdl-account-account';
		$config->input->$name->cookie = true;
		$config->input->$name->validate->required	 = true;
		$config->input->$name->error->required		 = 'account is empty.';
		
		//	Password
		$name = self::GetConst('password');
		$config->input->$name->type   = 'password';
		$config->input->$name->class  = 'op-input op-input-text op-input-password mdl-account-password';
		$config->input->$name->validate->required	 = true;
		$config->input->$name->error->required		 = 'password is empty.';

		//	Password(confirm)
		/*
		$name = self::GetConst('confirm');
		$config->input->$name->type   = 'password';
		$config->input->$name->class  = 'op-input op-input-text op-input-password mdl-account-password';
		$config->input->$name->validate->required	 = true;
		$config->input->$name->error->required		 = 'password(confirm) is empty.';
		$config->input->$name->validate->compare	 = self::GetConst('password');
		$config->input->$name->error->compare		 = 'Does not match password. ($value)';
		*/

		//	Encrypt / Decrypt Keyword.
		$name = self::GetConst('keyword');
		$config->input->$name->value  = $this->Model('Blowfish')->GetEncryptKeyword();
		$config->input->$name->class  = 'op-input op-input-button op-input-submit mdl-account-submit';
		
		//	Submit
		$name = self::GetConst('submit');
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
		$config->table->$table_name->column->$name->comment = 'MD5 hash of account value. (use search)';
		
		$name = self::COLUMN_ACCOUNT;
		$config->table->$table_name->column->$name->type    = 'text';
		$config->table->$table_name->column->$name->null    = null;
		$config->table->$table_name->column->$name->comment = 'Are encrypted. (can decrypt)';
		
		$name = self::COLUMN_PASSWORD;
		$config->table->$table_name->column->$name->type    = 'char';
		$config->table->$table_name->column->$name->length  = 32;
		$config->table->$table_name->column->$name->null    = null;
		$config->table->$table_name->column->$name->comment = 'MD5 hash. (can not decrypt)';
		
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
