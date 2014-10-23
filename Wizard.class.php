<?php
/**
 * Wizard is build database automatic.
 * Wizard has self test feature.
 * If the database schema has been changed, Wizard will report to the administrator.
 * 
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 */

/**
 * Wizard
 * 
 * @author tomoaki.nagahara@gmail.com
 */
class Wizard extends OnePiece5
{
	private $_config = null; // WizardConfig
	private $_result = null; // check's result
	private $_wizard = null; // do wizard's result
	private $_status = null; //	Wizard status

	/**
	 * Execute selftest
	 *
	 * @var boolean
	 */
	const _DO_SELFTEST_ = 'do_selftest';
	
	/**
	 * Selftest result.
	 *
	 * @var boolean
	 */
	const _IS_SELFTEST_ = 'is_selftest';
	
	/**
	 * @return WizardConfig
	 */
	function Config()
	{
		if(!$this->_config){
			$this->_config = new WizardConfig();
		}
		return $this->_config;
	}
	
	/**
	 * Get execution flag of selftest.
	 *
	 * @return boolean
	 */
	function isSelftest()
	{
		return $this->_is_selftest;
	}
	
	/**
	 * Get execution flag of wizard.
	 * 
	 * @return boolean
	 */
	function isWizard()
	{
		return $this->_is_wizard;
	}
	
	/**
	 * Save selftest to wizard.
	 * 
	 * @param string $class_name
	 * @param Config $config
	 */
	function SetSelftest( $class_name, Config $config )
	{
		if( ! $config instanceof Config ){
			$this->StackError("argument is not config-object");
			return false;
		}
		
		//	Check table
		if( empty($config->table) ){
			$this->mark('$config->table is empty.');
			return false;
		}
		
		//	Check port
		if( empty($config->database->port) ){
			if(is_array($config->database)){
				$config->database['port'] = '3306';
			}else{
				$config->database->port = '3306';
			}
		}
		
		$selftest = $this->GetSession('selftest');
		$selftest[$class_name] = Toolbox::toArray($config); // anti of __php_incomplete_class
		$this->SetSession('selftest',$selftest);
	}
	
	const _DO_SELFTEST_ = 'do_selftest';
	const _IS_SELFTEST_ = 'is_selftest';
	
	public function Selftest()
	{
		//	Check admin
		if(!$this->admin()){
			return null;
		}
		
		//	Check
		if( Env::Get(self::_DO_SELFTEST_) ){
			$this->mark('Selftest is already execute.','selftest');
			return Env::Get(self::_IS_SELFTEST_);
		}
		Env::Set(self::_DO_SELFTEST_,true);
		
		//	init
		$this->_result = new Config();
		$this->_wizard = new Config();
		
		//	init form
		$this->form()->AddForm( $this->config()->WizardForm() );
		
		//	selftest of each config
		$this->_selftest_loop();
		
		//	check
		$this->d($this->_result,'selftest');
		$this->d($this->_wizard,'selftest');
		
		//	status
		if( $this->_status ){
			$this->mark($this->_status,'selftest');
		}
		
		return Env::Get(self::_IS_SELFTEST_);
	}
	
	private function _selftest_loop()
	{
		//	init
		$_is_selftest = true;
		
		//	get selftest config
		if( $selftest = $this->GetSession('selftest') ){
			
			//	Check each class
			foreach( $selftest as $class_name => $config ){
				
				//	Get Cache
				$key = md5($class_name.', '.serialize($config));
				if( $io = $this->Cache()->Get($key) and false ){
					$this->mark("return cache value ($class_name, $key)",'wizard');
					continue;
				}
				
				try{
					//	anti of __php_incomplete_class
					$config = Toolbox::toObject($config);
					
					if( $io = $this->_Selftest($config) ){
						$this->mark("![.blue[$class_name is selftest passed]]",'selftest');
					}else{
						$this->mark("![.red [$class_name is selftest failed]]",'selftest');
					}
				}catch( Exception $e ){
					$version = PHP_VERSION;
					$file = $e->getFile();
					$line = $e->getLine();
					$message = $e->getMessage();
					$this->mark("$file, $line, $message, PHP $version");
					$io = false;
				}
		
				//	Set Cache
				$this->Cache()->Set($key,$io);
		
				if(!$io){
					$_is_selftest = false;
					$do_wizard = true;
					$config_list[] = $config;
				}
			}
			
			//	save selftest result
			Env::Set(self::_IS_SELFTEST_, $_is_selftest);
			
			if(!empty($do_wizard)){
				//	Execute the Wizard.
				if( $this->_Wizard($config_list) ){
					//	case of success, do delete config from session.
					unset($selftest[$class_name]);
				}else{
					$fail = true;
				}
			}
			
			if( empty($fail) ){
				$this->form()->Clear($this->config()->GetFormName());
			}else{
				$this->_PrintForm($config->form);
			}
		}
		
		//	re save
		$this->SetSession('selftest', $selftest);
		
		return empty($do_wizard) ? false: true;
	}
	
	/**
	 * Check database struct.
	 * Result is save to $this->_result.
	 * 
	 * @param  Config $config
	 * @return boolean
	 */
	private function _Selftest( Config $config )
	{
		//	user connection test
		$io = $_is_user_connection = $this->_check_connection($config->database);
		
		//	If case of wizard. (connect root)
		$form_name = $this->Config()->GetFormName();
		if( $this->Form()->Secure( $form_name ) ){
			
			//	clone database config
			$database = clone $config->database;
			
			//	root account and password
			$user = $this->Form()->GetInputValue(WizardConfig::_INPUT_USERNAME_,$form_name);
			$pass = $this->Form()->GetInputValue(WizardConfig::_INPUT_PASSWORD_,$form_name);
			
			//	replace user and password
			$database->user		 = $user;
			$database->password	 = $pass;

			//	root connection test
			$io = $_is_root_connection = $this->_check_connection($database);
		}
		
		//	return connection result.
		if(!$io){
			return false;
		}
		
		//	check database
		if(!$this->_CheckDatabase($config)){
			$this->model('Log')->Set("FAILED: Database check. (host=$host, user=$user, db=$db)",false);
			return false;
		}
		
		//	check table (check column are here)
		if(!$this->_CheckTable($config)){
			return false;
		}
		
		return $_is_user_connection;
	}
	
	/**
	 * Execute database rebuild.
	 * 
	 * @param  array $list_of_config_array
	 * @return boolean
	 */
	private function _Wizard( $config_list )
	{
		//  Get form name.
		$form_name = $this->config()->GetFormName();
		
		//  Check secure
		if(!$this->form()->Secure($form_name) ){
			$this->_status = $this->form()->GetStatus($form_name);
		}else{
			$this->_status = 'Is secure.';
			
			foreach( $config_list as $config ){
				
				$database = Toolbox::Copy( $config->database );
				$database->user     = $this->form()->GetInputValue(WizardConfig::_INPUT_USERNAME_,$form_name);
				$database->password = $this->form()->GetInputValue(WizardConfig::_INPUT_PASSWORD_,$form_name);
				
				//  Remove database name. (only connection, If not exists database.)
				unset($database->database);
				
				//  Connect to administrator account.
				if(!$io = $this->pdo()->Connect( $database ) ){
					//	remove error information
					$this->d($this->FetchError(),'debug');
					$this->d(Error::Get(),'debug');
					
					//	Information
					$this->p("![.red[Does not access from {$database->user} user.]]");
					$this->d(Toolbox::toArray($database));
					
					//	Discard
					$this->form()->Flash($form_name);
				}else{
					$this->model('Log')->Set("Connect {$database->user} account.",true);
				}
				
				//  Create
				if( $io ){
					//	re:check table's column
					$this->_CheckTable($config);
					
					$this->D($this->_result,'selftest');
					$this->_CreateDatabase($config);
					$this->_CreateTable($config);
					$this->_CreateColumn($config);
					$this->_CreateUser($config);
					$this->_CreateGrant($config);
				}
			}
		}
		
		//	Logger
		$this->model('Log')->Out();
		
		return empty($io) ? false: true;
	}
	
	private function _PrintForm( $config )
	{
		//	get form title and form message
		$title   = $config->title;
		$message = $config->message;
		
		//	check and wiki2
		$title   = is_string($title)   ? $this->Wiki2($title):   'Please set "$config->form->title".';
		$message = is_string($message) ? $this->Wiki2($message): 'Please set "$config->form->message".';
		
		$args['title'] = $title;
		$args['message'] = $message;
		$args['form_name'] = WizardConfig::_FORM_NAME_;
		$args['input_username'] = WizardConfig::_INPUT_USERNAME_;
		$args['input_password'] = WizardConfig::_INPUT_PASSWORD_;
		$args['input_submit']   = WizardConfig::_INPUT_SUBMIT_;
		$this->Template('op:/Template/wizard-form.phtml',$args);
	}
	
	private function _check_connection( $database )
	{
		//	init
		$dbms  = $database->driver;
		$host  = $database->host;
		$port  = $database->port;
		$user  = $database->user;
		$db    = $database->database;
	
		if( $port and $port !== '3306' ){
			$host .=  ':'.$port;
		}
	
		//	Database connection test
		if(!$io = $this->pdo()->Connect( $database )){
			$this->model('Log')->Set("FAILED: Database connect is failed.(dbms=$dbms, host=$host, port=$port, user=$user, db=$db)",false);
		}
	
		//	Save result of connection
		$this->_result->connect->$host->$user->$db = $io;
	
		return $io;
	}
	
	private function _CheckDatabase( Config $config )
	{
		//  Get database list.
		$dbms    = $config->database->driver;
		$host    = $config->database->host;
		$host   .= ':'.$config->database->port;
		$user    = $config->database->user;
		$db_name = $config->database->database;
		$db_list = $this->pdo()->GetDatabaseList($config->database);
		
		//  Check database exists.
		$io = array_search( $db_name, $db_list) === false ? false: true;

		//	result
		$this->_result->database->$db_name = $io;
		
		if(!$io){
			//	logger
			$this->model('Log')->Set('FAILED: '.__FUNCTION__,false);
		}
		
		return $io;
	}
	
	private function _CheckTable( Config $config )
	{
		$database = clone $config->database;
		
		//  Get table-name list.
		$table_list = $this->pdo()->GetTableList($database);
		if( $table_list === false ){
			//	result
			$this->_result->connect->$host->$user->$db = false;
			
			//	Log
			$info = "(host={$database->host}, user={$database->user}, db={$database->database})";
			$this->model('Log')->Set('FAILED: '.$this->pdo()->qu(),false);
			return false;
		}
		
		//  Loop
		foreach( $config->table as $table_name => $table ){
			
			//  Check table exists.
			if( array_search( $table_name, $table_list) === false ){
				
				//	Logger
				$this->model('Log')->Set("FAILED: $table_name is does not exists. (or denny access)",false);
				
				//	result
				$this->_result->table->$table_name = false;

				$this->_result->table->debug = false;
				
				//	fail...next!
				$fail = true;
				continue;
			}
			
			//	Good!	
			$this->_result->table->$table_name = true;
			
			//	Check column config exists
			if( $config->table->$table_name->column ){
				//	OK
			}else if( $config->table->$table_name->column === false){
				return true;
			}else{
				$this->StackError("Illigal error.");
				continue;
			}
			
			//  Check column.
			if(!$this->_CheckColumn( $config, $table_name )){
				$fail = true;
			}
			
			//	Check index
			if(!$this->_check_index( $config, $table_name )){
				$fail = true;
			}
		}
		
		return empty($fail) ? true: false;
	}
	
	private function _CheckColumn( Config $config, $table_name )
	{	
		//	return value
		$result = true;
		
		$columns = $config->table->$table_name->column;
		$structs = $this->pdo()->GetTableStruct( $table_name );
		
		//	use create column, new create column is after where column
		$after = null;
		
		//  Check detail
		foreach( $columns as $column_name => $column ){
			
			//	init
			$fail = false;
			
			//	init
			$io = null;
			$hint = null;
			
			//	This column, Does not exists in the existing table.
			if(!isset($structs[$column_name])){
				
				$this->mark("![.red[$column_name is fail]]");
				$fail = true;
				
				if( empty($config->table->$table_name->column->$column_name->rename) ){
					//	create new column
					$this->_result->column->$table_name->$column_name = 'create,'.$after;
					$hint = "Does not exists";
				}else{
					//	change column name by exists column
					$rename = $config->table->$table_name->column->$column_name->rename;
					if( isset($structs[$rename]) ){
						$this->_result->column->$table_name->$column_name = 'change,'.$after;
						$hint = "Rename column name, to $column_name from $rename";
					}else{
						$this->_result->column->$table_name->$column_name = false;
						$hint = "Does not have original column name. ($rename)";
					}
				}
			}else{
				//  Get type from config.
				$type = $config->table->$table_name->column->$column_name->type;
				if(!is_string($type)){
					$type = 'int';
				}
				
				//	Get length
				$length = isset($config->table->$table_name->column->$column_name->length)
							  ? $config->table->$table_name->column->$column_name->length: null;
				
				//  Get default from config.
				$default = isset($config->table->$table_name->column->$column_name->default)
							   ? $config->table->$table_name->column->$column_name->default: null;
				
				//	Get null from config.
				$null = isset($config->table->$table_name->column->$column_name->null)
							? $config->table->$table_name->column->$column_name->null: 'YES';
				$null = $null ? 'YES': 'NO';
				
				if( $structs[$column_name]['extra'] === 'auto_increment' OR
					$structs[$column_name]['type'] === 'timestamp' OR
					$structs[$column_name]['key'] === 'PRI' ){
					$null = 'NO';
				}
				
				//	Convert config value
				if( $type == 'boolean' ){
					$type =  'tinyint';
				}
				
				//	Convert existing table value
				if( $type === 'enum' or $type === 'set' ){
					$length = "'".join("','",array_map('trim',explode(',',$length)))."'";
				}
				
				//	Check type
				if( $type != $structs[$column_name]['type'] ){
					$fail = true;
					$hint = "type=$type not {$structs[$column_name]['type']}";
				
				//	Check length
				}else if( $length and $length != $structs[$column_name]['length'] ){
					$fail = true;
					$hint = "length=$length not {$structs[$column_name]['length']}";
					
				//	Check NULL
				}else if( $null != $structs[$column_name]['null'] ){	
					$fail = true;
					$hint = "null=$null not {$structs[$column_name]['null']}";

				//	Check default
				}else if( !is_null($default) and $default != $structs[$column_name]['default'] ){
					$fail = true;
					$temp = is_null($structs[$column_name]['default']) ? 'null': $structs[$column_name]['default'];
					$hint = "default=$default not $temp";
					
				}else{
					//	
				}
				
				//	If false will change this column.
				$this->_result->column->$table_name->$column_name = $fail ? 'change,': true;
			}
			
			//	use create column
			$after = $column_name;
			
			//	Logger
			if( $fail ){
				$this->model('Log')->Set("ERROR: table=$table_name, column=$column_name, hint=$hint",false);
			}
			
			if( $fail ){
				$result = false;
			}
		}
		
		//  Finish
		return $result;
	}
	
	private function _check_index( Config $config, $table_name )
	{
		//	return value
		$result = true;
		
		$columns = $config->table->$table_name->column;
		$structs = $this->pdo()->GetTableStruct( $table_name );

		//  Check detail
		foreach( $columns as $column_name => $column ){
			
			//	Get each index type
			$ai     = isset($column->ai)     ? $column->ai     : null;
			$pkey   = isset($column->pkey)   ? $column->pkey   : null;
			$index  = isset($column->index)  ? $column->index  : null;
			$unique = isset($column->unique) ? $column->unique : null;
			
			$temp['ai'] = $ai;
			$temp['pkey'] = $pkey;
			$temp['index'] = $index;
			$temp['unique'] = $unique;
			
			//	Convert to string from boolean 
			$index  = $index === true ? 'true': $index;
			
			//	Convert type
			if( $ai or $pkey){
				$type = 'PRI';
			}else if( $unique ){
				$type = 'UNI';
			}else if( $index == 'true' ){
				$type = 'MUL';
			}else if( $index === 'unique' ){
				$type = 'UNI';
			}else{
				$type = 'null';
			}
			
			//	Check index
			$key = empty($structs[$column_name]['key']) ? 'null': $structs[$column_name]['key'];
			if( $type != $key ){
				$result = false;
				$hint = "index=$type, Not $key";
				$this->model('Log')->Set("ERROR: table=$table_name, column=$column_name, hint=$hint",false);
				
				//	selftest result flag
				$this->_result->column->$table_name->$column_name = 'change,';
				
				$this->mark($column_name);
			//	$this->mark($key);
			//	$this->mark($type);
				$this->mark($hint);
				$this->d($temp);
				$this->d($column);
				$this->d($structs[$column_name]);
				
			}
		}
		
		return $result;
	}
	
	private function _CheckPrivilege( Config $config )
	{
		if(empty($config->privilege)){
			return true;
		}
		
		//	Only mysql.(yet)
		$host	 = $config->database->host;
		$user	 = $config->database->user;
		$db		 = 'mysql';
		$table	 = 'user';
		
		$select = new Config();
		$select->database	 = $db;
		$select->table		 = $table; 
		$select->where->Host = $host; // 'localhost'; // TODO
		$select->where->User = $user;
		$select->limit		 = 1;
		$record = $this->pdo()->select($select);
		
	//	$this->mark( $this->pdo()->qu() );
	//	$this->d($record);
		
		if( is_string($config->privilege) ){
			foreach( explode(',',$config->privilege) as $label ){
				$privilege[$label] = 'Y';
			}
		}else{
			foreach( $config->privilege as $label => $value ){
				$privilege[$label] = $value ? 'Y': 'N';
			}
		}
		
	//	$this->mark($config->privilege);
	//	$this->d($privilege);
		
		foreach( $privilege as $priv => $var ){
			$key = ucfirst($priv).'_priv';
			$var = $record[$key];
			if( $var == 'N' ){
				$this->mark("privilege: $key");
				return false;
			}
		}
		
		return true;
	}
	
	private function _CreateDatabase( Config $config)
	{
		//	Init
		$io   = true;
		$host = $config->database->host;
		$user = $config->database->user;
		$db   = $config->database->database;
		
		//	If does not exists database.
		if( empty($this->_result->database->$db) ){
			
			//  Create database
			$io = $this->pdo()->CreateDatabase( $config->database );
			
			//	check
			$this->_wizard->$host->$user->$db->created = $io;
		}
		
		return $io;
	}
	
	private function _CreateTable( Config $config )
	{
		if( empty($config->table) ){
			return true;
		}
		
		//	Init
		$host = $config->database->host;
		$user = $config->database->user;
		$db   = $config->database->database;
		
		foreach( $config->table as $table_name => $table ){
			
			//	Check selftest reslut
			if( $this->_result->table->$table_name === true ){
				//	no trouble
				$this->model('Log')->Set("CHECK: Create table is skip ($table_name is no trouble)");
				continue;
			}
			
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
			
			//	Check privilege.(necessary root account)
			$table->database = $config->database;
			if(!$io = $this->_CheckPrivilege($table)){
				$fail = true;
			}
			
			$io = $io ? 'true': 'false';
			$table_name = $table->table;
		//	$this->mark("host=$host, user=$user, database=$db, table=$table_name, io=$io");
			$this->_wizard->$host->$user->$db->table->$table_name = $io ? true: false;
			$this->model('Log')->Set( $this->pdo()->qu(), $io ? 'green':'red');
		}
		
		return empty($fail) ? true: false;
	}
	
	private function _CreateColumn( Config $config )
	{
		//  Select database
		$this->pdo()->SetDatabase($config->database->database);
		
		foreach( $config->table as $table_name => $table ){
		//	$this->mark('table: '.$table_name);
			
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
			
			//	There is no column to change.
			if( empty($this->_result->column->$table_name) ){
				$this->mark("![.red[$table_name is empty]]");
				continue;
			}
			
			//	drop primary key from table flag
			$drop_pkey = null;
			
			//	create alter
			$create = new Config();
			
			//	change alter
			$change = new Config();
			
			//	result of selftest
			foreach( $this->_result->column->$table_name as $column_name => $value ){
			//	$this->mark('column: '.$column_name);
				
				if( $value === true ){
					continue;
				}else if( $value === false ){
					$this->StackError("Does not execute wizard. ($column_name)");
					continue;
				}
				

				list( $acd, $after ) = explode(',',$value);
				
				//	create or change
				switch( trim($acd) ){
					case 'create':
						
						//	If ai or pkey, than drop pkey
						$ai = isset($config->table->$table_name->column->$column_name->ai) ? $config->table->$table_name->column->$column_name->ai: null;
						$pk = isset($config->table->$table_name->column->$column_name->pkey) ? $config->table->$table_name->column->$column_name->pkey: null;
						if( $ai or $pk ){
							$drop_pkey = true;	
						}
						
						//	after this column
						if( $after ){
							$config->table->$table_name->column->$column_name->after = $after;
						}else{
							$config->table->$table_name->column->$column_name->first = true;
						}
						
						//	join
						$create->column->$column_name = $config->table->$table_name->column->$column_name;
						break;
						
					case 'change':
						//	join
						$change->column->$column_name = $config->table->$table_name->column->$column_name;
						break;
						
					default:
						$this->StackError("ACD is not set.");
				}
				
				//	
				$after = $column_name;
			}
			
			//	drop primary key
			if( $drop_pkey ){
				$io = $this->pdo()->DropPrimaryKey($table_name);
				$this->model('Log')->Set( $this->pdo()->qu(), $io?'green':'red');
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
				$io = $this->pdo()->ChangeColumn($change);
				$this->model('Log')->Set( $this->pdo()->qu(), $io?'green':'red');
			}
		}
		
		return true;
	}
	
	private function _CreateUser($config)
	{
		//	CREATE USER 'new-user-name'@'permit-host-name' IDENTIFIED BY '***';
		$config->user->host     = $config->database->host === 'localhost' ? 'localhost': $_SERVER['SERVER_ADDR'];//$_SERVER['SERVER_ADDR']==='127.0.0.1' ? 'localhost':$_SERVER['SERVER_ADDR']; // $config->database->host; // This is database host name.
		$config->user->user     = $config->database->user;
		$config->user->password = $config->database->password;
		
		//  Check user exists.
		$list = $this->pdo()->GetUserList($config->user->host);
		
		//  Check user exists.
		$io = array_search( $config->user->user, $list ) !== false ? true: false;
		if( $io ){
			//	Logger
			$this->model('Log')->Set("CHECK: {$config->user->user} user is already exists.");
			
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
		
		return $io;
	}
	
	private function _CreateGrant($config)
	{
		//	Check
		if( empty($config->table) ){
			$this->model('Log')->Set('CHECK: Empty table name.',false);
			return true;
		}
		
		//  Create grant
		foreach( $config->table as $table_name => $table ){
			
			if( isset($grant) ){
				unset($grant);
			}
			
			if( isset($revoke) ){
				unset($revoke);
			}
			
			//  Create grant
			$grant = new Config();
			$grant->host     = $host = $config->database->host === 'localhost' ? 'localhost': $_SERVER['SERVER_ADDR']; // $_SERVER['SERVER_ADDR']==='127.0.0.1' ? 'localhost':$_SERVER['SERVER_ADDR']; // $config->database->host; 
			$grant->database = $db   = $config->database->database;
			$grant->user     = $user = $config->database->user;
			
			//	Create revoke
			$revoke = Toolbox::Copy($grant);
			
			//	If connect is successful case
			if( $this->_result->connect->$host->$user->$db !== true ){
				
				//	get user privilege
				$user_priv = $this->pdo()->GetUserPrivilege(array('user'=>$user,'host'=>$host));
				
				//	
				if( $user_priv ){					
					//	Revoke all plivilage
					$revoke->table = $table_name;
					$revoke->privilege = 'ALL PRIVILEGES';
					$io = $this->pdo()->Revoke($revoke);
					
					//  Log (revoke)
					$this->model('Log')->Set( $this->pdo()->qu(), $io);
				}
			}
			
			//	Set table name
			$grant->table = $table_name;
			
			//	If GRANT is present if
			if( isset($table->grant) ){
				//	Do merge
				$grant->merge($table->grant);
			}else{
				//	Create detail privilege
				if( isset($table->privilege) ){
					$grant->privilege = $table->privilege;
				}else{

					//	Build column list
					$column = array();
					foreach( $table->column as $column_name => $temp ){
						$column[] = $column_name;
					}
					
					//	Build privilege by column list
					foreach( array('insert','select','update'/*,'references'*/) as $priv ){
						$grant->privilege->$priv = join(',',$column);
					}
				}
			}
			
			//	
			if( $io = $this->pdo()->Grant($grant) ){
				$fail = true;
			}
			
			//  Log
			$this->model('Log')->Set( $this->pdo()->qu(), $io);
			
			//	Revoke timestamp
			/*
			unset($revoke->privilage);
			$revoke->privilage->update = 'timestamp';
			$io = $this->pdo()->Revoke($revoke);
			$this->model('Log')->Set( $this->pdo()->qu(), $io);
			*/
		}
		
		return empty($fail) ? true: false;
	}
}

/**
 * WizardConfig
 *
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 */
class WizardConfig extends OnePiece5
{
	const _FORM_NAME_ = 'op_magic_form';
	const _INPUT_USERNAME_ = 'username';
	const _INPUT_PASSWORD_ = 'password';
	const _INPUT_SUBMIT_   = 'submit';
	
	function GetFormName()
	{
		return self::_FORM_NAME_;
	}
	
	function WizardForm()
	{
		$config = new Config();
		
		//  form name
		$config->name  = self::_FORM_NAME_;
		$config->id    = 'form-wizard';
		$config->class = 'form-wizard';
		
		//  user
		$input_name = self::_INPUT_USERNAME_;
		$config->input->$input_name->label = 'User';
		$config->input->$input_name->name  = $input_name;
		$config->input->$input_name->value = 'root';
		$config->input->$input_name->validate->required = true;
		
		//  password
		$input_name = self::_INPUT_PASSWORD_;
		$config->input->$input_name->label = 'Password';
		$config->input->$input_name->name  = $input_name;
		$config->input->$input_name->type  = 'password';
		
		//  submit
		$input_name = 'submit';
		$config->input->$input_name->label = '';
		$config->input->$input_name->name  = $input_name;
		$config->input->$input_name->type  = 'submit';
		$config->input->$input_name->value = ' Execute to Wizard ';
		
		return $config;
	}
}

/**
 * WizardHelper
 * 
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 */
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

