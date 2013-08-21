<?php
/**
 * 
 * 
 * 
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
	
	/**
	 * Save selftest inner wizard
	 * 
	 * @param Config $config
	 */
	function SetSelftest( $class_name, Config $config )
	{
		/*
		$this->mark(__FUNCTION__."($class_name)");
		$this->mark($this->GetCallerLine());
		$config->D();
		*/
		if( ! $config instanceof Config ){
			$this->StackError("argument is not config-object");
			return false;
		}
		
		$selftest = $this->GetSession('selftest');
	//	unset($selftest);
		$selftest[$class_name] = $config;
		$this->SetSession('selftest',$selftest);
	}
	
	function Selftest()
	{
		if(!$this->admin()){
			return null;
		}
		
		//	init
		$this->_result = new Config();
		
		if( $selftest = $this->GetSession('selftest') ){
			foreach( $selftest as $class_name => $config ){
				try{
					if( $io = $this->_Selftest($config) ){
						$this->mark("![.blue[$class_name is selftest passed]]",'selftest');
					}else{
						$this->mark("![.red [$class_name is selftest failed]]",'selftest');
					}
				}catch( Exception $e ){
					$this->mark($e->getMessage());
					$io = false;
				}
				
				if( $io ){
					//	passed through a self-test
				}else{
					//	Wizard
					$io = $this->_Wizard($config);
					if( $io ){
						$this->form()->Clear($this->config()->GetFormName());
						unset($selftest[$class_name]);
					}else{
						$this->_PrintForm($config);
						break;
					}
				}
			}
		}
		
		$this->_result->D('selftest');
		
		//	re save
		$this->SetSession('selftest', $selftest);
		
		return $io;
	}
	
	private $_result = null;
	
	/**
	 * Selftest
	 * 
	 * @param Config $config
	 * @throws OpWzException
	 * @return void|boolean
	 */
	private function _Selftest( Config $config )
	{
		//	Database connection test
		if(!$io = $this->pdo()->Connect($config->database) ){
			
			//	Logger
			$dns = $config->database->user.'@'.$config->database->host;
			$this->model('Log')->Set("FAILED: Database connect is failed.($dns)",false);
			
			//	result
			$this->_result->connect = false;
			
			return false;
		}
		
		//	Check database and table.
		if(!$this->_CheckDatabase($config)){
			return false;
		}
		if(!$this->_CheckTable($config)){
			return false;
		}
		
		return true;
	}
	
	/*
	private function _Execute_( Config $config )
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
	*/
	
	/*
	private function _CallWizard_( Config $config )
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
	*/
	
	private function _Wizard( Config $config )
	{
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
				$this->_ChangeColumn($config);
				$this->_CreateUser($config);
				$this->_CreateGrant($config);
			}
		}else{
		//	$this->model('Log')->Set("Wizard-Form is not secure.");
		//	$this->form()->Debug($form_name);
		}
		
		//	Logger
		$this->model('Log')->Out();
		
		return empty($io) ? false: true;
	}
	
	private function _PrintForm( $config )
	{
		/*
		$this->mark( $this->GetCallerLine(0) );
		$this->mark( $this->GetCallerLine(1) );
		$this->mark( $this->GetCallerLine(2) );
		*/
		
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
		//  Get database list.
		$db_name = $config->database->database;
		$db_list = $this->pdo()->GetDatabaseList($config->database);
		
		//  Check database exists.
		$io = array_search( $db_name, $db_list);
		if( $io === false){
			//	result
			$this->_result->database = false;
			//	logger
			$this->model('Log')->Set('FAILED: '.__FUNCTION__,false);
			return false;
		}
		
		return true;
	}
	
	private function _CheckTable( Config $config )
	{
		//  Get table-name list.
		if(!$table_list = $this->pdo()->GetTableList($config->database) ){
			
			//	Logger
			$this->model('Log')->Set('FAILED: '.$this->pdo()->qu(),false);
			
			//	result
			$this->_result->connect = false;
			return false;
		}
		
		//  Loop
		foreach( $config->table as $table_name => $table ){
			
			//  Check table exists.
			if( array_search( $table_name, $table_list) === false ){
				//	Logger
				$this->model('Log')->Set("FAILED: $table_name is does not exists.",false);
				
				//	result
				$this->_result->table->$table_name = false;
				return false;
			}
			$this->_result->table->$table_name = true;
			
			//  Check column.
			if(!$this->_CheckColumn( $config, $table_name )){
				return false;
			}
		}
		
		return true;
	}
	
	private function _CheckColumn( Config $config, $table_name )
	{
		$columns = Toolbox::toArray($config->table->$table_name->column);
		$structs = $this->pdo()->GetTableStruct( $table_name );
		//$this->d($structs);
		
		//  Check detail
		foreach( $columns as $column_name => $column ){
			$io = null;

			if(!isset($structs[$column_name]) ){
				$this->_result->column->$table_name->$column_name = false;
			}
			
			//  Get type from config.
			$type = $config->table->$table_name->column->$column_name->type;
			
			//	Get null from config.
			$null = isset($config->table->$table_name->column->$column_name->null) ? $config->table->$table_name->column->$column_name->null:'YES';
			$null = $null ? 'YES': 'NO';

			//	already existing in table
			if( isset($structs[$column_name]) ){
				if( $structs[$column_name]['extra'] === 'auto_increment' OR
					$structs[$column_name]['type'] === 'timestamp' OR
					$structs[$column_name]['key'] === 'PRI' ){
					$null = 'NO';
				}
				
				//	Convert config value
				if( $type == 'boolean' ){
					$type = 'tinyint';
				}
				
				//	Convert existing table value
				if( $type == 'enum'){
					if(preg_match( '/^enum\((.+)\)$/', $structs[$column_name]['type'], $match )){
						$join = array();
						foreach( explode(',',$match[1]) as $temp ){
							$join[] = trim($temp,"'");
						}
						$structs[$column_name]['length'] = join(',',$join);
					//	$this->d($length);
					//	$this->d($config->table->$table_name->column->$column_name->length);
					}
					$structs[$column_name]['type'] = 'enum';
				}
				
				//	Check type
				if( $type != $structs[$column_name]['type'] ){
					$io = false;
					$hint = "type=$type not {$structs[$column_name]['type']}";
				
				//	Check NULL
				}else if( $null != $structs[$column_name]['null'] ){	
					$io = false;
					$hint = "null=$null not {$structs[$column_name]['null']}";
					
				}else{
					$io = true;
				}
				
				//	debug
				if(!$io){
					$this->d($structs[$column_name]);
				}
			}else{
				$io = false;
				$hint = "$column_name is not in existing table";
			}
			
			$this->_result->column->$table_name->$column_name = $io;
			
			if(!$io){
				$return = false;
				$this->mark("![.red[table=$table_name, column=$column_name, $hint)]]",'selftest');
			}
		}
		
		//  Finish
		return isset($return) ? $return: true;
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
	
	private function _ChangeColumn( Config $config )
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
			
			//	
			if( empty($this->_result->column->$table_name) ){
				$this->mark('continue');
				continue;
			}
			
			$this->_result->column->$table_name->d();
			
			//	new alter
			$alter = new Config();
			
			foreach( $this->_result->column->$table_name as $column_name => $value ){
				if( $value ){
					continue;
				}
				
				$alter->database = $config->database->database;
				$alter->table    = $table_name;
				$alter->column->$column_name = $config->table->$table_name->column->$column_name;
			}
			
			//	execute to each table.
			$alter->d();
			$io = $this->pdo()->ChangeColumn($alter);
			$this->model('Log')->Set( $this->pdo()->qu(), $io?'green':'red');
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
			$this->model('Log')->Set("CHECK: {$config->user->user} is already exists.");
			
			//  Change password
			$io = $this->pdo()->Password($config->user);
			
			//  Log
			$this->model('Log')->Set( $this->pdo()->qu(), $io?'green':'red');
			
			if( $io ){
			//	$this->model('Log')->Set("Change password is successful.",'blue');
			}else{
			//	$this->model('Log')->Set("Change password is failed.",'red');
			}
			
		}else{
			//  Create user
			$io = $this->pdo()->CreateUser($config->user);
			
			//  Log
			$this->model('Log')->Set( $this->pdo()->qu(), $io);
		}
		
		if(!$io){
			$wz = new OpWzException("Create user is failed. ({$config->user->user})");
			$wz->SetConfig($config);
			throw $wz;
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
					$wz = new OpWzException("Grant is failed. ($table_name)");
					$wz->SetConfig($config);
					throw $wz;
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

/*
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
*/

