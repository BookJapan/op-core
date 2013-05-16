<?php
/**
 * 
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * 
 */
class Wizard extends OnePiece5
{
	private $config = null;
	
	/**
	 * @return WizardConfig
	 */
	function Config()
	{
		if(!$this->config){
			$this->config = new WizardConfig();
		}
		return $this->config;
	}
	
	function Selftest( Config $config )
	{
		if(!$this->admin()){
			return;
		}
		$this->p( 'Call: ' . $this->GetCallerLine() );
		
		//  Start
		$this->model('Log')->Set("START: Selftest.");
		
		if(!$this->pdo()->Connect($config->database) ){
			$dns = $config->database->user.'@'.$config->database->host;
			$this->model('Log')->Set("FAILED: Database connect is failed.($dns)",false);
			$io = $this->DoWizard( $config );

			$this->model('Log')->Set("FINISH: Selftest.",$io);
			$this->model('Log')->Out();
			
			return $io;
		}
		
		try{
			$this->CheckDatabase($config);
			$this->CheckTable($config);
		//	$this->CheckColumn($config);
			$io = true;
		}catch( Exception $e ){
			$io = false;
			$me = $e->getMessage();
			$this->p( $me );
			$this->model('Log')->Set($me,false);
			$this->DoWizard( $config );
		}	
		
		//  Finish
		$this->model('Log')->Set("FINISH: Selftest.",$io);
		$this->model('Log')->Out();
		return $io;
	}
	
	function Execute( Config $config )
	{
		//	Form
		$form_name = $this->config()->GetFormName();
		
		//  Database
		$database = Toolbox::Copy( $config->database );
		$database->user     = $this->form()->GetInputValue('user',$form_name);
		$database->password = $this->form()->GetInputValue('password',$form_name);
		
		//	Check user account.
		if( empty($database->user) ){
			return false;
		}
		
		//  Remove database name. (only connection, If not exists database.)
		unset($database->database);
		
		//  Connect to administrator account.
		if(!$io = $this->pdo()->Connect( $database ) ){
			//	$database->d();
		}else{
			$this->model('Log')->Set("Connect {$database->user} account.",true);
		}
		
		//  Create
		$this->CreateDatabase($config);
		$this->CreateTable($config);
		$this->CreateColumn($config);
		$this->CreateUser($config);
		$this->CreateGrant($config);
		
		return true;
	}
	
	function CallWizard( Config $config )
	{
		if(!$this->admin()){
			return;
		}
		$this->p( 'Call: ' . $this->GetCallerLine() );
		
		//  Start
		$this->model('Log')->Set("START: Wizard.");

		//  Get form name.
		$form_name = $this->config()->GetFormName();
		
		//  Init form config.
		$this->form()->AddForm( $this->config()->MagicForm() );
		
		//  Check secure
		if( $this->form()->Secure($form_name) ){
			$io = $this->Execute($config);
		}else{
			$io = false;
			$this->model('Log')->Set("Wizard-Form is not secure.");
		//	$this->form()->Debug($form_name);
		}
		
		//  Print form.
		if(!$io){
			$this->PrintForm( $config );
		}
		
		//  Finish
		$this->model('Log')->Set("FINISH: Wizard.", $io);
		$this->model('Log')->Out();
		
		//	Exception
		throw new OpModelException('Call Wizard.('.__LINE__.')');
	}
	
	function DoWizard( Config $config )
	{
		//  Start
		$this->model('Log')->Set('START: '.__FUNCTION__);
		
		//  Get form name.
		$form_name = $this->config()->GetFormName();
		
		//  Init form config.
		$this->form()->AddForm( $this->config()->MagicForm() );
		
		//  Check secure
		if( $this->form()->Secure($form_name) ){
			
			$database = Toolbox::Copy( $config->database );
			$database->user     = $this->form()->GetInputValue('user',$form_name);
			$database->password = $this->form()->GetInputValue('password',$form_name);
			
			//  Remove database name. (only connection, If not exists database.)
			unset($database->database);
			
			//  Connect to administrator account.
			if(!$io = $this->pdo()->Connect( $database ) ){
			//	$database->d();
			}else{
				$this->model('Log')->Set("Connect {$database->user} account.",true);
			}
			
			//  Create 
			$this->CreateDatabase($config);
			$this->CreateTable($config);
			$this->CreateColumn($config);
			$this->CreateUser($config);
			$this->CreateGrant($config);
		}else{
			$this->model('Log')->Set("Wizard-Form is not secure.");
		//	$this->form()->Debug($form_name);
		}
		
		//  Print form.
		$this->PrintForm( $config );
		
		//  Finish
		$this->model('Log')->Set('FINISH: '.__FUNCTION__);
		
		return true;
	}
	
	function PrintForm( $config )
	{	
		if( isset($config->title) ){
			$this->p( $config->title, 'h1' );
		}
		
		if( isset($config->message) ){
			$this->p( $config->message );
		}
		
		//  Get input decorate.
		$decorate = $this->config()->InputDecorate();
		
		//  Print form.
		$form_name = $this->config()->GetFormName();
		$this->form()->Start($form_name);
		foreach ( array('user','password','submit') as $input_name ){
			printf(
				$decorate,
				$this->form()->GetLabel($input_name),
				$this->form()->GetInput($input_name),
				$this->form()->GetError($input_name)
			);
		}
		$this->form()->Finish($form_name);
	}
	
	function CheckDatabase( Config $config )
	{
		//  Start
		$this->model('Log')->Set('START: '.__FUNCTION__);
		
		//  Get database list.
		$db_name = $config->database->database;
		$db_list = $this->pdo()->GetDatabaseList($config->database);
		
		//  Check database exists.
		$io = array_search( $db_name, $db_list);
		if( $io === false){
			throw new OpException("Database can not be found. ($db_name)");
		}

		//  Finish
		$this->model('Log')->Set('FINISH: '.__FUNCTION__);
		return true;
	}
	
	function CheckTable( Config $config )
	{
		//  Start
		$this->model('Log')->Set('START: '.__FUNCTION__);
		
		//  Get table-name list.
		if(!$table_list = $this->pdo()->GetTableList($config->database) ){
			throw new Exception("Failed GetTableList-method.");
		}
		
		//  Loop
		foreach( $config->table as $table_name => $table ){
			//  Check table exists.
			if( array_search( $table_name, $table_list) === false ){
				throw new OpException("Does not find table. ($table_name)");
			}
			//  Check column.
			$this->CheckColumn( $config, $table_name );
		}
		
		//  Finish
		$this->model('Log')->Set('FINISH: '.__FUNCTION__);
		return true;
	}
	
	function CheckColumn( Config $config, $table_name )
	{
		//  Start
		$this->model('Log')->Set('START: '.__FUNCTION__);
		
		$columns = Toolbox::toArray($config->table->$table_name->column);
		$structs = $this->pdo()->GetTableStruct( $table_name );
		$diff = array_diff_key( $columns, $structs );
		
		if( count($diff) ){
			$join = join(', ', array_keys($diff) );
			$me = "Does not match column. ($join)";
			throw new OpException($me);
		}
		
		//  Check detail
		foreach( $columns as $column_name => $column ){
			//$this->d($column);
			if( !isset($config->table->$table_name->column->$column_name->type) ){
				continue;
			}
			
			//  Get type from config.
			$type =$config->table->$table_name->column->$column_name->type;
			
			//  Check type
			if( $column['type'] !=  $type){
				$me = "Does not match column type. ($column_name is $type, not {$column['type']}.)";
				throw new OpException($me);
			}
		}
		
		//  Finish
		$this->model('Log')->Set('FINISH: '.__FUNCTION__);
		return true;
	}
	
	function CreateDatabase( Config $config)
	{
		//  Start
		$this->model('Log')->Set('START: '.__FUNCTION__);
		
		//  Create database
		$io = $this->pdo()->CreateDatabase( $config->database );
		
		//  Finish
		$this->model('Log')->Set('FINISH: '.__FUNCTION__);
		return $io;
	}
	
	function CreateTable( Config $config )
	{
		//  Start
		$this->model('Log')->Set('START: '.__FUNCTION__);
		
		if(empty($config->table)){
			return true;
		}
		
		foreach( $config->table as $table ){
			if( empty($table->database) ){
				$table->database = $config->database->database;
			}
			
			if( $io = $this->pdo()->CreateTable($table) ){
				$this->model('Log')->Set( $this->pdo()->qu(), 'green');
			}else{
				$this->model('Log')->Set("CreateTable is failed. ({$table->table})", false);
				return false;
			}
		}
		
		//  Finish
		$this->model('Log')->Set('FINISH: '.__FUNCTION__);
		return true;
	}
	
	function CreateColumn( Config $config )
	{
		//  Start
		$this->model('Log')->Set('START: '.__FUNCTION__);
		
		//  Select database
		$this->pdo()->Database($config->database->database); 
		
		foreach( $config->table as $table_name => $table ){
			$structs = $this->pdo()->GetTableStruct( $table_name );
			$diff = array_diff_key( Toolbox::toArray($table->column), $structs );
			
			if( count($diff) ){
				$this->d($diff);
				$config = new Config();
				$config->database = $config->database->database;
				$config->table    = $table_name;
				$config->column   = $diff;
				$this->pdo()->AddColumn($config);
			}
		}
		
		//  Finish
		$this->model('Log')->Set('FINISH: '.__FUNCTION__);
		return true;
	}
	
	function CreateUser($config)
	{
		//  Start
		$this->model('Log')->Set('START: '.__FUNCTION__);
		
		//  Inist
		$config->user->host     = $config->database->host;
		$config->user->user     = $config->database->user;
		$config->user->password = $config->database->password;
		
		//  Check user exists.
		$list = $this->pdo()->GetUserList();
		
		//  Log
		$this->model('Log')->Set( $this->pdo()->qu(), 'green');
		
		//  Check user exists.
		$io = array_search( $config->user->user, $list ) !== false ? true: false;
		if( $io ){
			$this->model('Log')->Set("New user {$config->user->user} is already exists.",true);
			
			//  Change password
			$io = $this->pdo()->Password($config->user);
			
		}else{
			//  Create user
			$io = $this->pdo()->CreateUser($config->user);
		}
		
		//  Log
		$this->model('Log')->Set( $this->pdo()->qu(), 'green');
		
		if(!$io){
			$me = "Create user is failed. ({$config->user->user})";
			throw new OpException($me);
		}
		
		//  Finish
		$this->model('Log')->Set('FINISH: '.__FUNCTION__);
		return true;
	}

	function CreateGrant($config)
	{
		//  Start
		$this->model('Log')->Set('START: '.__FUNCTION__);
		
		//  Init
		//$this->d( Toolbox::toArray($config) );
		$config->grant->host     = $config->database->host;
		$config->grant->database = $config->database->database;
		$config->grant->user     = $config->database->user;
		
		//  Create grant
		foreach( $config->table as $table_name => $table ){
			$config->grant->table = $table_name;
			if(!$this->pdo()->Grant($config->grant) ){
				$me = "Grant is failed. ($table_name)";
				throw new OpException($me);
			}
		}
		
		//  Finish
		$this->model('Log')->Set('FINISH: '.__FUNCTION__);
		return true;
	}
}

class WizardConfig extends ConfigMgr
{
	const FORM_NAME = 'op_magic_form';
	
	function GetFormName()
	{
		return self::FORM_NAME;
	}
	
	function MagicForm()
	{
		$config = new Config();
		
		//  form name
		$config->name = self::FORM_NAME;
		
		//  user
		$input_name = 'user';
		$config->input->$input_name->label = 'User';
		$config->input->$input_name->name  = $input_name;
		$config->input->$input_name->value = 'root';
		$config->input->$input_name->validate->required = true;
		
		//  password
		$input_name = 'password';
		$config->input->$input_name->label = 'Password';
		$config->input->$input_name->name  = $input_name;
		$config->input->$input_name->type  = 'password';
		
		//  submit
		$input_name = 'submit';
		$config->input->$input_name->label = '';
		$config->input->$input_name->name  = $input_name;
		$config->input->$input_name->type  = 'submit';
		$config->input->$input_name->value = 'Submit';
		
		return $config;
	}
}

class WizardHelper extends OnePiece5
{
	/**
	 * Create base config
	 * 
	 * @param string $user_name
	 * @param string $password
	 * @param string $host_name
	 * @param string $database_name
	 * @param string $table_name
	 * @param string $driver
	 * @param string $charset
	 */
	static function GetBase( $user_name, $password, $host_name, $database_name, $table_name, $driver='mysql', $charset='utf8' )
	{
		//  init
		$database->driver   = $driver;
		$database->host     = $host_name;
		$database->user     = $user_name;
		$database->password = $password;
		$database->database = $database_name;
		$database->charset  = $charset;

		$config = new Config();
		$config->database = $database;
		
		return $config;
	}
}
