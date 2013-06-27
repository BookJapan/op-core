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
	
	function _Selftest( Config $conifg )
	{
		//	Check
		if(!$this->admin()){
			return;
		}
		
		//  Start
		$this->model('Log')->Set("START: Selftest.");
		
		//  Finish
		$this->model('Log')->Set("FINISH: Selftest.",$io);
		$this->model('Log')->Out();
		return $io;
	}
	
	function Selftest( Config $config )
	{
		if(!$this->admin()){
			return;
		}
		
		//  Start
		$this->model('Log')->Set("START: Selftest.");
		
		//	Database connection test
		if(!$io = $this->pdo()->Connect($config->database) ){
			
			//	Logger
			$dns = $config->database->user.'@'.$config->database->host;
			$this->model('Log')->Set("FAILED: Database connect is failed.($dns)",false);
		//	$this->model('Log')->Out();
			
			//	Do wizard in NewWorld.
			$e = new OpWzException();
			$e->SetConfig($config);
			throw $e;
		}
		
		//	Check database and table.
		$this->_CheckDatabase($config);
		$this->_CheckTable($config);
		
		return true;
		
		//===========================================================================//
		
		try{
			$this->_CheckDatabase($config);
			$this->_CheckTable($config);
			$io = true;
		}catch( Exception $e ){
			$io = false;
			$me = $e->getMessage();
			$this->p( $me );
			$this->model('Log')->Set($me,false);
			$this->_DoWizard( $config );
		}	
		
		//  Finish
		$this->model('Log')->Set("FINISH: Selftest.",$io);
		$this->model('Log')->Out();
		return $io;
	}
	
	private function _Execute( Config $config )
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
		$this->_CreateDatabase($config);
		$this->_CreateTable($config);
		$this->_CreateColumn($config);
		$this->_CreateUser($config);
		$this->_CreateGrant($config);
		
		return true;
	}
	
	private function _CallWizard( Config $config )
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
				
				//	Information
				$this->p("Does not access from {$database->user} user.");
				dump::d(Toolbox::toArray($database));
				
				//	Discard
				$this->form()->Flash($form_name);
			}else{
				$this->model('Log')->Set("Connect {$database->user} account.",true);
			}

			//  Create
			if( $io ){ 
				$this->_CreateDatabase($config);
				$this->_CreateTable($config);
				$this->_CreateColumn($config);
				$this->_CreateUser($config);
				$this->_CreateGrant($config);
			}
		}else{
			$this->model('Log')->Set("Wizard-Form is not secure.");
		//	$this->form()->Debug($form_name);
		}
		
		//  Finish
		$this->model('Log')->Set('FINISH: '.__FUNCTION__);
		$this->model('Log')->Out();
		
		return empty($io) ? false: true;
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
	
	private function _CheckDatabase( Config $config )
	{
		//  Start
		$this->model('Log')->Set('START: '.__FUNCTION__);
		
		//  Get database list.
		$db_name = $config->database->database;
		$db_list = $this->pdo()->GetDatabaseList($config->database);
		
		//  Check database exists.
		$io = array_search( $db_name, $db_list);
		if( $io === false){
			$e = new OpWzException("Database can not be found. ($db_name)");
			$e->SetConfig($config);
			throw $e;
		}

		//  Finish
		$this->model('Log')->Set('FINISH: '.__FUNCTION__);
		return true;
	}
	
	private function _CheckTable( Config $config )
	{
		//  Start
		$this->model('Log')->Set('START: '.__FUNCTION__);
		
		//  Get table-name list.
		if(!$table_list = $this->pdo()->GetTableList($config->database) ){
			//	Logger
			$this->model('Log')->Set('FAILED: '.$this->pdo()->qu(),false);
			//	Exception
			$e = new OpWzException("Failed GetTableList-method.");
			$e->SetConfig($config);
			throw $e;
		}
		
		//  Loop
		foreach( $config->table as $table_name => $table ){
			//  Check table exists.
			if( array_search( $table_name, $table_list) === false ){
				//	Logger
				$this->model('Log')->Set("CHECK: $table_name is does not exists.",false);
				//	Exception
				$e = OpWzException("Does not find table. ($table_name)");
				$e->SetConfig($config);
				throw $e;
			}
			//  Check column.
			$this->_CheckColumn( $config, $table_name );
		}
		
		//  Finish
		$this->model('Log')->Set('FINISH: '.__FUNCTION__);
		return true;
	}
	
	private function _CheckColumn( Config $config, $table_name )
	{
		//  Start
		$this->model('Log')->Set('START: '.__FUNCTION__);
		
		$columns = Toolbox::toArray($config->table->$table_name->column);
		$structs = $this->pdo()->GetTableStruct( $table_name );
		$diff = array_diff_key( $columns, $structs );
		
		if( count($diff) ){
			$join = join(', ', array_keys($diff) );
			$me = "Does not match column. ($join)";
			throw new OpWzException($me);
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
				throw new OpWzException($me);
			}
		}
		
		//  Finish
		$this->model('Log')->Set('FINISH: '.__FUNCTION__);
		return true;
	}
	
	private function _CreateDatabase( Config $config)
	{
		//  Start
		$this->model('Log')->Set('START: '.__FUNCTION__);
		
		//  Create database
		$io = $this->pdo()->CreateDatabase( $config->database );
		
		//  Finish
		$this->model('Log')->Set('FINISH: '.__FUNCTION__);
		return $io;
	}
	
	private function _CreateTable( Config $config )
	{
		//  Start
		$this->model('Log')->Set('START: '.__FUNCTION__);
		
		if(empty($config->table)){
			return true;
		}
		
		foreach( $config->table as $table_name => $table ){
			
			//	Check
			if(!$table instanceof Config ){
				$this->model('Log')->Set("CHECK: $table_name is not Config.",false);
				return false;
			}
			
			//	Check
			if( empty($table->database) ){
				$table->database = $config->database->database;
			}
			
			//	Support
			if( empty($table->table) ){
				$table->table = $table_name;
			}
			
			//	Execute
			$io = $this->pdo()->CreateTable($table);
			$this->model('Log')->Set( $this->pdo()->qu(), 'green');
			if(!$io){
				$this->model('Log')->Set("CreateTable is failed. ($table_name)", false);
				return false;
			}
		}
		
		//  Finish
		$this->model('Log')->Set('FINISH: '.__FUNCTION__);
		return true;
	}
	
	private function _CreateColumn( Config $config )
	{
		//  Start
		$this->model('Log')->Set('START: '.__FUNCTION__);
		
		//  Select database
		$this->pdo()->Database($config->database->database);
		
		foreach( $config->table as $table_name => $table ){

			//	Check
			if(!$table instanceof Config ){
				$this->model('Log')->Set("CHECK: This is not Config. ($table_name)",false);
				return false;
			}
			
			//	Get exists table name
			$structs = $this->pdo()->GetTableStruct( $table_name, $config->database->database );
			if(!$structs){
				$this->model('Log')->Set("CHECK: This table does not exist. ($table_name)",false);
				return false;
			}
			
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
	
	private function _CreateUser($config)
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
			
			//	Logger
			$this->model('Log')->Set("CHECK: {$config->user->user} is already exists.",true);
			
			//  Change password
			$io = $this->pdo()->Password($config->user);
			
			//  Log
			$this->model('Log')->Set( $this->pdo()->qu(), $io);
			
			if( $io ){
				$this->model('Log')->Set("Change password is successful.",'blue');
			}else{
				$this->model('Log')->Set("Change password is failed.",'red');
			}
			
		}else{
			//  Create user
			$io = $this->pdo()->CreateUser($config->user);
			
			//  Log
			$this->model('Log')->Set( $this->pdo()->qu(), $io);
		}
		
		if(!$io){
			$me = "Create user is failed. ({$config->user->user})";
			throw new OpWzException($me);
		}
		
		//  Finish
		$this->model('Log')->Set('FINISH: '.__FUNCTION__);
		return true;
	}

	private function _CreateGrant($config)
	{
		//  Start
		$this->model('Log')->Set('START: '.__FUNCTION__);
		
		//  Init
		$config->grant->host     = $config->database->host;
		$config->grant->database = $config->database->database;
		$config->grant->user     = $config->database->user;
		
		//	Check
		if( isset($config->table) ){
			$tables = Toolbox::toArray($config->table);
		}else{
			$tables = null;
		}
		
		if(!count($tables) ){
			$this->model('Log')->Set('CHECK: Empty table name.',false);
		}else{
			//  Create grant
			foreach( $tables as $table_name => $table ){
				$config->grant->table = $table_name;
				if(!$this->pdo()->Grant($config->grant) ){
					$me = "Grant is failed. ($table_name)";
					throw new OpWzException($me);
				}
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

class OpWzException extends OpException
{
	private $_config = null;
	function SetConfig( Config $config )
	{
		$this->_config = $config;
	}
	
	function GetConfig()
	{
		return $this->_config;
	}
}
