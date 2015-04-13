<?php
/**
 * Doctor.class.php
 * 
 * @creation  2014-09-16
 * @version   1.0
 * @package   op-core
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2014 (C) Tomoaki Nagahara All right reserved.
 */
/**
 * Doctor
 * 
 * @creation  2014-09-16
 * @version   1.0
 * @package   op-core
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2014 (C) Tomoaki Nagahara All right reserved.
 */
class Doctor extends OnePiece5
{
	/**
	 * 
	 */
	const _KEY_SELFTEST_ = 'SELFTEST_CONFIG';
	
	/**
	 * OnePiece's PDO5 object
	 * 
	 * @var PDO5
	 */
	private $_pdo;
	
	/**
	 * Diagnosis.
	 * 
	 * @var Config
	 */
	private $_diagnosis;
	
	/**
	 * Is diagnosis.
	 * 
	 * @var boolean
	 */
	private $_is_diagnosis = null; // is_healthy
	
	/**
	 * Blue print
	 * 
	 * @var Config
	 */
	private $_blueprint;
	
	/**
	 * Root user's name.
	 * 
	 * @var string
	 */
	private $_root_user;
	
	/**
	 * Root user's password.
	 * 
	 * @var string
	 */
	private $_root_password;
	
	/**
	 * Stack log.
	 *
	 * @var array
	 */
	private $_log;
	
	/**
	 * Stack class name.
	 * 
	 * @var array
	 */
	private $_registration = array();
	
	/**
	 * Stack self-test config.
	 * 
	 * @var array
	 */
	private $_selftest_config = array();
	
	/**
	 * Operation
	 *
	 * @return DoctorX
	 */
	function Operation()
	{
		return $this->Singleton('DoctorX');
	}
	
	/**
	 * (non-PHPdoc)
	 * @see OnePiece5::Init()
	 */
	function Init()
	{
		parent::Init();
		if(!$this->Admin()){
			$this->StackError("You are not an administrator.",'en');
		}
	}
	
	/**
	 * Init diagnosis Config.
	 */
	function InitDiagnosis()
	{
		$this->_is_diagnosis = true;
		
		$this->_log = array();
		
		$this->_diagnosis = new Config();
		$this->_blueprint = new Config();
		$this->_blueprint->grant	 = array();
		$this->_blueprint->user		 = array();
		$this->_blueprint->database	 = array();
		$this->_blueprint->table	 = array();
		$this->_blueprint->column	 = array();
		$this->_blueprint->alter	 = array();
		$this->_blueprint->index->add	 = array();
		$this->_blueprint->index->drop	 = array();
		$this->_blueprint->pkey->drop	 = array();
		$this->_blueprint->pkey->add	 = array();
		$this->_blueprint->ai = array();
	}
	
	/**
	 * Set root user name and password.
	 *
	 * @param string $user
	 * @param string $password
	 */
	function Root($user, $password)
	{
		$this->_root_user     = $user;
		$this->_root_password = $password;
	}
	
	/**
	 * Registaration class name.
	 *
	 * @param string $name
	 */
	function Registration($label, Config $config)
	{
		//	Init config.
		$config = Toolbox::toConfig($config);
		$config = $config->Copy();
		
		//	Pre process.
		$this->_registaration($config);
	//	$this->d($config->table);
		$this->_selftest_config[$label] = $config;
	}
	
	private function _registaration($config)
	{
		//	Database name.
		if( empty($config->database->name) ){
			if( empty($config->database->database) ){
				$this->StackError("Empty database name.");
				return;
			}
			$config->database->name = $config->database->database;
		}
		
		//	Pkey
		foreach( $config->table as $table_name => $table ){
			//	Check config object.
			if(!$table instanceof Config){
				unset($config->table->$table_name);
				$this->StackError("This table configuration is not a \config\ object. \($table_name)\\",'en');
				continue;
			}
			
			//	Each column.
			foreach( $table->column as $column_name => $column ){
				//	Check config object.
				if(!$column instanceof Config){
					unset($table->column->$column_name);
					$this->StackError("This column configuration is not a \config\ object. \({$table_name}.{$column_name})\\",'en');
					continue;
				}
				
				//	Convert.
				$this->_key($column);
			}
		}
	}
	
	private function _Key($column)
	{
		if( !empty($column->pkey) or !empty($column->ai) ){
			$key = 'PRI';
		}else if(!empty($column->unique)){
			unset($column->unique);
			$key = 'UNI';
		}else if(!empty($column->index)){
			switch( strtoupper($column->index) ){
				case 'PRI':
				case 'PRIMARY':
				case 'PKEY':
				case 'PRIMARY_KEY':
					$key = 'PRI';
					$column->pkey = true;
					break;
				case 'UNI':
				case 'UNIQUE':
					$key = 'UNI';
					break;
				default:
					$key = 'MUL';
			}
			unset($column->index);
		}
		
		//	Set key.
		if( isset($key) ){
			$column->key = $key;
		}
	}
	
	function GetBlueprint()
	{
		return $this->_blueprint;
	}
	
	function GetDiagnosis()
	{
		return $this->_diagnosis;
	}
	
	function isDiagnosis()
	{
		if( $this->_root_user ){
			$io = false;
		}else{
			$io = $this->_is_diagnosis;
		}
		return $this->_is_diagnosis;
	}
	
	function Diagnose($password=null, $user='root')
	{
		//	Set Root user's password.
		if( $password ){
			$this->Root($user, $password);
		}
		
		//	Init diagnosis.
		$this->InitDiagnosis();
		
		//	Each per config.
		foreach( $this->_selftest_config as $label => $origin ){
			//	Clone of nesting property.
			$config = $origin->Copy();
			
			//	Set config. (Is this required?)
			$this->_blueprint->config->$label = Toolbox::toObject($config);
			
			//	Set root and password.
			if( $this->_root_user ){
				
				//	Change root user name and password.
				$config->database->user     = $this->_root_user;
				$config->database->password = $this->_root_password;
				
				//	Root user's Diagnosis.
				$this->CheckConnection($config);
				$this->CheckUser($origin);
				
				//	Grant will done every time.
				foreach( $config->table as $table_name => $table ){
					$table = $table->Copy();
					$table->name = $table_name;
					$this->WriteGrant($origin->database, $table);
				}
			}
			
			//	Diagnosis.
			$this->CheckConnection($config);
			$this->CheckDatabase($config);
			$this->CheckTable($config);
			$this->CheckColumn($config);
		}
		
		$this->CheckPkey();
		$this->CheckPkeyDrop();
		$this->CehckAutoIncrement();
		
		return $this->_is_diagnosis;
	}
	
	function CheckConnection($config)
	{
		//	Database connection config.
		$database = $config->database->Copy();
		unset($database->database);
		unset($database->name);
		
		//	Connection
		$io   = $this->PDO()->Connect($database);
		$dsn  = $this->PDO()->GetDSN();
		$user = $database->user;
		
		//	Write diagnosis
		$this->_diagnosis->$user->$dsn->connection = $io;
		
		//	return
		if(!$io){
			//	Error
			$error = $this->FetchError();
			$this->_log($error['message'],$io);
		}
	}
	
	function CheckUser($config)
	{
		$user_list = $this->PDO()->GetUserList();
		if(!in_array("{$config->database->user}@{$config->database->host}", $user_list)){
			$this->WriteUser($config->database);
		}
	}
	
	function CheckDatabase($config)
	{
		//	Connection
		if( $io = $this->PDO()->isConnect() ){
			$db_name = $config->database->name;
			$db_list = $this->PDO()->GetDatabaseList($config);
			$io = in_array($db_name, $db_list);
		}
		
		//	Diagnosis
		$db_name = $config->database->name;
		$user	 = $config->database->user;
		$dsn	 = $this->PDO()->GetDSN();
		$this->_diagnosis->$user->$dsn->database->$db_name = $io;
		
		//	Blueprint
		if(!$io){
			$this->_is_diagnosis = false;
			$this->_blueprint->database[] = $config->database->Copy();
			return;
		}
		
		//	Database select.
		$charset = isset($config->database->charset) ? $config->database->charset: null;
		$this->PDO()->SetDatabase($db_name, $charset);
	}
	
	function CheckTable($config)
	{
		//	init
		$database = $config->database->Copy();
		$db_name = $database->name;
		$user	 = $database->user;
		$dsn	 = $this->PDO()->GetDSN();
		
		//	Does not connection.
		if( empty($this->_diagnosis->$user->$dsn->connection) ){
			$table_list = array();
		}else 
		
		//	Does not exists dabatabse
		if( empty($this->_diagnosis->$user->$dsn->database->$db_name) ){
			$table_list = array();
		}
		
		else{
			$table_list = $this->PDO()->GetTableList($db_name);
		}
		
		if(!is_array($table_list)){
			//	Error process.
			$error = $this->FetchError();
			$this->_log($error['message'],false);
			return;
		}
		
		//	Check each table.
		foreach( $config->table as $table_name => $table ){
			$table = $table->Copy();
			$join_name = $db_name.'.'.$table_name;
			
			//	Check table exists.
			$io = in_array($table_name, $table_list);
			
			//	Write diagnosis.
			$this->_diagnosis->$user->$dsn->table->$join_name = $io;
			
			//	In case of success to continue.
			if( $io ){ continue; }
			
			//	Supplement value.
			$table->database = $db_name;
			$table->name = $table_name;
			
			//	Failed.
			$this->_is_diagnosis = false;
			
			//	Is renamed?
			if( isset($table->renamed) ){
				$rename_flag = in_array($table->renamed, $table_list);
			}else{ $rename_flag = null; }
			
			//	Write blueprint.
			$this->WriteTable($table->Copy(), $rename_flag);
			$this->CheckColumnIndex($user, $db_name, $table);
		}
	}
	
	function CheckColumn($config)
	{
		//	Init
		$db_name = $config->database->name;
		$user	 = $config->database->user;
		$dsn	 = $this->PDO()->GetDSN();
		
		//	Check each table.
		foreach($config->table as $table_name => $origin){
			$table = $origin->Copy();
			
			//	Check table exists.
			$join_name = $db_name.'.'.$table_name;
			if(!$this->_diagnosis->$user->$dsn->table->$join_name){
				return;
			}
			
			//	Check table's each column.
			$table->database = $db_name;
			$table->name = $table_name;
			
			//	
			$this->CheckColumnName($user, $db_name, $table);
			$this->CheckColumnIndex($user, $db_name, $table);
		}
	}
	
	function CheckColumnName($user, $db_name, $table)
	{
		//	Init
		$dsn	 = $this->PDO()->GetDSN();
		$table_name = $table->name;
		$join_name = $db_name.'.'.$table_name;
		$i		 = 0;
		$after	 = null;
		$columns = array();
		
		//	Table has already problem.
		if( $this->_diagnosis->$user->$dsn->table->$join_name !== true ){
			return;
		}
		
		//	Only privilege
		if(!isset($table->column)){
			return;
		}
		
		//	Get column struct.
		$struct = $this->PDO()->GetTableStruct($table_name, $db_name);
		
	//	$this->D($struct);
	//	$this->d($table);
		
		//	Check each column(exists).
		foreach($table->column as $column_name => $column){
			//	Count
			$i++;
			
			//	Clone
			$column = $column->Copy();
			
			//	after
			$after = $column_name;
			
			//	Use column diff.
			$columns[$column_name] = true;
			
			//	Compensate name.
			$table->name  = $table_name;
			$column->name = $column_name;
			
			//	Check column exists.
			if( array_key_exists($column_name, $struct) ){
				
				//	Add AI list.
				if( $struct[$column_name]['extra'] === 'auto_increment' ){
					$column->name = $column_name;
					$this->_diagnosis->AI->$db_name->$table_name = $column;
				}
					
				//	Add PRI list.
				if( $struct[$column_name]['key'] === 'PRI' ){
					$this->_diagnosis->PRI->$db_name->$table_name->$column_name = true;
				}
				
				//	Check column's struct.
				$this->CheckColumnStruct($user, $db_name, $table_name, $column, $struct[$column_name]);
				
			}else{
				//	Fail
				$this->_is_diagnosis = false;
				
				//	Diagnosis
				$this->_diagnosis->$user->$dsn->column->$join_name->$column_name = false;
				
				//	Alter is add or rename.
				$acmd = empty($column->renamed) ? 'add': 'change'; 
								
				//	Add new column.
				if( $i === 1 ){
					$column->first = true;
				}else{
					$column->after = $after;
				}
				$this->WriteColumn($db_name, $table_name, $column, $acmd);
			}
			
			//	After where column.
			$after = $column_name;
		}
		
		//	This column is not used.
		foreach(array_diff_key($struct,$columns) as $column_name => $column){
			$this->_diagnosis->$user->$dsn->column->$join_name->$column_name = null;
		}
	}
	
	function CheckColumnStruct($user, $db_name, $table_name, $column, $struct)
	{
		//	Init
		$dsn	 = $this->PDO()->GetDSN();
		$column_name = $column->name;
		$join_name = $db_name.'.'.$table_name;
		
		//	In case of pkey.
		if( !empty($column->pkey) or !empty($column->ai) ){
			if( $column->type === 'varchar' ){
				if( $column->length > 255 ){
					$this->StackError("Primary key's length was too long. Limit 767 bytes. UTF-8 is use 3 byte at 1 character.",'en');
				}
			}
		}
		
		//	Each check.
		foreach($column as $key => $var){
			switch($key){
				case 'key':
				case 'debug':
				case 'name':
				case 'index':
				case 'pkey':
				case 'unique':
				case 'renamed':
					continue 2;
					
				case 'ai':
					$key = 'extra';
					$var = 'auto_increment';
				break;
			}
			
			//	Illigal key name.
			if(!isset($struct[$key])){
				$this->StackError("This key has not been set. \($key)\\",'en');
				continue;
			}
			
			//	In case of NULL
			if( $key === 'null' ){
				$var = $var ? 'YES': 'NO';
			}
			
			//	Evaluation
			if( ($column->type === 'enum' or $column->type === 'set') and $key === 'length' ){
				if( is_string($var) ){
					foreach(explode(',',$var) as $temp){
						$arr1[] = trim($temp);
					}
				}else{
					$arr1 = Toolbox::toArray($var);
				}
				foreach(explode(',',$struct[$key]) as $temp){
					$arr2[] = trim($temp,"'");
				}
				$io = count(array_diff($arr1, $arr2)) === 0 ? true: false;
			}else{
				$io = $struct[$key] == $var ? true: false;
			}
			
			//	Diagnosis
			$this->_diagnosis->$user->$dsn->column->$join_name->$column_name->$key = $io;
			
			//	Auto Increment
			if( $key === 'extra' and $var === 'auto_increment'){
				if( $io === true ){
					$this->_diagnosis->ai->$db_name->$table_name->$column_name = $io;
				}else if(!empty($column->ai)){
					//	Save the auto increment column.
					$this->_is_diagnosis = false;
					$this->_diagnosis->ai->$db_name->$table_name->$column_name = $column->Copy();
					continue;
				}
			}
			
			//	Write blueprint
			if(!$io){
				$this->_is_diagnosis = false;
				$this->WriteColumn($db_name, $table_name, $column, 'modify');
			}
		}
	}
	
	function CheckColumnIndex($user, $db_name, $table)
	{
		//	Init
		$dsn = $this->PDO()->GetDSN();
		$table_name	 = $table->name;
		$join_name	 = $db_name.'.'.$table_name;

		//	Does not connect database.
		if(!$this->_diagnosis->$user->$dsn->connection){
			return;
		}
		
		//	Does not exists database.
		if( empty($this->_diagnosis->$user->$dsn->database->$db_name) ){
			return;
		}
				
		//	Get column struct.
		if(!$struct = $this->PDO()->GetTableStruct($table_name, $db_name)){
			$struct = array();
			
			//	Error
			$error = $this->FetchError();
			$this->_log($error['message'],false);
			return false;
		}
		
		//	Check each column.
		foreach($table->column as $column_name => $column){
			/*
			if( empty($this->_diagnosis->$user->$dsn->column->$join_name->$column_name) ){
				//	Only privirage?
				$struct[$column_name]['key'] = '';
			}
			*/
			
			//	Get column's key.
			$key = $this->_ConvertColumnKey($column);
			
			//	Table has not been created yet.
			if(!isset($struct[$column_name]['key'])){
				if( $key === 'PRI' ){
					continue;
				}
				$struct[$column_name]['key'] = null;
			}
			
			//	Evaluation
			$io = $key == $struct[$column_name]['key'];
			
			//	Diagnosis (In case of not empty)
			if( !empty($key) or !empty($struct[$column_name]['key']) ){
				//	In case of boolean. (Column is not exists in table)
				/*
				if( is_bool($this->_diagnosis->$user->$dsn->column->$join_name->$column_name) ){
					if(!$key){
						//	Supplement index name
						$key = $struct[$column_name]['key'];
					}
				}
				*/
			}else if( empty($key) and empty($struct[$column_name]['key']) ){
				continue;
			}
						
			//	Write
			$key_type = $key ? $key: $struct[$column_name]['key'];
			$this->_diagnosis->$user->$dsn->column->$join_name->$column_name->$key_type = $io;
			
			//	Save the column name with primary key value. and set Add/Drop flag.
			if( $key === 'PRI' ){
				if( empty($column->ai) ){
					//	is not auto increment.
					$flag = empty($column->pkey) ? null: $io;
				}else{
					//	is auto increment.
					$flag = $io;
				}
				//	"true" is pkey is ON. "false" is pkey is OFF. "null" is to OFF from ON.
				$this->_diagnosis->pkey->$db_name->$table_name->$column_name = $flag;
			}
			
			//	Nothing problem
			if( $io ){ continue; }
			
			//	Diagnosis
			$this->_is_diagnosis = false;
						
			//	Primary key is skip.
			if( $key === 'PRI' ){ continue; }
			
			//	Add, Change, Drop
			if(!$key ){
				$this->WriteIndex($db_name, $table_name, $column_name, $struct[$column_name]['key'], 'drop');
			}else if(!$struct[$column_name]['key'] ){
				$this->WriteIndex($db_name, $table_name, $column_name, $key, 'add');
			}else{
				$this->WriteIndex($db_name, $table_name, $column_name, $key, 'drop');
				$this->WriteIndex($db_name, $table_name, $column_name, $key, 'add');
			}
		}
	}
	
	function CheckPkey()
	{
		if( empty($this->_diagnosis->pkey) ){
			return;
		}
		
		foreach( $this->_diagnosis->pkey as $db_name => $table ){
			foreach( $table as $table_name => $column ){
				//	Convert to Array from Config. 
				$pkeys = Toolbox::toArray($column);
				
				//	Check issue.
				if(!in_array(false, $pkeys)){
					continue;
				}
				
				/*
				
				//	Drop flag
				$drop = false;
				
				//	Will drop pkey if in auto increment.  
				if( isset($this->_diagnosis->AI->$db_name->$table_name) ){
					$drop = true;
					//	Remove auto increment if will drop pkey.
					$column = $this->_diagnosis->AI->$db_name->$table_name;
					$this->WriteColumn($db_name, $table_name, $column, 'modify');
				}
				
				//	In case of existing pkey.
				if( in_array(true, $pkeys, true) ){
					$drop = true;
				}
				
				//	Drop existing pkey.
				if( $drop ){
					$this->WritePKEY($db_name, $table_name, null, 'drop');
				}
				
				*/
				
				//	Build primary key.
				$pkeys = array_merge(array_keys($pkeys,true), array_keys($pkeys,false,true));
				$this->WritePKEY($db_name, $table_name, $pkeys,'add');
			}
		}
	}
	
	function CheckPkeyDrop()
	{
		//	Does not add pkey.
		if( empty( $this->_blueprint->pkey->add ) ){
			return;
		}
		
		//	Search pkey.
		foreach( $this->_blueprint->pkey->add as $temp ){
			//	Get table struct.
			$db_name    = $temp->database;
			$table_name = $temp->table;
			$struct = $this->PDO()->GetTableStruct($table_name, $db_name);
			
			foreach($struct as $column_name => $detail){ 
				foreach($detail as $key => $var){
					if( $key === 'key' and $var === 'PRI' ){
						$this->WritePKEY($db_name, $table_name, null, 'drop');
						continue 2;
					}
				}
			}
		}
	}
	
	function CehckAutoIncrement()
	{
		if( empty($this->_diagnosis->ai) ){
			return;
		}
		
		foreach( $this->_diagnosis->ai as $db_name => $table ){
			foreach( $table as $table_name => $column ){
				//	Checking duplicate auto increment.
				$count = 0;
				$column_names = array();
				foreach( $this->_diagnosis->ai->$db_name->$table_name as $column_name => $temp){
					$count++;
					$column_names[] = $column_name;
				}
				if( $count > 1 ){
					$columns = '\\'.join('\ AND \\',$column_names).'\\';
					$this->StackError("Auto increment of $columns is duplicated. \(Database: $db_name, Table: $table_name)\\",'en');
				}
				
				foreach( $column as $column_name => $column ){
					if( $column === true ){
						continue;
					}
					$column->name = $column_name;
					$this->WriteAI($db_name, $table_name, $column);
				}
			}
		}
	}
	
	function _ConvertColumnKey($column)
	{
		if(!empty($column->ai) or !empty($column->pkey)){
			$key = 'PRI';
		}else if(!empty($column->unique)){
			$key = 'UNI';
		}else if(!empty($column->index)){
			switch( strtoupper($column->index) ){
				case 'PRI':
				case 'PRIMARY':
				case 'PKEY':
				case 'PRIMARY_KEY':
					$key = 'PRI';
					break;
				case 'UNIQUE':
					$key = 'UNI';
					break;
				case '1':
					$key = 'MUL';
					break;
				default:
					$this->D($column);
			}
		}else{
			if( isset($column->key) ){
				$key = $column->key;
			}else{
				$key = null;
			}
		}
		
		return $key;
	}
	
	function WriteUser($database)
	{
		$user = new Config();
		$user->host = $database->host;
		$user->user = $database->user;
		$user->password = $database->password;
		$this->_blueprint->user[] = $user;
	}
	
	/**
	 * Grant to each user by table.
	 * 
	 * @param Config $database Database connection information.
	 * @param Config $table    Table define.
	 */
	function WriteGrant( $database, $table )
	{
		//	Generate grant config.
		$grant = new Config;
		$grant->host	 = $database->host;
		$grant->database = $database->name;
		$grant->table	 = isset($table->name) ? $table->name: $table->table;
		$grant->user	 = $database->user;
		$grant->password = isset($database->password) ? $database->password: null;
		$grant->privilege= isset($table->privilege)   ? $table->privilege: 'select,insert,update';
				
		//	Stack grant config.
		$this->_blueprint->grant[] = $grant;
	}
	
	/**
	 * Write create table config.
	 * 
	 * @param Config $table    Table define.
	 */
	function WriteTable($table, $rename_flag=null)
	{
		if( empty($table->column) ){
			$this->mark("Table name {$table->name} will skip the write to blueprint. (column is empty)",__CLASS__);
			return;
		}
		
		//	Remove index property.
		foreach( $table->column as $column_name => $column ){
			/*
			unset($column->ai);
			unset($column->pkey);
			*/
			if(!empty($column->index)){
				$this->WriteIndex($table->database, $table->name, $column_name, $column->index, 'add');
			}
			unset($column->index);
		}
		
		if( $rename_flag ){
			$table->rename = $table->name;
			$table->name = $table->renamed;
			unset($table->renamed);
			unset($table->column);
		}
		
		//	Touch table name.
		$table->table = $table->name;
		unset($table->name);
		
		//	Stack create table config.
		$this->_blueprint->table[] = $table;
	}
	
	/**
	 * Alter each column.
	 * 
	 * @param string $database_name
	 * @param string $table_name
	 * @param Config $column
	 * @param string $acmd
	 */
	function WriteColumn($db_name, $table_name, $column, $verb)
	{
		$column = $column->Copy();
		
		if( $verb === 'change' ){
			$column->rename = $column->name;
			$column->name = $column->renamed;
			
			if( $column->ai ){
				$this->WritePKEY($db_name, $table_name, null, 'drop');
			}
		}else{
			unset($column->ai);
			unset($column->pkey);
			unset($column->index);
			unset($column->unique);
		}
		
		
		$column_name = $column->name;
		
		$alter = new Config();
		$alter->database = $db_name;
		$alter->table	 = $table_name;
		$alter->column->$verb->{(string)$column_name} = $column->Copy();
		
		//	Stack create table config.
		$this->_blueprint->alter[] = $alter;
	}
	
	function WriteIndex($database_name, $table_name, $column_name, $key, $verb)
	{
		switch($key){
			case 'PRI':
				$type = 'PRIMARY KEY';
				break;
			case 'MUL':
				$type = 'INDEX';
				break;
			case 'UNI':
				$type = 'UNIQUE';
				break;
			default:
				$type = null;
		}
		
		$alter = new Config();
		$alter->database = $database_name;
		$alter->table	 = $table_name;
		$alter->column	 = $column_name;
		$alter->type	 = $type;
		$alter->debug = $this->GetCallerLine();
		
		$this->_blueprint->index->{$verb}[] = $alter;
	}
	
	function WritePKEY($db_name, $table_name, $column_names, $modifier)
	{
		if(!$modifier){
			$this->StackError("\$modifier is empty. (add or drop)");
			return;
		}
		
		$alter = new Config();
		$alter->database = $db_name;
		$alter->table	 = $table_name;
		$alter->column	 = $column_names;
		
		$this->_blueprint->pkey->{$modifier}[] = $alter;
	}
	
	function WriteAI($db_name, $table_name, $column)
	{
		//	Get column name.
		$column_name = $column->name;

		//	Supplement.
	//	$column->pkey = false;
		unset($column->name);
		unset($column->renamed);
		
		//	Pkey is already set?
		if( $this->_diagnosis->pkey->$db_name->$table_name->$column_name ){
			//	Will do drop to first.
			$this->WritePKEY($db_name, $table_name, null, 'drop');
		}
		
		//	Build alter config.
		$alter = new Config();
		$alter->database = $db_name;
		$alter->table	 = $table_name;
		$alter->column->modify->$column_name = $column;
		
		$this->_blueprint->ai[] = $alter;
	}
	
	function _Log($message, $result=null, $from='en')
	{
		return $this->_pdo_error_($message, $result, $from);
	}
	
	function _pdo_error_($message, $result=null, $from='en')
	{
		list($message, $query) = explode("\n",$message."\n");
		
		//	Generate log array.
		$log = array();
		$log['from']	 = $from;
		$log['result']	 = $result;
		$log['message']	 = $message;
		$log['query']	 = $query;
		
		//	Stack log array.
		$this->_log[] = $log;
	}
	
	function PrintLog()
	{
		return $this->PrintPdoError();
	}
	
	function PrintPdoError()
	{
		$this->p("![.bold .bigger[Display Selftest's diagnosis log:]]");
		if( $this->_is_diagnosis ){
			$class = 'green';
			$message = "Diagnostic results was no problem.";
		}else{
			$class = 'red';
			$message = "Diagnostic results has problem.";
		}
		$message = $this->i18n()->Bulk($message,'en');
		$this->p("![.{$class} margin:1em [$message]]");
		
		//	Display diagnosis.
		$Poneglyph = new Poneglyph();
		$Poneglyph->Display($this->GetDiagnosis());
		
		print '<ol>';
		while($log = array_shift($this->_log)){
			//	init
			$from	 = $log['from'];
			$result  = $log['result'];
			$message = $log['message'];
			$query	 = $log['query'];
			
			//	translate
			if( $from ){
				$message = $this->i18n()->Bulk($message, $from);
			}
			
			//	class
			if( $result === null ){
				$class = 'gray';
			}else if( is_bool($result) ){
				$class = $result ? 'blue': 'red';
			}else{
				$class = $result;
			}
			
			//	print
			print $this->p("![li .small .{$class}[{$message} \n $query]]");
		}
		print '</ol>';
	}
}


/**
 * Poneglyph
 *
 * Creation: 2015-03-12
 *
 * @version   1.0
 * @package   op-core
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2015 (C) Tomoaki Nagahara All right reserved.
 */
class Poneglyph extends OnePiece5
{
	private $_tank = array();
	
	function Display($diagnosis)
	{
		foreach( $diagnosis as $user => $dsn ){
			//	continue
			if( $user === 'PRI' or $user === 'AI' ){
				continue;
			}
			
			//	continue
			if( $user === 'ai' or $user === 'pkey' ){
				continue;
			}
			
			//	init
			$_tank = array();
			
			//	User
			$this->_tank['users'][$user]['io'] = true;
			
			//	parse dns
			foreach( $dsn as $dsn_key => $dsn_var){
				//	init
				list($product, $temp) = explode(':', $dsn_key);
				list($temp, $host) = explode('=', $temp);
				//	
				foreach($dsn_var as $key => $var){
					//	switch
					switch( $key ){
						case 'connection':
							$this->_tank['users'][$user]['connection']['io'] = $var;
							break;
						case 'database':
							foreach($var as $database => $io){
								$this->_tank['users'][$user]['databases'][$database]['io'] = $io;
								if( $io === false ){
									$this->_tank['users'][$user]['io'] = $io;
								}
							}
							break;
						case 'table':
							foreach($var as $database_table => $io){
								$temp = explode('.', $database_table);
								$table = array_pop($temp);
								$database = join('.',$temp);
								$this->_tank['users'][$user]['databases'][$database]['tables'][$table]['io'] = $io;
								if( $io === false ){
									$this->_tank['users'][$user]['io'] = $io;
									$this->_tank['users'][$user]['databases'][$database]['io'] = $io;
								}
							}
							break;
						case 'column':
							foreach($var as $database_table => $columns){
								$temp = explode('.', $database_table);
								$database = array_shift($temp);
								$table = join('.',$temp);
								
								foreach($columns as $column => $struct){

									if( is_null($struct) ){
										$this->_tank['users'][$user]['databases'][$database]['tables'][$table]['columns'][$column]['io'] = null;
										continue;
									}
									
									if( empty($struct) ){
										$io = false;
										$this->_tank['users'][$user]['io'] = $io;
										$this->_tank['users'][$user]['databases'][$database]['io'] = $io;
										$this->_tank['users'][$user]['databases'][$database]['tables'][$table]['io'] = $io;
										$this->_tank['users'][$user]['databases'][$database]['tables'][$table]['columns'][$column]['io'] = $io;
										continue;
									}
									
									foreach($struct as $attr => $io){
										if( $io === false ){
											$this->_tank['users'][$user]['io'] = $io;
											$this->_tank['users'][$user]['databases'][$database]['io'] = $io;
											$this->_tank['users'][$user]['databases'][$database]['tables'][$table]['io'] = $io;
											$this->_tank['users'][$user]['databases'][$database]['tables'][$table]['columns'][$column]['io'] = $io;
										}
										$this->_tank['users'][$user]['databases'][$database]['tables'][$table]['columns'][$column]['struct'][$attr]['io'] = $io;
									}
								}
							}
							break;
						default:
						$this->Mark($key);
						$this->D($var);
					}				
				}
			} // end dns;
		}
		
		$this->_Ignition();
	}
	
	private function _Ignition()
	{
		if( empty($this->_tank['users']) ){
			return;
		}
		
		echo '<ol>';
		foreach($this->_tank['users'] as $user => $_user){
			echo '<li>';
			$color = $_user['io'] ? 'blue': 'red';
			echo "<span style='color:$color;'>user: $user</span>";
			if( $_user['io'] ){
				continue;
			}
			echo '<ol>';
			$color = $_user['connection']['io'] ? 'blue': 'red';
			echo "<li><span style='color:$color;'>connection</span></li>";
			foreach($_user['databases'] as $database => $_database ){
				$color = $_database['io']  ? 'blue': 'red';
				echo "<li><span style='color:$color;'>database: $database</span></li>";
				if(!isset($_database['tables'])){
					continue;
				}
				echo '<ol>';
				foreach($_database['tables'] as $table => $_table){
					$color = $_table['io']  ? 'blue': 'red';
					echo "<li><span style='color:$color;'>table: $table</span></li>";
					if( $_table['io'] ){
						continue;
					}
					if( empty($_table['columns']) ){
						continue;
					}
					echo '<ol>';
					foreach($_table['columns'] as $column => $_column){
						$color = isset($_column['io']) ? 'red': 'blue';
						echo "<li><span style='color:$color;'>$column</span></li>";
						
						if( !isset($_column['io']) ){
							continue;
						}

						if( $_column['io'] ){
							continue;
						}
						
						if( empty($_column['struct']) ){
							continue;
						}
						
						echo '<ol>';
						foreach($_column['struct'] as $attr => $_attr){
							$color = $_attr['io']  ? 'blue': 'red';
							echo "<li><span style='color:$color;'>$attr</span></li>";
						}
						echo '</ol>';
					}
					echo '</ol>';
				}
				echo '</ol>';
			}
			echo '</ol>';
			echo '</li>';
		}
		echo '</ol>';
		
	//	$this->p(__METHOD__);
	//	Dump::D($this->_tank);
	}
}
