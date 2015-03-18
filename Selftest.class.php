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
		$this->_blueprint->index->add	 = array();
		$this->_blueprint->index->drop	 = array();
		$this->_blueprint->index->change = array();
		$this->_blueprint->pkey->drop	 = array();
		$this->_blueprint->pkey->add	 = array();
		$this->_blueprint->ai = array();
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
		
		//	Display diagnosis.
		$Poneglyph = new Poneglyph();
		$Poneglyph->Display($this->GetDiagnosis());
		
		print '<ol>';
		while($log = array_shift($this->_log)){
			//	init
			$from	 = $log['from'];
			$result  = $log['result'];
			$message = $log['message'];
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
			print $this->p("![li .small .{$class}[{$message}]]");
		}
		print '</ol>';
	}
	
	function Root($user, $password)
	{
		$this->_root_user     = $user;
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
		foreach( $this->GetSelftestConfig() as $class_name => $origin ){
			$config = $origin->Copy();
			
			//	Set config. (Is this required?)
			$this->_blueprint->config->$class_name = Toolbox::toObject($config);
			
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
				//	$table = clone($table);
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

		$this->CheckPkey($config);
		$this->CehckAutoIncrement($config);
		
		return $this->_is_diagnosis;
	}
	
	function CheckConnection($config)
	{
		//	Database connection config.
		$database = $config->database->Copy();
		
		//	Connection
		$io	 = $this->PDO()->Connect($database);
		$dsn = $this->PDO()->GetDSN();
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
		
		//	result
		if(!$io){
			$this->_is_diagnosis = false;
			$this->_blueprint->database[] = $config->database->Copy();
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
		$database = $config->database->Copy();
		$db_name = $database->name;
		$user	 = $database->user;
		$dsn	 = $this->PDO()->GetDSN();
		
		//	This connection's found list.
		if( $this->_diagnosis->$user->$dsn->connection === true ){
			if(!$table_list = $this->PDO()->GetTableList($db_name)){
				$table_list = array();
				$error = $this->FetchError();
				$this->mark($error['message'],__CLASS__);
			}
		}else{
			$table_list = array();
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
				$renamed = in_array($table->renamed, $table_list);
			}
			
			//	Write blueprint.
			$this->WriteTable($table, $renamed);
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
			return;
		}
		
		//	
		if(!isset($table->column)){
			return;
		}
		
		//	Get column struct.
		$struct = $this->PDO()->GetTableStruct($table_name, $db_name);
		
		//	Check each column(exists).
		$columns = array();
		foreach($table->column as $column_name => $column){
		//	$column = clone($column);
			$column = $column->Copy();
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
				$this->WriteColumn($db_name, $table_name, $column, $acmd);
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
				case 'debug':
				case 'name':
				case 'index':
				case 'pkey':
				case 'unique':
				case 'renamed':
					$continue = true;
					break;
					
				case 'ai':
					$key = 'extra';
					$var = 'auto_increment';
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
					if(!$key){
						//	Supplement index name
						$key = $struct[$column_name]['key']; 
					}
					//	Write
					$this->_diagnosis->$user->$dsn->column->$join_name->$column_name->$key = $io;
				}
			}
			
			//	Save the column name with primary key value.
			if( $key === 'PRI' ){
				if(empty($column->ai)){
					$this->_diagnosis->pkey->$db_name->$table_name->$column_name = $this->_ConvertColumnKey($column) === 'PRI' ? true: false;
				}else{
					$this->_diagnosis->pkey->$db_name->$table_name->$column_name = $io;
				}
			}
			
			//	Nothing problem
			if( $io ){ continue; }
			
			//	Diagnosis
			$this->_is_diagnosis = false;
			
			//	CheckPKey
			if( $key === 'PRI' ){ continue; }
			
			//	Add, Change, Drop
			if(!$key ){
				$this->WriteIndex($db_name, $table_name, $column_name, $key, 'drop');
			}else if(!$struct[$column_name]['key'] ){
				$this->WriteIndex($db_name, $table_name, $column_name, $key, 'add');
			}else{
				$this->WriteIndex($db_name, $table_name, $column_name, $key, 'change');
			}
		}
	}
	
	function CheckPkey($config)
	{
		if( empty($this->_diagnosis->pkey) ){
			return;
		}
		
		foreach( $this->_diagnosis->pkey as $db_name => $table ){
			foreach( $table as $table_name => $column ){
				$pkeys = Toolbox::toArray($column);
				if(!in_array(false, $pkeys, true)){
					continue;
				}
				
				//	Will add pkey in auto increment.  
				if( empty($this->_diagnosis->ai->$db_name->$table_name) ){
					if( in_array(true, $pkeys, true) ){
						//	Drop existing PKEY.
						$this->WritePKEY($db_name, $table_name, null, 'drop');
					}
					//	Build primary key.
					$this->WritePKEY($db_name, $table_name, array_keys($pkeys,true), 'add');
				}
			}
		}
	}
	
	function CehckAutoIncrement($config)
	{
		if( empty($this->_diagnosis->ai) ){
			return;
		}
		
		foreach( $this->_diagnosis->ai as $db_name => $table ){
			foreach( $table as $table_name => $column ){
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
			$key = '';
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
	function WriteTable($table, $renamed=null)
	{
		if( empty($table->column) ){
			$this->mark("Table name {$table->name} will skip the write to blueprint. (column is empty)",__CLASS__);
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
	 * Alter each column.
	 * 
	 * @param string $database_name
	 * @param string $table_name
	 * @param Config $column
	 * @param string $acmd
	 */
	function WriteColumn($database_name, $table_name, $column, $acmd)
	{
		if( $acmd === 'change' ){
			$column->rename = $column->name;
			$column->name = $column->renamed;
		}
		
		$column_name = $column->name;
		unset($column->name);
		unset($column->renamed);
		unset($column->ai);
		unset($column->pkey);
		unset($column->index);
		unset($column->unique);
		
		$alter = new Config();
		$alter->database = $database_name;
		$alter->table	 = $table_name;
	//	$alter->column->$acmd->{(string)$column_name} = clone($column);
		$alter->column->$acmd->{(string)$column_name} = $column->Copy();
		
		//	Stack create table config.
		$this->_blueprint->alter[] = $alter;
	}
	
	function WriteIndex($database_name, $table_name, $column_name, $key, $acmd)
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
		$alter->debug = $this->GetCallerLine();
		
		$this->_blueprint->index->{$acmd}[] = $alter;
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
		//	Supplement.
		$column_name = $column->name;
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
	function Display($diagnosis)
	{
		print "<ol>";
		foreach( $diagnosis as $user => $dsn ){
			
			if( $user === 'ai' or $user === 'pkey' ){
				continue;
			}
			
			$this->li("User name is \\$user\.");
			
			foreach( $dsn as $dsn_key => $dsn_var){
				
				//	init
				list($product, $temp) = explode(':', $dsn_key);
				list($temp, $host) = explode('=', $temp);
				
				foreach($dsn_var as $key => $var){
					print "<ol>";
					
					//	switch
					switch( $key ){
						case 'connection':
							$this->_connection($host, $var);
							break;
						case 'database':
							break;
						case 'table':
							break;
						case 'column':
							break;
						case 'ai':
							break;
						case 'pkey':
							break;
						default:
						//	$this->d($dsn);
						//	$this->d($dsn_key);
						//	$this->d($dsn_var);
							$this->d($key);
						//	$this->d($var);
					}
					print "</ol>";					
				}
			}
		}
		print "</ol>";
	}
	
	function li($text, $io=null)
	{
		if( $io === null ){
			$class = 'black';
		}else{
			$class = $io === true ? 'blue': 'red';
		}
		
		print "<li>";
		print "<span class=\"$class\">";
		print $this->i18n()->En($text);
		print "</span>";
		print "</li>";
	}
	
	function _connection($host, $io)
	{
		if( $io === true ){
			$text = "Connection to \\$host\ is successful.";
		}else{
			$text = "Connection to \\$host\ is failed.";
		}
		$this->li($text, $io);
	}
}
