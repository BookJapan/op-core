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
	private $_result = null;
	private $_wizard = null;
	
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
		if( ! $config instanceof Config ){
			$this->StackError("argument is not config-object");
			return false;
		}
		
		$selftest = $this->GetSession('selftest');
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
		$this->_wizard = new Config();
		
		if( $selftest = $this->GetSession('selftest') ){

			//  Get form name.
			$form_name = $this->config()->GetFormName();
			
			//  Init form config.
			$this->form()->AddForm( $this->config()->MagicForm() );
			
			//	Check each class
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
					if( $this->_Wizard($config) ){
						//	case of success, do delete config from session.
						unset($selftest[$class_name]);
					}else{
						$fail = true;
					}
				}
			}
			
			if( empty($fail) ){
				$this->form()->Clear($this->config()->GetFormName());
			}else{
				$this->_PrintForm($config->form);
			}
		}
		
		//	check
		$this->_result->D('selftest');
		$this->_wizard->D('selftest');
		
		//	re save
		$this->SetSession('selftest', $selftest);
		
		return isset($io) ? $io: true;
	}
	
	/**
	 * Selftest
	 * 
	 * @param Config $config
	 * @throws OpWzException
	 * @return void|boolean
	 */
	private function _Selftest( Config $config )
	{
		$user = $config->database->user;
		$host = $config->database->host;
		$dns  = $user.'@'.$host;
		
		//	Database connection test
		if(!$io = $this->pdo()->Connect($config->database) ){
	
			//	Logger		
			$this->model('Log')->Set("FAILED: Database connect is failed.($dns)",false);
			
			return false;
		}
		
		//	result
		$this->_result->connect->$dns = $io;
			
		//	Check database and table.
		if(!$this->_CheckDatabase($config)){
			return false;
		}
		if(!$this->_CheckTable($config)){
			return false;
		}
		
		return true;
	}
	
	private function _Wizard( Config $config )
	{
		//  Get form name.
		$form_name = $this->config()->GetFormName();
		
		//  Init form config.
	//	$this->form()->AddForm( $this->config()->MagicForm() );
		
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
		}
		
		//	Logger
		$this->model('Log')->Out();
		
		return empty($io) ? false: true;
	}
	
	private function _PrintForm( $config )
	{		
		print '<div style="margin:1em; padding:0px; border: 1px solid black;">';
		
		if( isset($config->title) ){
			$style['margin']  = '0px';
			$style['padding'] = '0px';
			$style['padding-left'] = '1em';
			$style['border'] = '1px solid black';
			$style['color'] = 'white';
			$style['background-color'] = 'black';
			$this->p( $config->title, 'h1', array('style'=>$style) );
		}
		
		print '<div style="margin:0em 1em;">';
		
		if( isset($config->message) ){
			$this->p( $config->message );
		}
		
		//  Get input decorate
		$decorate = $this->config()->InputDecorate();
		
		//  Print form
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
		print '</div>';
		print '</div>';
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
			$user = $config->database->user;
			$host = $config->database->host;
			$dns  = $user.'@'.$host;
			$this->_result->connect->$dns = false;
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
				
				//	fail...next!
				$fail = true;
				continue;
			}
			
			//	Good!	
			$this->_result->table->$table_name = true;
			
			//  Check column.
			if(!$this->_CheckColumn( $config, $table_name )){
				$fail = true;
			}
		}
		
		return empty($fail) ? true: false;
	}
	
	private function _CheckColumn( Config $config, $table_name )
	{
		$columns = Toolbox::toArray($config->table->$table_name->column);
		$structs = $this->pdo()->GetTableStruct( $table_name );
		
		//  Check detail
		foreach( $columns as $column_name => $column ){
			$io = null;
			
			//	This column, Does not exists in the existing table.
			if(!isset($structs[$column_name]) ){
				$this->_result->column->$table_name->$column_name = 'create';
				$hint = "Does not exists";
			}else{
				
				//  Get type from config.
				$type = $config->table->$table_name->column->$column_name->type;
				
				//	Get null from config.
				$null = isset($config->table->$table_name->column->$column_name->null) ? $config->table->$table_name->column->$column_name->null:'YES';
				$null = $null ? 'YES': 'NO';

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
				//	$this->d($structs[$column_name]);
				}
				
				//	If false will change this column.
				$this->_result->column->$table_name->$column_name = $io ? true: 'change';
			}
			
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
		//	Init
		$user = $config->database->user;
		$host = $config->database->host;
		$dns  = $user.'@'.$host;
		$database = $config->database->database;
		
		//	If does not exists database.
		if( empty($this->_result->database->$database) ){
			
			//  Create database
			$io = $this->pdo()->CreateDatabase( $config->database );

			//	check
			$this->_wizard->$dns->$database = $io;
		}
		
		return $io;
	}
	
	private function _CreateTable( Config $config )
	{
		if( empty($config->table) ){
			return true;
		}
		
		//	Init
		$user = $config->database->user;
		$host = $config->database->host;
		$dns  = $user.'@'.$host;
		$database = $config->database->database;
		
		foreach( $config->table as $table_name => $table ){
			
			//	Check
			if(!$table instanceof Config ){
				$this->model('Log')->Set("CHECK: $table_name is not Config.",false);
				continue;
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
			if(!$io = $this->pdo()->CreateTable($table) ){
				$fail = true;
			}
			
			$this->_wizard->$dns->$database->{$table->table} = $io;
			$this->model('Log')->Set( $this->pdo()->qu(), $io ? 'green':'red');
		}
		
		return empty($fail) ? true: false;
	}
	
	private function _CreateColumn( Config $config )
	{
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
			
			//	debug
			$this->_result->column->$table_name->d('selftest');
			
			//	create alter
			$create = new Config();
			
			//	change alter
			$change = new Config();
			
			//	result of selftest
			foreach( $this->_result->column->$table_name as $column_name => $value ){
				if( $value === true ){
					continue;
				}
				
				//	create or change
				switch( $value ){
					case 'create':
						$create->column->$column_name = $config->table->$table_name->column->$column_name;
						break;
						
					case 'change':
						$change->column->$column_name = $config->table->$table_name->column->$column_name;
						break;
				}
			}
			
			//	create
			if( isset($create->column) ){
				$create->database = $config->database->database;
				$create->table    = $table_name;
				
				//	execute to each table.
				$create->d();
				$io = $this->pdo()->AddColumn($create);
				$this->model('Log')->Set( $this->pdo()->qu(), $io?'green':'red');
			}
			
			//	change
			if( isset($change->column) ){
				$change->database = $config->database->database;
				$change->table    = $table_name;
				
				//	execute to each table.
				$change->d();
				$io = $this->pdo()->ChangeColumn($change);
				$this->model('Log')->Set( $this->pdo()->qu(), $io?'green':'red');
			}
		}
		
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
		$config->name  = self::FORM_NAME;
		$config->id    = 'form-wizard';
		$config->class = 'form-wizard';
		
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
		$config->input->$input_name->value = ' Wizard execute  ';
		$config->input->$input_name->style = 'background-color:#338bc6; color:white;';
		$config->input->$input_name->onmouseover = 'this.style.backgroundColor="#0596f6"';
		$config->input->$input_name->onmouseout  = 'this.style.backgroundColor="#338bc6"';
		
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

