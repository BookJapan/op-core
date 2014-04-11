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
	private $config = null;  // what is this?
	private $_result = null; // check's result
	private $_wizard = null; // do wizard's result
	private $_status = null; //	Wizard status
	
	/**
	 * Execution flag of Wizard.
	 * 
	 * @var boolean
	 */
	private $_isWizard = null;
	
	function __destruct()
	{
		if( $this->Admin() ){
			if(!$this->_isWizard){
				/*
				if( Toolbox::GetMIME(true) === 'html' ){
					$this->Selftest();
				}
				*/
			//	$this->StackError('Please Execute Selftest at Wizard.');
			}
		}
		parent::__destruct();
	}
	
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
	
	function isWizard( $io=null )
	{
		if( $io ){
			$this->_isWizard = $io;
		}
		return $this->_isWizard;
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
	
	/**
	 * Execution of Wizard. 
	 * 
	 * @return NULL|boolean
	 */
	public function Selftest()
	{
		if( Env::Get('is_wizard') ){
			$this->mark("Wizard is already executing.");
		}
		Env::Set('is_wizard',true);
		
		
		if( $this->_isWizard ){
			return;
		}
		
		//	Set flag
		$this->_isWizard = true;
		
		//	Check admin
		if(!$this->admin()){
			return null;
		}
		
		//	init
		$this->_result = new Config();
		$this->_wizard = new Config();
		
		if( $selftest = $this->GetSession('selftest') ){
			
			//  Init form config.
			$this->form()->AddForm( $this->config()->MagicForm() );
						
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
					$this->mark($e->getMessage());
					$io = false;
				}
				
				//	Set Cache
				$this->Cache()->Set($key,$io);
				
				if(!$io){
					$do_wizard = true;
					$config_list[] = $config;
				}
			}

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
		}else{
		//	$this->form()->Debug($this->config()->GetFormName());
		}
		
		//	check
	//	$this->mark('wizard is successful.');
	//	$this->d( $this->_result );
	//	$this->d( $this->_wizard );
		
		//	re save
		$this->SetSession('selftest', $selftest);
		
		//	status
		if( $this->_status ){
			$this->mark( $this->_status );
		}
		
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
		$dbms  = $config->database->driver;
		$host  = $config->database->host;
		$port  = $config->database->port;
		$user  = $config->database->user;
		$db    = $config->database->database;
		$host .= $port == 3306 ? '': ' : '.$port;
		
		//	Database connection test
		$io = $this->pdo()->Connect($config->database);
		
		//	Save result of connection
		$this->_result->connect->$host->$user->$db = $io;
		
		if(!$io){
			$this->model('Log')->Set("FAILED: Database connect is failed.(host=$host, user=$user, db=$db)",false);
		//	$this->mark("Failed database connect. (host=$host, user=$user, db=$db)");
			return false;
		}
		
		if(!$this->_CheckDatabase($config)){
			$this->model('Log')->Set("FAILED: Database check. (host=$host, user=$user, db=$db)",false);
		//	$this->mark("Failed database check. (host=$host, user=$user, db=$db)");
			return false;
		}
		
		if(!$this->_CheckTable($config)){
		//	$this->model('Log')->Set("FAILED: Table check.(host=$host, user=$user, db=$db)",false);
		//	$this->mark("Failed table check. (host=$host, user=$user, db=$db)");
			return false;
		}
		
		return true;
	}
	
	private function _Wizard( $config_list )
	{
		//  Get form name.
		$form_name = $this->config()->GetFormName();
		
		//  Check secure
		if(!$this->form()->Secure($form_name) ){
			$this->_status = $this->form()->GetStatus($form_name);
			
		//	$this->form()->Debug($form_name);
		//	$this->d($_POST);
		}else{
			$this->_status = 'Is secure.';
			
			foreach( $config_list as $config ){
				
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
					$this->D($this->_result,'wizard');
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
		$css = '
		<style>
		#form-wizard{
			margin: 1em;
			border: 0px solid #d8d7da;
			background-Color: #e8e2f3;
			width: 450px;
		}
		
		#form-wizard div.headline{
			padding: 0.5em 1em;
			background-Color: #583e8d;
			color: white;
			font-size: 150%;
			font-weight: bold;
		}
		
		#form-wizard div.content-area{
			margin:  0;
			padding: 0em;
			padding-left: 1em;
			padding-bottom: 1em;
			color: #2b1e44;
			border: 1px solid #d8d7da;
		}
		
		#form-wizard p.message{
			margin: 1em;
			color: #21045a;
			border: 0px solid #d8d7da;
			font-size: 125%;
		}
		
		#form-wizard div.content{
			margin: 1em;
		}
		
		#form-wizard input.op-input{
			border: 1px solid #4c455b;
			width: 16em;
		}
		
		#form-wizard input.op-input-button{
			margin: 0;
			color: white;
			font-weight: bold;
			background-color: #653cb8;
			font-family: sans-serif;
		}
		
		#form-wizard input.op-input-button:hover{
			background-color: #7538ef;
		}
				
		#form-wizard .form-area{
			margin-left: 0px;
			width: 260px;
		}
		</style>
		';
		
		$html = '
		<div id="form-wizard" class="">
			<div class="headline">
				<span>%s</span>
			</div>
			<div class="content-area" style="">
				<p class="message">%s</p>
				<div class="form-area table" style="">
					%s
				</div>
			</div>
		</div>
		';
		
		//	Get form name
		$form_name = $this->config()->GetFormName();
		
		//  Get input decorate
		$decorate = $this->config()->InputDecorate();
		
		//	Get input
		$form = '';
		foreach ( array('user','password','submit') as $input_name ){
			$form .= sprintf(
				$decorate,
				$this->form()->GetLabel($input_name,$form_name),
				$this->form()->GetInput($input_name,$form_name),
				$this->form()->GetError($input_name,$form_name)
			);
		}
		
		//	
		$title	 = $config->title;
		$message = $config->message;
		if(!is_string($title)){ $title = 'Please set "$config->form->title".'; }
		if(!is_string($message)){ $message = 'Please set "$config->form->message".'; }
		
		print $css;
		$this->form()->Start($form_name);
		printf( $html, $title, $message, $form );
		$this->form()->Finish($form_name);
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
		//  Get table-name list.
		if(!$table_list = $this->pdo()->GetTableList($config->database) ){
			
			//	result
			$host = $config->database->host;
			$user = $config->database->user;
			$db   = $config->database->database;
			$this->_result->connect->$host->$user->$db = false;

			//	Logger
			$this->model('Log')->Set('FAILED: '.$this->pdo()->qu(),false);
			$this->model('Log')->Set("FAILED: Does not access database.(host=$host, user=$user, db=$db)",false);
			return false;
		}
		
		//  Loop
		foreach( $config->table as $table_name => $table ){
		//	$this->mark("Check $table_name table",'selftest');
			
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
			
			//  Check column.
			if(!$this->_CheckColumn( $config, $table_name )){
				$fail = true;
			}
		}
		
		return empty($fail) ? true: false;
	}
	
	private function _CheckColumn( Config $config, $table_name )
	{	
		//	return value
		$result = true;
		
		if( $config->table->$table_name->column ){
			//	OK
		}else if( $config->table->$table_name->column === false){
			return true;
		}else{
			$this->mark();
			var_dump($config->table->$table_name->column);
		}
		
		$columns = Toolbox::toArray($config->table->$table_name->column);
		$structs = $this->pdo()->GetTableStruct( $table_name );
		
		//	use create column, new create column is after where column
		$after = null;
		
		//  Check detail
		foreach( $columns as $column_name => $column ){
		//	$this->mark("table=$table_name, column=$column_name",'selftest');

			//	init
			$fail = false;
			
			//	check
			if(empty($column)){
				continue;
			}
			
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
				
				//	Get index type
				$index = isset( $config->table->$table_name->column->$column_name->index) ? 
								$config->table->$table_name->column->$column_name->index: '';
				
				//	Convert index string
				if( $index ){
					switch($index){
						case 'index':
							$index = 'MUL';
							break;
							
						case 'unique':
							$index = 'UNI';
							break;
							
						default:
							$this->mark();
					}
				}else{
					if( !empty($config->table->$table_name->column->$column_name->pkey) or
						!empty($config->table->$table_name->column->$column_name->ai)
						){
						$index = 'PRI';
					}else if(!empty($config->table->$table_name->column->$column_name->unique)){
						$index = 'UNI';
					}
				}
				
				//	Convert config value
				if( $type == 'boolean' ){
					$type =  'tinyint';
				}
				
				//	Convert existing table value
				if( $type === 'enum' or $type === 'set' ){
					
					$length = "'".join("','",array_map('trim',explode(',',$length)))."'";
					
					/*
					if(preg_match( '/^enum\((.+)\)$/', $structs[$column_name]['type'], $match )){
						$join = array();
						foreach( explode(',',$match[1]) as $temp ){
							$join[] = trim($temp,"'");
						}
						$structs[$column_name]['length'] = join(',',$join);
					}
					$structs[$column_name]['type'] = 'enum';
					*/
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
				
				//	Check index
				}else if( $index !== $structs[$column_name]['key'] ){

				//	$this->mark("$column_name=$index, {$structs[$column_name]['key']}");
				//	$config->table->$table_name->column->$column_name->d();
					
					$fail = true;
					$hint = "index=$index not {$structs[$column_name]['key']}";
					
					/*
					$this->mark($hint);
					$this->mark($index);
					$this->d($structs[$column_name]);
					*/
						
				}else{
				//	$fail = false;
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
			
		//	$this->mark("fail=$fail");
		}
		
		//  Finish
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
		$this->mark( $this->pdo()->qu() );
		$this->d($record);
		
		if( is_string($config->privilege) ){
			foreach( explode(',',$config->privilege) as $label ){
				$privilege[$label] = 'Y';
			}
		}else{
			foreach( $config->privilege as $label => $value ){
				$privilege[$label] = $value ? 'Y': 'N';
			}
		}
		
		foreach( $privilege as $priv ){
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
		$this->pdo()->Database($config->database->database);
		
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
			
			//	
			if( empty($this->_result->column->$table_name) ){
				$this->mark("![.red[$table_name is empty?]]");
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
		//  Inist
		$config->user->host     = $config->database->host;
		$config->user->user     = $config->database->user;
		$config->user->password = $config->database->password;
		
		//  Check user exists.
		$list = $this->pdo()->GetUserList();
		
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
			$grant->host     = $host = $config->database->host;
			$grant->database = $db   = $config->database->database;
			$grant->user     = $user = $config->database->user;
			
			//	Create revoke
			$revoke = Toolbox::Copy($grant);
			
			//	If connect is successful case
			if( $this->_result->connect->$host->$user->$db === true ){
				//	Does not revoke alter.
			}else{
				//	Revoke all plivilage
				$revoke->table = $table_name;
				$revoke->privilage = 'ALL PRIVILEGES';
				$io = $this->pdo()->Revoke($revoke);
				
				//  Log (revoke)
				$this->model('Log')->Set( $this->pdo()->qu(), $io);
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
		$config->input->$input_name->value = ' Execute to Wizard ';
		
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

