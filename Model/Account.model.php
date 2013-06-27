<?php
/**
 * Sample of how to make the model.
 *
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 *
 */
class Model_Account extends Model_Model
{
	private $log = array();
	
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
		return AccountConfig::FORM_NAME;
	}
	
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
	
	/**
	 * Auto authorizetion.
	 * 
	 * @return unknown
	 */
	function Auto()
	{
		//	Selftest
		if( $this->Admin() ){
			$wz = new Wizard();
			$wz->Selftest( $this->Config()->Selftest() );
		}
				
		if(!$this->form()->Secure( AccountConfig::FORM_NAME ) ){
			$this->Debug("Does not secure.");
			return false;
		}
		
		$form_name = AccountConfig::FORM_NAME;
		$id       = $this->form()->GetInputValue('id',$form_name);
		$password = $this->form()->GetInputValue('password',$form_name);
		
		return $this->Auth( $id, $password );
	}
	
	function Auth( $id=null, $pw=null )
	{
		if( empty($id) or empty($pw) ){
			$this->Debug("Empty id or password.");
			return false;
		}
		
		/*
		if(!$io = $this->pdo()->Connect( $this->Config()->Database() )){
			$this->Selftest();
		}
		*/
		
		//	Reset.
		$config = $this->config()->update_reset($id);
		$this->pdo()->update($config);
		
		//	
		$config = $this->config()->select_auth( $id, $pw );
		$record = $this->pdo()->select($config);
		
		//	
		$count = isset($record['failed']) ? $record['failed']: 100;
		
		//	
		$limit = $this->config()->limit_count();
		
		//	
		$io = $count < $limit ? true: false;
		
		//	failed process.
		if(!$io){
			//	record of failed times.
			$config = $this->config()->update_failed( $id );
		}
		
		return $io;
	}
	
	function Selftest()
	{
		$wz = new Wizard();
		$wz->Selftest( $this->Config()->Selftest() );
		
		return;
		/*
		$config = $this->Config()->Selftest();
		
		//	Throw Wizard Exception
		$e = new OpException('WIZARD');
		$e->SetWizard($config);
		throw new $e;
		*/
	}
	
	function Debug( $log=null )
	{
		if( $log ){
			$this->log[] = $log;
		}else{
			if( $this->admin() ){
				$this->p('Debug information','div');
				Dump::d($log);
			}
		}
	}
}

class AccountConfig extends ConfigModel
{
	const FORM_NAME = 'model_account_login';
	
	private $table_prefix = 'op';
	private $table_name   = 'account';
	private $limit_time   = 600; // ten minutes.
	private $limit_count  = 10; // failed.
	
	function table_name( $key=null, $value=null )
	{
		return $this->table_prefix.'_'.$this->table_name;
	}
	
	function limit_date()
	{
		$gmtime = time() + date('Z') + $this->limit_time;
		$gmdate = gmdate('Y-m-d H:i:s',$gmtime);
		return $gmdate;
	}
	
	function limit_count()
	{
		return $this->limit_count;
	}
	
	function GetDatabaseConfig()
	{
		$config = parent::GetDatabaseConfig();
		$config->user = 'op_model_account';
		return $config;
	}
	
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
	
	function select_auth( $id, $pw )
	{
		$config = $this->select();
		$config->where->id = $id;
		$config->where->password = $pw;
		return $config;
	}
	
	function select_failed()
	{
		$gmdate = $this->limit_date();
		
		$config = $this->select();
		$config->where->id = $id;
		$config->where->updated = '> $gmdate';
		
		return $config;
	}
	
	/**
	 * Reset login failed count.
	 * 
	 * @param  string $id
	 * @return Config
	 */
	function update_reset( $id )
	{
		$gmdate = $this->limit_date();
		
		$config = parent::update( $this->table_name() );
		$config->set->failed = null;
		$config->where->updated = '< $gmdate';
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
	
	function form_login()
	{
		$config = new Config();
		
		//	Form
		$config->name = self::FORM_NAME;
		
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
	
	function Selftest()
	{
		//	Base config
		$config = parent::Selftest();
		
		//	Table name
		$table_name = $this->table_name();
		
		//	Column
		$name = 'account_id';
		$config->table->$table_name->column->$name->type = 'int';
		$config->table->$table_name->column->$name->ai   = true;

		$name = 'account';
		$config->table->$table_name->column->$name->type = 'text';
		$config->table->$table_name->column->$name->comment = 'Are encrypted.';
		
		$name = 'password';
		$config->table->$table_name->column->$name->type = 'varchar';
		$config->table->$table_name->column->$name->length = 32;
		$config->table->$table_name->column->$name->comment = 'MD5 hash';
		
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

