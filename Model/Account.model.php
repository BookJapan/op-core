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
	 * @return AccountConfig
	 */
	function Config($name='AccountConfig')
	{
		return parent::Config($name);
	}
	
	function Init()
	{
		parent::Init();
		$this->Config('AccountConfig');
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
		$record['email'] = $this->model('Blowfish')->Decrypt($record['account_enc']);
		return $record;
	}
	
	/*
	function Insert($config)
	{
		return $this->pdo()->insert($config);
	}
	
	function Select($config)
	{
		return $this->pdo()->select($config);
	}
	
	function Update($config)
	{
		return $this->pdo()->update($config);
	}
	
	function Delete($config)
	{
		return $this->pdo()->delete($config);
	}
	*/
	
	/**
	 * Auto authorizetion.
	 * 
	 * @return unknown
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
	
	function Auth( $account=null, $password=null )
	{
		if( empty($account) or empty($password) ){
			$this->SetStatus("Empty id or password.");
			return false;
		}
		
		//	Convert
		$account  = md5($account);
		$password = md5($password);
		
		//	Reset.
		/*
		$config = $this->config()->update_failed_reset();
		if( $this->pdo()->update($config) ){
			$this->Debug('Reset failed count');
		}
		*/
		
		//	Check password from id.
		$config = $this->config()->select_auth( $account, $password );
		$record = $this->pdo()->select($config);
		if( is_array($record) and count($record) ){
			$this->SetStatus('Match password from account.');
		}else{
			$this->SetStatus('Does not match password from account.');
			return false;
		}
		
		//	Failed num
		$failed = isset($record['failed']) ? $record['failed']: 0;
		
		//	Permit failed limit.
		$limit = $this->config()->limit_count();
		
		//	Check
		$io = $failed < $limit ? true: false;
		
		//	failed.
		if(!$io){
			$this->SetStatus("Over the failed.($failed < $limit)");
			return false;
		}
		
		//	ID
		$id = $record[AccountConfig::COLUMN_ID];
		$this->SetStatus("ID is $id");
		
		return $id;
	}
	
	/*
	function Selftest()
	{
		if( method_exists($this->Config(), 'Selftest') ){
			$wz = new Wizard();
			$io = $wz->Selftest( $this->Config()->Selftest() );
		}else{
			$io = null;
		}
		return $io;
	}
	*/
	
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

class AccountConfig extends ConfigModel
{
	private $_form_name		 = 'model_account_login';
	private $_table_prefix	 = 'op';
	private $_table_name	 = 'account';
	private $_limit_time	 = 600; // ten minutes.
	private $_limit_count	 = 10; // failed.
	
	function SetTableName( $table_name )
	{
		$this->_table_name = $table_name;
	}
	
	function GetTableName( $label=null )
	{
		return $this->table_name();
	}
	
	function table_name( $key=null, $value=null )
	{
		return $this->_table_prefix.'_'.$this->_table_name;
	}
	
	function limit_date()
	{
		$gmtime = time() + date('Z') + $this->_limit_time;
		$gmdate = gmdate('Y-m-d H:i:s',$gmtime);
		return $gmdate;
	}
	
	function limit_count()
	{
		return $this->_limit_count;
	}
	
	/*
	function GetDatabaseConfig()
	{
		$config = parent::GetDatabaseConfig();
		$config->user = 'op_model_account';
		return $config;
	}
	*/
	
	static function Database()
	{
		$config = parent::Database();
		$config->user     = 'op_model_account';
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
		return $config;
	}
	
	function select_failed()
	{
		$config = $this->select();
		$config->where->{self::COLUMN_ID} = $id;
		$config->where->updated = '> '.$this->limit_date();
		
		return $config;
	}
	
	/**
	 * Reset login failed count.
	 * 
	 * @param  string $id
	 * @return Config
	 */
	function update_failed_reset( $id )
	{
		$config = parent::update( $this->table_name() );
		$config->set->failed = null;
		$config->where->{self::COLUMN_ID} = $id;
	//	$config->where->updated = '< '.$this->limit_date();
		return $config;
	}
	
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
		$config->input->$name->validate->required = true;
		
		//	Password
		$name = 'password';
		$config->input->$name->type   = 'password';
		$config->input->$name->class  = 'op-input op-input-text op-input-password mdl-account-password';
		$config->input->$name->validate->required = true;
		
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
		$config->table->$table_name->column->$name->comment = 'MD5 hash';
		$config->table->$table_name->column->$name->null    = null;
		
		$name = self::COLUMN_ACCOUNT;
		$config->table->$table_name->column->$name->type    = 'text';
		$config->table->$table_name->column->$name->comment = 'Are encrypted.';
		$config->table->$table_name->column->$name->null    = null;
		
		$name = self::COLUMN_PASSWORD;
		$config->table->$table_name->column->$name->type    = 'char';
		$config->table->$table_name->column->$name->length  = 32;
		$config->table->$table_name->column->$name->comment = 'MD5 hash';
		$config->table->$table_name->column->$name->null    = null;

		$name = self::COLUMN_FAILED;
		$config->table->$table_name->column->$name->type    = 'int';
		
		$name = 'created';
		$config->table->$table_name->column->$name->type = 'datetime';

		$name = 'updated';
		$config->table->$table_name->column->$name->type = 'datetime';

		$name = 'deleted';
		$config->table->$table_name->column->$name->type = 'datetime';

		$name = 'timestamp';
		$config->table->$table_name->column->$name->type = 'timestamp';
		
		return $config;
	}
}
