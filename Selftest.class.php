<?php
/**
 * Selftest.class.php
 * 
 * Creation: 2014-09-16
 * 
 * @version   1.0
 * @package   op-core
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2014 (C) Tomoaki Nagahara All right reserved.
 */

/**
 * Selftest
 * 
 * Creation: 2014-09-16
 *
 * @version   1.0
 * @package   op-core
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2014 (C) Tomoaki Nagahara All right reserved.
 */
class Selftest extends OnePiece5
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
	
	function Init()
	{
		parent::Init();
		if(!$this->Admin()){
			$this->StackError("Not admin call.");
		}
	}
	
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
		$this->_blueprint->index->add  = array();
		$this->_blueprint->index->drop = array();
		$this->_blueprint->index->pkey = array();
	}
	
	private function _Log($message, $result=null, $from='en')
	{
		//	Generate log array.
		$log = array();
		$log['from']	 = $from;
		$log['result']	 = $result;
		$log['message']	 = $message;
		
		//	Stack log array.
		$this->_log[] = $log;
	}
	
	function PrintLog()
	{
		$this->p("![.bold .bigger[Display Selftest's diagnosis log:]] ![.gray .small[".$this->GetCallerLine()."]]");
		if( $this->_is_diagnosis ){
			$class = 'green';
			$message = "Diagnostic results was no problem.";
		}else{
			$class = 'red';
			$message = "Diagnostic results has problem.";
		}
		$message = $this->i18n()->Bulk($message,'en');
		$this->p("![.{$class} margin:1em [$message]]");
		
		print '<ol>';
		while($log = array_shift($this->_log)){
			$from	 = $log['from'];
			$result  = $log['result'];
			$message = $log['message'];
			$translate = $this->i18n()->Bulk($message, $from);
			if( $result === null ){
				$class = 'gray';
			}else if( is_bool($result) ){
				$class = $result ? 'blue': 'red';
			}else{
				$class = $result;
			}
			print $this->p("![li .small .{$class}[{$translate}]]");
		}
		print '</ol>';
	}
	
	function Root($password, $root_user='root')
	{
		$this->_log('Set the Root user and password.');
		$this->_root_user     = $root_user;
		$this->_root_password = $password;
	}
	
	private $_selftest_config;
	
	function SetSelftestConfig( $class_name, Config $config )
	{
		if( isset($this->_selftest_config[$class_name]) ){
			$this->StackError("This class's self-test config is already exists.");
			return;
		}
		$this->_selftest_config[$class_name] = clone($config);
	}
	
	function GetSelftestConfig()
	{
		return $this->_selftest_config;
	}
	
	function ClearSelftestConfig()
	{
		$this->_selftest_config = null;
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
	
	function Diagnose($root=null)
	{
		$this->InitDiagnosis();
		
		//	each per config.
		foreach( $this->GetSelftestConfig() as $class_name => $config ){
			
			//	Set config. (Is this required?)
			$this->_blueprint->config->$class_name = Toolbox::toObject($config);
			
			//	Diagnosis
			$this->CheckConnection($config);
			$this->CheckDatabase($config);
			$this->CheckTable($config);
			$this->CheckColumn($config);
			$this->CheckPkey($config);
		}
		
		return $this->_is_diagnosis;
	}
	
	function CheckConnection($config)
	{
		//	Database connection config.
		$database = clone($config->database);
		
		//	Set root and password..
		if( $this->_root_user ){
			$database->user     = $this->_root_user;
			$database->password = $this->_root_password;
		}
		
		//	Connection
		$io	 = $this->PDO()->Connect($database);
		$dsn = $this->PDO()->GetDSN();
		$user = $database->user;
		
		//	Write diagnosis
		$this->_diagnosis->$user->$dsn->connection = $io;
		
		//	return
		if(!$io){
			$this->_blueprint->user[] = $user;
			//	Error
			$error = $this->FetchError();
			$this->_log($error['message'],$io);
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
		
		//	result
		if(!$io){
			$this->_is_diagnosis = false;
			$this->_blueprint->database[] = clone($config->database);
		}
		
		//	Diagnosis
		$db_name = $config->database->name;
		$user	 = $config->database->user;
		$dsn	 = $this->PDO()->GetDSN();
		$this->_diagnosis->$user->$dsn->database->$db_name = $io;
	}
	
	function CheckTable($config)
	{
		//	init
		$database = clone($config->database);
		$db_name = $database->name;
		$user	 = $database->user;
		$dsn	 = $this->PDO()->GetDSN();
		
		//	Get a list of the found table in this connection.
		$is_connection = $this->_diagnosis->$user->$dsn->connection;
		
		//	This connection's found list.
		if( $is_connection ){
			$table_list = $this->PDO()->GetTableList($db_name);
		}else{
			$table_list = array();
		}
		
		//	Check each table.
		foreach( $config->table as $table_name => $table ){
			$table = clone($table);
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
				$renamed = in_array($table->renamed, $table_list);
			}
			
			//	Write blueprint.
			$this->WriteTable($table, $renamed);
			$this->WriteGrant($database, $table);
		}
	}
	
	function CheckColumn($config)
	{
		//	Init
		$db_name = $config->database->name;
		$user	 = $config->database->user;
		$dsn	 = $this->PDO()->GetDSN();
		
		//	Check each table.
		foreach($config->table as $table_name => $original){
			$table = clone($original);
			
			//	Check table exists.
			$join_name = $db_name.'.'.$table_name;
			if(!$this->_diagnosis->$user->$dsn->table->$join_name){
				return;
			}
			
			//	Check table's each column.
			$table->database = $db_name;
			$table->name = $table_name;
			
			$this->CheckColumnEach($user, $db_name, $table);
			$this->CheckColumnIndex($user, $db_name, $table);
		}
	}
	
	function CheckColumnEach($user, $db_name, $table)
	{
		//	Init
		$dsn	 = $this->PDO()->GetDSN();
		$table_name = $table->name;
		$join_name = $db_name.'.'.$table_name;
		$after = null;
		
		//	Table had no problem.
		if( $this->_diagnosis->$user->$dsn->table->$join_name !== true ){
		//	$this->mark("Does not check column of $table_name. ($table_name table have failed to check.)");
			return;
		}
		
		//	
		if(!isset($table->column)){
		//	$this->mark("Does not check column of $table_name. ($table_name table does not been set column.)");
			return;
		}
		
		//	Get column struct.
		$struct = $this->PDO()->GetTableStruct($table_name, $db_name);
		
		//	Check each column(exists).
		$columns = array();
		foreach($table->column as $column_name => $column){
			$column = clone($column);
			$columns[$column_name] = true;
			
			//	Compensate name.
			$table->name  = $table_name;
			$column->name = $column_name;
			
			//	Check column exists.
			if( array_key_exists($column_name, $struct) ){
				
				//	Check column's struct.
				$this->CheckColumnEachStruct($user, $db_name, $table_name, $column, $struct[$column_name]);
				
			}else{
				//	Fail
				$this->_is_diagnosis = false;
				
				//	Diagnosis
				$this->_diagnosis->$user->$dsn->column->$join_name->$column_name = false;
				
				//	Alter is add or rename.
				$acmd = empty($column->renamed) ? 'add': 'change'; 
				
				//	Add new column.
				$column->after = $after;
				$this->WriteAlter($db_name, $table_name, $column, $acmd);
			}
			
			//	After where column.
			$after = $column_name;
		}
		
		//	This column is not used.
		$temp = array_diff_key( $struct, $columns );
		foreach($temp as $column_name => $column){
			$this->_diagnosis->$user->$dsn->column->$join_name->$column_name = null;
		}
	}
	
	function CheckColumnEachStruct($user, $db_name, $table_name, $column, $struct)
	{
		//	Init
		$dsn	 = $this->PDO()->GetDSN();
		$column_name = $column->name;
		$join_name = $db_name.'.'.$table_name;
		
		//	Each check.
		foreach($column as $key => $var){
			switch($key){
				case 'name':
				case 'ai':
				case 'index':
				case 'pkey':
				case 'unique':
				case 'renamed':
					$continue = true;
					break;
				default:
					$continue = false;
			}
			
			if( $continue ){
				continue;
			}
			
			if(!isset($struct[$key])){
				$this->StackError("This key has not been set. \($key)\\",'en');
				continue;
			}
			
			//	Save column type.
			if( $key === 'type' ){
				$type = $var;
			}
			
			//	Evaluation
			if( ($type === 'enum' or $type === 'set') and $key === 'length' ){
				foreach(explode(',',$var) as $temp){
					$arr1[] = trim($temp);
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
			
			//	Write blueprint
			if(!$io){
				$this->_is_diagnosis = false;
				$this->WriteAlter($db_name, $table_name, $column, 'modify');
			}
		}
	}
	
	function CheckColumnIndex($user, $db_name, $table)
	{
		//	Init
		$dsn = $this->PDO()->GetDSN();
		$table_name	 = $table->name;
		$join_name	 = $db_name.'.'.$table_name;
		
		//	Get column struct.
		$struct = $this->PDO()->GetTableStruct($table_name, $db_name);
		
		//	Check each column.
		foreach($table->column as $column_name => $column){
			if( empty($this->_diagnosis->$user->$dsn->column->$join_name->$column_name) ){
				$struct[$column_name]['key'] = '';
			}
			
			//	Get column's key.
			$key = $this->_ConvertColumnKey($column);
			
			//	Evaluation
			$io = $key === $struct[$column_name]['key'];
			
			//	Diagnosis (In case of not empty)
			if( !empty($key) or !empty($struct[$column_name]['key']) ){
				//	In case of boolean. (Column is not exists in table)
				if(!is_bool($this->_diagnosis->$user->$dsn->column->$join_name->$column_name)){
					if(!$key){ $key = $struct[$column_name]['key']; /* Supplement index name */ }
					//	Write
					$this->_diagnosis->$user->$dsn->column->$join_name->$column_name->$key = $io;
				}
			}
			
			//	Save the auto increment column.
			if(!empty($column->ai)){
				$this->_diagnosis->$user->$dsn->ai->$table_name->$column_name = $io;
			}
			
			//	Save the column name with primary key value.
			if( $key === 'PRI' ){
				$this->_diagnosis->$user->$dsn->pkey->$table_name->$column_name = $io;
			}
			
			//	Nothing problem
			if( $io ){ continue; }
			
			//	Diagnosis
			$this->_is_diagnosis = false;
			
			//	Drop Index
			if( $struct[$column_name]['key'] ){
				$this->WriteIndex($db_name, $table_name, $column_name, $key, 'drop');
			}
			
			//	Add Index
			if( $key !== 'PRI' ){
				$this->WriteIndex($db_name, $table_name, $column_name, $key, 'add');
			}
		}
	}
	
	function CheckPkey($config)
	{
		//	Init
		$dsn = $this->PDO()->GetDSN();
		$user	 = $config->database->user;
		$db_name = $config->database->name;
		
		//	If exists peky?
		if( empty($this->_diagnosis->$user->$dsn->pkey) ){
			return;
		}
		
		//	Loop at each table.
		foreach( $this->_diagnosis->$user->$dsn->pkey as $table_name => $columns ){
			$rebuild = null;
			
			//	Collect column name.
			foreach( $columns as $column_name => $value ){
				$pkeys[] = $column_name;
				if(!$value){
					$rebuild = true;
				}
			}
			
			//	If failed?
			if(!$rebuild){
				continue;
			}
			
			//	Remove auto increment. (Does not drop pkey.)
			foreach( $this->_diagnosis->$user->$dsn->ai->$table_name as $column_name => $value ){
				$column = $config->table->$table_name->column->$column_name;
				$column->name = $column_name;
				$this->WriteAlter($db_name, $table_name, $column, 'modify');
			}
			
			//	Rebuild Primary key.
			$this->WritePKEY($db_name, $table_name, $pkeys);
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
			$key = '';
		}
		
		return $key;
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
		$grant->password = $database->password;
		$grant->privilege = isset($table->privilege) ? $table->privilege: 'select,insert,update';
		
		//	Stack grant config.
		$this->_blueprint->grant[] = $grant;
	}
	
	/**
	 * Write create table config.
	 * 
	 * @param Config $table    Table define.
	 */
	function WriteTable($table, $renamed=null)
	{
		if( empty($table->column) ){
			$this->mark("Table name \\$table->name\ will skip the write to \blueprint\. (column is empty)");
			return;
		}
		
		//	Remove index property.
		foreach( $table->column as $column_name => $column ){
			unset($column->ai);
			unset($column->pkey);
			unset($column->index);
			unset($column->unique);
		}
		
		if( $renamed ){
			$table->rename = $table->name;
			$table->name = $table->renamed;
			unset($table->renamed);
		}
		
		//	Touch table name.
		$table->table = $table->name;
		unset($table->name);
		
		//	Stack create table config.
		$this->_blueprint->table[] = $table;
	}
	
	/**
	 * Write alter table config.
	 *
	 * @param Config $database Database connection information.
	 * @param Config $table    Table define.
	 */
	function WriteAlter($database_name, $table_name, $column, $acmd)
	{
		if( $acmd === 'change' ){
			$column->rename = $column->name;
			$column->name = $column->renamed;
		}
		
		$column_name = $column->name;
		unset($column->name);
		unset($column->ai);
		unset($column->pkey);
		unset($column->index);
		unset($column->unique);
		
		$alter = new Config();
		$alter->database = $database_name;
		$alter->table	 = $table_name;
		$alter->column->$acmd->{$column_name} = clone($column);
		
		//	Stack create table config.
		$this->_blueprint->alter[] = $alter;
	}
	
	function WriteIndex($database_name, $table_name, $column_name, $key, $acd)
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
		}
		
		$alter = new Config();
		$alter->database = $database_name;
		$alter->table	 = $table_name;
		$alter->column	 = $column_name;
		$alter->type	 = $type;
		
		$this->_blueprint->index->{$acd}[] = $alter;
	}
	
	function WritePKEY($database_name, $table_name, $column)
	{
		$alter = new Config();
		$alter->database = $database_name;
		$alter->table	 = $table_name;
		$alter->column	 = $column;
		
		$this->_blueprint->index->pkey[] = $alter;
	}
}
