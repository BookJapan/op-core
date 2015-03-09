<?php
/**
 * 
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 *
 */
class PDO5 extends OnePiece5
{
	/**
	 * @var PDO
	 */
	private $pdo = null;
	private $dcl = null;
	private $ddl = null;
	private $dml = null;
	private $qu	 = null;
	private $qus = array();
	private $isConnect	 = null;
	private $driver		 = null;
	private $host		 = null;
	private $port		 = null;
	private $user		 = null;
	private $database	 = null;
	private $charset	 = null;
	
	/**
	 * Debug information.
	 */
	function Debug()
	{
		$this->D($this->qus);
	}
	
	/**
	 * Get private PDO instance.
	 * 
	 * @return PDO
	 */
	function Instance()
	{
		return $this->pdo;
	}
	
	/**
	 * 
	 * @param  string $name
	 * @throws OpException
	 * @return DML5
	 */
	function DML( $name='DML5' )
	{
		if( empty($this->dml) ){

			$path  = $this->GetEnv('op-root');
			$path  = rtrim($path,'/').'/';
			$path .= "PDO/$name.class.php";
			$io = include_once($path);
			if(!$io){
				throw new OpException("Include failed.($path)");
			}
			
			//  Init
			$this->dml = new $name();
			$this->dml->SetPDO( $this->pdo, $this->driver );
			$this->dml->InitQuote($this->driver);
		}
		return $this->dml;
	}
	
	/**
	 * 
	 * @param  string $name
	 * @throws OpException
	 * @return DDL5
	 */
	function DDL( $name='DDL5' )
	{
		if( empty($this->ddl) ){
			
			$path  = $this->GetEnv('op-root');
			$path  = rtrim($path,'/').'/';
			$path .= "PDO/$name.class.php";
			$io = include_once($path);
			if(!$io){
				throw new OpException("Include failed.($path)");
			}
			
			//  Init
			$this->ddl = new $name();
			$this->ddl->SetPDO( $this->pdo, $this->driver );
		}
		
		return $this->ddl;
	}
	
	/**
	 * 
	 * @param  string $name
	 * @throws OpException
	 * @return DCL5
	 */
	function DCL( $name='DCL5' )
	{
		if( empty($this->dcl) ){
			
			$path  = $this->GetEnv('op-root');
			$path  = rtrim($path,'/').'/';
			$path .= "PDO/$name.class.php";
			$io = include_once($path);
			if(!$io){
				throw new OpException("Include failed.($path)");
			}
			
			//  Init
			$this->dcl = new $name();
			$this->dcl->SetPDO( $this->pdo, $this->driver );
		}
		
		return $this->dcl;
	}
	
	/**
	 * Set/Get SQL Query string
	 * 
	 * @param  string|null $query
	 * @return string
	 */
	function Qu($qu=null)
	{
		if( $qu ){
			$this->qu = $qu;
			$this->qus[] = $qu;
		}else{
			$qu = $this->qu;
			$this->qu = '';
		}
		
		return $qu;
	}
	
	/**
	 * Get all SQL Query
	 * 
	 * @return array:
	 */
	function Qus()
	{
		return $this->qus;
	}
	
	function Query( $qu, $key=null )
	{
		//	init
		$result = null;
		
		//  Check PDO object
		if(!$this->pdo instanceof PDO ){
			$this->StackError("PDO is not instanced.");
			return false;
		}
		
		//  Save query.
		$this->qu($qu);
		
		//  Execute
		if( $st = $this->pdo->query( $this->qu ) ){
			switch($key){
				case 'alter':
				case 'grant':
				case 'create':
					$result = true;
					break;
					
				case 'count':
					$result = $st->fetch(PDO::FETCH_ASSOC);
					if( isset($result['COUNT(*)']) ){
						$result = $result['COUNT(*)'];
					}else if( isset($result['COUNT()']) ){
						$result = $result['COUNT()'];
					}else{
						$result = false;
					}
					break;
					
				case 'update':
				case 'delete':
					$result = $st->rowCount();
					break;
					
				default:
					$result = $st->fetchAll(PDO::FETCH_ASSOC);
				break;
			}
		}else{
			switch(strtolower($key)){
				case 'use':
					$result = true;
					break;
					
				default:
					//  failed
					$result = false;
					$this->_query_error();
				break;
			}
		}
		
		return $result;
	}
	
	private function _query_error()
	{
		if( $this->driver == 'mysql' ){
			$message = $this->_query_error_mysql();
		}else{
			$temp = $this->pdo->errorInfo();
			$error_id = $temp[0];
			$error_no = $temp[1];
			$message  = $temp[2];
		}
		
		$this->StackError($message.' '.PHP_EOL.$this->qu,'en');
	}

	private function _query_error_mysql()
	{
		$temp = $this->pdo->errorInfo();
		$error_id = $temp[0];
		$error_no = $temp[1];
		$message  = $temp[2]." \#{$error_no}\ ";
		
		switch($error_no){
			case 1064:
				$message  = "You have an error in your SQL syntax. "; 
				$message .= "Check the manual that corresponds to your MySQL server version. ";
				break;
			case 1091:
				$message = "Cannot DROP \PRIMARY KEY\. Check that column/key exists.";
			default:
		}
		
		return $message;
	}
	
	function ConvertCharset( $charset=null )
	{
		if( empty($charset) ){
			$charset = $this->GetEnv('charset');
		} 
		
		switch( $charset ){
			case 'utf8':
				break;
				
			case 'utf-8':
				$charset = 'utf8';
				break;
		
			case 'sjis':
			case 'shift-jis':
			case 'shift_jis':
				$charset = PHP_OS === 'WINNT' ? 'sjis-win': 'sjis';
				break;
					
			case 'euc-jp':
				$charset = PHP_OS === 'WINNT' ? 'eucjpms': 'ujis';
				break;
		}

		return $charset;
	}
	
	function isConnect()
	{
		return $this->isConnect;
	}
	
	/**
	 * Data Source Name
	 * 
	 * @var string
	 */
	private $_dsn;
	
	function GetDSN()
	{
		return $this->_dsn;
	}
	
	function Connect( $config )
	{
		if( is_object($config) ){
			$args = Toolbox::toArray($config);
		}else{
			$args = $config;
		}
		
		//  init
		$this->driver   = isset($args['driver'])   ? $args['driver']   : 'mysql';
		$this->host     = isset($args['host'])     ? $args['host']     : 'localhost';
		$this->port     = isset($args['port'])     ? $args['port']     : '3306';
		$this->user     = isset($args['user'])     ? $args['user']     : null;
		$password       = isset($args['password']) ? $args['password'] : null;
		$this->database = isset($args['database']) ? $args['database'] : null;
		$this->charset  = isset($args['charset'])  ? $args['charset']  : strtolower($this->GetEnv('charset'));
		$options        = array();
		
		//	convert
		if( $this->charset === 'utf-8' ){
			$this->charset = 'utf8';
		}
		
		//	check
		foreach( array('driver','host','user') as $key ){
			if( empty($this->$key) ){
				$this->StackError("\'$key'\ is empty.",'en');
				return false;
			}
		}
		
		try{
			//	Support to PHP 5.1 ( USE db_name is not supports. )
			$db  = $this->database ? 'dbname='.$this->database.';': null;
			if( $this->charset ){
				if( $this->driver === 'mysql' ){
					$options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES '{$this->charset}'";
				}
			}
			
			//	Create DNS
			$this->_dsn = "{$this->driver}:{$db}host={$this->host}";
			$this->Qu($this->_dsn);
			
			//	Instance PDO
			if(!$this->pdo = new PDO( $this->_dsn, $this->user, $password, $options )){
				$this->StackError("Can not connect database. \( {$this->_dsn}, {$this->user} )\ ",'en');
				return false;
			}
		}catch( PDOException $e){
			$file = $e->getFile();
			$line = $e->getLine();
			$text = $e->GetMessage();
			$this->StackError("$file (#$line) $text");
			return false;
		}
		
		//  Database select
		if( $this->database ){
			$this->SetDatabase( $this->database, $this->charset );
		}
		
		//  connected flag
		$this->isConnect = true;
		
		return true;
	}
	
	function Database( $db_name, $charset=null, $locale=null )
	{
		return $this->SetDatabase( $db_name, $charset, $locale );
	}
	
	function SetDatabase( $db_name, $charset=null, $locale=null )
	{
		//	
		if( version_compare(PHP_VERSION, '5.1.0', '<') ){
			$this->mark('This PHP version is not supported?('.PHP_VERSION.')');
		}
		
		if(!is_string($db_name)){
			$type = gettype($db_name);
			$this->StackError('Database name is not string. ($type)');
			return false;
		}
		
		//	Quote
		$db_name = ConfigSQL::Quote( $db_name, $this->driver );
		
		//	Execute
		if( $this->query("USE $db_name",'use') === false){
			$this->StackError('Database select is failed.','en');
			return false;
		}
		
		//	
		if( $charset ){
			$this->SetCharset($charset);
		}
		
		//	
		if( $locale ){
			$this->SetLocale($locale);
		}
		
		return true;
	}
	
	function SetCharset( $charset )
	{
		$charset = $this->ConvertCharset($charset);
		$io = $this->query("SET NAMES '$charset'");
		return $io !== false ? true: false;
	}
	
	function SetLocale( $locale )
	{
		$io = $this->query("SET lc_time_names = '$locale'");
		return $io !== false ? true: false;
	}
	
	function GetDatabaseList()
	{
		//  Which driver
		switch($this->driver){
			case 'mysql':
				$qu = 'SHOW DATABASES';
				break;
			default:
				$this->mark("Does not implements yet. ({$this->driver})");
		}
		
		if( $record = $this->query($qu) ){
			for( $i=0, $c=count($record); $i<$c; $i++ ){
				$result[] = $record[$i]['Database'];
			}
		}else{
			$result = array();
		}
		
		return $result;
	}
	
	function GetTableList($database=null, $like=null)
	{
		if(!is_string($database)){
			if(!is_array($config)){
				$config = Toolbox::toArray($config);
			}
			
			//  set database
			$database = isset($config['database']) ? $config['database']: $this->database;
			
			//  set like
			$like = isset($config['like']) ? $config['like']: null;
		}
		
		//  select database
		if( $database ){
			if(!$this->Database($database)){
				return false;
			}
		}
		
		//	escape
		$database = ConfigSQL::Quote($database, $this->driver);
		
		//	like
		if( $like ){
			if( $this->driver === 'mysql' ){
				$like = str_replace("'",'',$like);
				$like = "LIKE '$like'"; // ConfigSQL::Quote($like, $this->driver);
			}else{
				$like = null;
			}
		}
		
		//  create qu
		$qu = "SHOW TABLES FROM $database $like ";
		
		//  get table list
		if( $record = $this->query($qu) ){
			foreach( $record as $i => $temp ){
				foreach( $temp as $n => $table_name ){
					$result[] = $table_name;
				}
			}
		}else{
			$result = array();
		}
		
		return $result;
	}
	
	/**
	 * Get table struct.
	 * 
	 * @param string $table_name
	 * @param string $database_name
	 * @param string $charset
	 * @return boolean|array
	 */
	function GetTableStruct( $table_name, $db_name=null, $charset=null )
	{
		//  Check table name
		if( !$table_name ){
			$this->StackError("Empty table name.");
			return false;
		}
		
		//  Check database
		if( $db_name ){
			$this->Database( $db_name, $charset );
		}
		
		//	Escape
		$table_name = ConfigSQL::Quote( $table_name, $this->driver );
		
		//  create query
		$qu = "SHOW FULL COLUMNS FROM $table_name";
		
		//  get table struct
		if(!$records = $this->query($qu) ){
			return false;
		}
		
		//  separate length from type
		foreach( $records as $record ){
			$record = array_change_key_case( $record, CASE_LOWER );
			$name = $record['field'];
			
			//	int(11), char(10), enum('A','B'), int(10) unsigned
			if( preg_match("|^([a-z]+)(\(.+\))?(.+)?|i",$record['type'],$match) ){
				$record['type']   = $match[1];
				$record['length'] = isset($match[2]) ? trim($match[2],'()'): null;
				if(isset($match[3])){
					$record['attribute'] = $match[3];
					if( preg_match('/unsigned/',$match[3]) ){
						$record['unsigned'] = true;
					}
					if( preg_match('/zerofill/',$match[3]) ){
						$record['zerofill'] = true;
					} 
				}
			}else{
				$record['type']   = $record['type'];
				$record['length'] = null;
			}
			
			$struct[$name] = $record;
		}
		
		return $struct;
	}
	
	function GetTableDefine( $database=null, $table=null )
	{
		//	select database
		$this->SetDatabase('information_schema');
		
		//	select
		$select = new Config();
		$select->table = 'tables';
		if( $database ){
			$select->where->TABLE_SCHEMA = $database;
		}
		if( $table ){
			$select->where->TABLE_NAME	 = $table;
		}
		$select->cache = $this->Admin() ? false: 60*60*1;
		
		//	fetch
		$record = $this->Select($select);
		
		return $record;
	}
	
	/**
	 * Get table's column name list.
	 * 
	 * @param string $table_name
	 * @param string $database_name
	 * @return boolean|array
	 */
	function GetTableColumn( $table_name, $database_name=null )
	{
		if( $structs = $this->GetTableStruct($table_name, $database_name) ){
			foreach( $structs as $struct ){
				$columns[] = $struct['field'];
			}
		}
		return isset($columns) ? $columns: array();
	}
	
	/**
	 * Get mysql user list.
	 * 
	 * @param  string $host
	 * @return array
	 */
	function GetUserList( $host=null )
	{
		switch($this->driver){
			case 'mysql':
				$database = 'mysql';
				break;
			default:
				return false;
		}
		
		//  Select database
		$this->Database($database);
		
		//  Get users list
		$select = new Config();
		$select->database = $database;
		$select->table  = 'user';
		$select->column = 'User,Host';
		$select->order  = 'User';
		if($host){ $select->where->Host = $host; }
		$record = $this->Select($select);
		
		//  Build result array
		for( $i=0, $c=count($record); $i<$c; $i++ ){
			$Host = $record[$i]['Host'];
			$User = $record[$i]['User'];
			$result[] = $host ? $User: "{$User}@{$Host}";
		}
		
		return $result;
	}
	
	/**
	 * Get user's privilege
	 * 
	 * @param  array $args
	 * @return array
	 */
	function GetUserPrivilege( $args )
	{
		switch( $this->driver ){
			case 'mysql':
				$this->SetDatabase('mysql');
				break;
			default:
				return false;
		}
		
		//	check
		if(!is_array($args)){
			$args = Toolbox::toArray($args);
		}
		
		//	init
		$host     = isset($args['host'])     ? $args['host']    : null; 
		$database = isset($args['database']) ? $args['database']: null; 
		$user     = isset($args['user'])     ? $args['user']    : null;
		
		//  Select database
		$this->Database('mysql');
		
		//  Get users list
		$select = new Config();
		$select->table  = 'tables_priv';
		$select->column = 'Host, Db, User, Table_name, Table_priv, Column_priv';
		if( $host ){ $select->where->Host = $host; }
		if( $database ){ $select->where->Db	 = $database; }
		if( $user ){ $select->where->User = $user; }
		
		$record = $this->select($select);
		$result = array();
		
		foreach( $record as $temp ){
			$host	 = $temp['Host'];
			$db		 = $temp['Db'];
			$user	 = $temp['User'];
			$table	 = $temp['Table_name'];
			
			$table_priv	 = strlen($temp['Table_priv'])>0 ? $temp['Table_priv']: '%';
			$column_priv = $temp['Column_priv'];
			
			foreach(explode(',',$table_priv) as $priv){
				$priv = strtolower($priv);
				$result[$user][$host][$db][$table][$priv] = true;
			}
		}
		
		return $result;
	}
	
	/**
	 * Quick select
	 * 
	 * Case of get record.(all column value)
	 * $this->PDO()->Quick('table_name.id = 1');
	 * 
	 * Case of join table.(all column value)
	 * $this->PDO()->Quick('table_A.id <= table_B.id = 1');
	 * 
	 * Case of get single column value.
	 * $this->PDO()->Quick('email <- table_name.id = 1');
	 * 
	 * @param  string $string
	 * @param  array  $config
	 * @return string|array
	 */
	function Quick( $string, $config=null)
	{
		//	Check memcache
		$chachekey = md5($string.', '.serialize($config));
		if( $record = $this->Cache()->Get($chachekey) ){
			return $record;
		}
		
		//  Get value
	//	if( preg_match('/(.+)[^><=]([=<>]{1,2})(.+)/', $string, $match) ){
		if( preg_match('/(.+)([=<>]{1,2})(.+)/', $string, $match) ){
			$left  = $match[1];
			$ope   = $match[2] === '=' ? null: $match[2].' ';
			$value = $match[3];
		}else{
			$this->StackError("Format error. ($string)");
			return false;
		}
		
		//  Get column
		if( strpos( $left, '<') ){
			list( $column, $location ) = explode('<-', trim($left) );
		}else{
			$location = $left;
			$column = null;
		}
	
		//  Generate define
		$locations = array_reverse( explode('.', trim($location) ) );
		$target   = isset($locations[0]) ? $locations[0]: null;
		$table    = isset($locations[1]) ? $locations[1]: null;
		$database = isset($locations[2]) ? $locations[2]: null;
		$host     = isset($locations[3]) ? $locations[3]: null;
		
		//  Create columns
		if( $column ){
			$columns = explode(',',str_replace(' ', '', $column));
		}else{
			$columns = array();
		}
		
		//  Supports aggregate
		$agg = array();
		$remove = array();
		$recovery = array();
		if( $columns ){
			foreach( $columns as $i => $column ){
				if( preg_match('/^(count|sum|min|max|avg)\(([-_a-z0-9]+)\)$/i', $column, $match) ){
					$agg[$match[1]] = $match[2];
					$remove[] = $match[0];
					$recovery[] = strtoupper($match[1])."({$match[2]})";
				}
			}
		}
		
		//  Create value
		$value = trim($value);
		$value = trim($value,"'");
	
		//  create limit, offset, order
		$limit	 = isset($config->limit)  ? $config->limit:  1;
		$offset	 = isset($config->offset) ? $config->offset: null;
		$order	 = isset($config->order)  ? $config->order:  null;
		$cache	 = isset($config->cache)  ? $config->cache:  1;
		
		//  Create config
		$config = new Config();
		$config->host     = $host;
		$config->database = $database;
		$config->table    = $table;
		$config->column   = array_diff( $columns, $remove );
		$config->agg      = $agg;
		$config->limit    = $limit > 0 ? $limit: null;
		$config->offset   = $offset;
		$config->order    = $order;
		$config->where->$target = $ope.$value;
		$config->cache    = $cache;
	
		//  Fetch record
		$record = $this->Select($config);
		
		//  
		if( $record ){
			//  select columns
			if( $columns = array_merge( $config->column, $recovery ) ){
				if( $limit === 1 ){
					$records[0] = $record;
				}else{
					$records = $record;
				}
				foreach($columns as $column){
					for( $i=0, $count=count($records); $i<$count; $i++ ){
						$return[$i][] = $records[$i][$column];
					}
				}
				//  
				if( $limit === 1 ){ //  limit is 1 (select single record)
					$return = $return[0];
					if( count($return) === 1 ){ // column is 1 (select single column)
						$return = $return[0];
					}
				}
			}else{
				$return = $record;
			}
		}else{
			$return = array();
		}
		
		//	Save to memcache
		$this->Cache()->Set( $chachekey, $return, $cache );
		
		return $return;
	}

	/**
	 * Create database
	 *
	 * @param  array|Config $conf
	 * @return boolean
	 */
	function CreateDatabase( $conf )
	{
		//  object to array
		if(!is_array($conf)){
			$conf = Toolbox::toArray($conf);
		}
		
		//  get select query
		if(!$qu = $this->ddl()->GetCreateDatabase($conf)){
			$this->StackError("![ .red .bold [ Failed GetCreateDatabase-method. ]]");
			return false;
		}
		
		//  execute
		$io = $this->query($qu,'create');
		
		return $io;
	}
	
	/**
	 * Create table
	 *
	 * @param  array|Config $conf
	 * @return boolean
	 */
	function CreateTable( $conf )
	{
		//  object to array
		if(!is_array($conf)){
			$conf = Toolbox::toArray($conf);
		}
		
		//  get select query
		if(!$qu = $this->ddl()->GetCreateTable($conf)){
			return false;
		}
		
		//  execute
		return $this->query( $qu, 'create' );
	}
	
	/**
	 * Create User
	 *
	 * @param  array|Config $conf
	 * @return boolean
	 */
	function CreateUser($conf)
	{
		//  object to array
		if(!is_array($conf)){
			$conf = Toolbox::toArray($conf);
		}
		
		//  get select query
		if(!$qu = $this->ddl()->GetCreateUser($conf)){
			return false;
		}
		
		//  execute
		return $this->query( $qu, 'create' );
	}

	/**
	 * Change password
	 *
	 * @param  array|Config $conf
	 * @return boolean
	 */
	function Password($args)
	{
		//  object to array
		if(!is_array($args)){
			$conf = Toolbox::toArray($args);
		}
		
		//  get select query
		$qu = $this->ddl()->GetPassword($conf);
		
		//  execute
		return $this->query( $qu, 'create' );
	}

	/**
	 * Grant
	 *
	 * @param  array|Config $conf
	 * @return boolean
	 */
	function Grant($conf)
	{
		//  object to array
		if(!is_array($conf)){
			$conf = Toolbox::toArray($conf);
		}
	
		//  get select query
		if(!$qu = $this->dcl()->GetGrant($conf)){
			return false;
		}
		
		//  execute
		return $this->query( $qu, 'grant' );
	}

	/**
	 * Revoke
	 *
	 * @param  array|Config $conf
	 * @return boolean
	 */
	function Revoke($conf)
	{
		//  object to array
		if(!is_array($conf)){
			$conf = Toolbox::toArray($conf);
		}
		
		//  get select query
		if(!$qu = $this->dcl()->GetRevoke($conf)){
			return false;
		}
	
		//  execute
		return $this->query( $qu, 'create' );
	}
	
	/**
	 * Change table or column
	 * 
	 * @param  array|Config $conf
	 * @return boolean
	 */
	function AlterTable($conf)
	{
		//  object to array
		if(!is_array($conf)){
			$conf = Toolbox::toArray($conf);
		}
		
		//  get select query
		if(!$qu = $this->ddl()->GetAlterTable($conf)){
			return false;
		}
		
		//  execute
		return $this->query( $qu, 'create' );
	}
	
	/**
	 * Add new column
	 * 
	 * @param  array|Config $conf
	 * @return boolean|string
	 */
	function AddColumn($conf)
	{
		//  object to array
		if(!is_array($conf)){
			$conf = Toolbox::toArray($conf);
		}
		
		//  Check
		if( isset($conf['add']['column']) ){
			//  OK
		}else{
			if( isset($conf['column']) ){
				$conf['add']['column'] = $conf['column'];
				unset($conf['column']);
			}else{
				$this->StackError('Does not set column.');
				return false;
			}
		}
		
		//  get select query
		if(!$qu = $this->ddl()->GetAlterTable($conf)){
			return false;
		}
		
		//  execute
		return $this->query( $qu, 'alter' );
	}

	/**
	 * Change column
	 *
	 * @param  array|Config $conf
	 * @return boolean|string
	 */
	function ChangeColumn($conf)
	{
		//  object to array
		if(!is_array($conf)){
			$conf = Toolbox::toArray($conf);
		}
	
		//  Check
		if( isset($conf['change']['column']) ){
			//  OK
		}else{
			if( isset($conf['column']) ){
				$conf['change']['column'] = $conf['column'];
				unset($conf['column']);
			}else{
				$this->StackError('Does not set column.');
				return false;
			}
		}
		
		//  get select query
		if(!$qu = $this->ddl()->GetAlterTable($conf)){
			return false;
		}
	
		//  execute
		return $this->query( $qu, 'alter' );
	}
	
	function AddIndex($args)
	{
		if( $qu = $this->DDL()->GetAddIndex(Toolbox::toArray($args)) ){
			$io = $this->Query($qu,'alter');
		}else{
			$io = false;
		}
		return $io;
	}
	
	function DropIndex($args)
	{
		if( $qu = $this->DDL()->GetDropIndex(Toolbox::toArray($args)) ){
			$io = $this->Query($qu,'alter');
		}else{
			$io = false;
		}
		return $io;
	}
	
	function AddPrimaryKey($args, $column=null)
	{
		if( $qu = $this->DDL()->GetAddPrimarykey( Toolbox::toArray($args) )){
			$io = $this->query($qu,'alter');
		}else{
			$io = false;
		}
		return $io;
	}
	
	function DropPrimarykey($args)
	{
		if( $qu = $this->DDL()->GetDropPrimarykey( Toolbox::toArray($args) )){
			$io = $this->query($qu,'alter');
		}else{
			$io = false;
		}
		return $io;
	}
	
	/**
	 * 
	 */
	function Count( $config )
	{
		if(!$this->isConnect){
			$this->StackError("Does not isConnect.");
			return false;
		}
		
		//  object to array
		if(!is_array($config)){
			$config = Toolbox::toArray($config);
		}
		
		//  added count aggregate
		if( empty($config['agg']['count']) ){
			$config['agg']['count'] = '*';
		}
		
		//  get select query
		if(!$qu = $this->dml()->GetSelect($config)){
			return false;
		}
		
		//  execute
		$count = $this->query($qu,'count');
		
		//	check
		if( is_numeric($count) ){
			$count = (int)$count;
		}
		
		return $count;
	}
	
	/**
	 * Save cache key.
	 * 
	 * @var string
	 */
	private $_cache_key;
	
	function CacheDelete($args)
	{
		$key = ConfigSQL::CacheKey($args);
		$io = $this->Cache()->Delete($key);
		return $io;
	}
	
	/**
	 * Select
	 * 
	 * @param  array $args
	 * @return boolean|array
	 */
	function Select( $args )
	{
		if(!$this->isConnect){
			$this->StackError("Not connected. (isConnect is false)",'en');
			return false;
		}
		
		//  Check
		if(!$this->pdo){
			$this->StackError("Does not instanced PDO object.",'en');
			return false;
		}
		
		//	empty config
		if( empty($args) ){
			$this->StackError("Does not set argument. (empty)",'en');
			return false;
		}
		
		//	init saved cache key
		$this->_cache_key = null;
		
		//  object to array
		if(!is_array($args)){
			$config = Toolbox::toArray($args);
		}else{
			$config = $args;
		}
		
		//  Check cache setting.
		if(!empty($config['cache'])){
			
			//	Get cache key
			if( isset( $config['cache_key'] ) ){
				$key = $config['cache_key'];
			}else{
			//	$key = md5(serialize($config));
				$key = ConfigSQL::CacheKey($args);
			}
			
			//	save cache key
			$this->_cache_key = $key;
			
			//	If find cache
			if( $records = $this->Cache()->Get($key) ){
				//	Stack log
				$this->Qu('Cache: '.var_export($config,true));
				//	Print mark
				$this->mark("hit cache!! expire is {$config['cache']}sec.",'cache');
				//	Return cache value
				return $records;
			}
		}
		
		//	FAT-MODE in the case of join table.
		if( empty($config['column']) and strpos($config['table'],'=') ){
			//	init
			if(!isset($config['column'])){ $config['column'] = ''; }
			//	database
			$database = isset($config['table']) ? $config['table']: null;
			//	Does not think use of USING.
			foreach(explode('=',$config['table']) as $temp){
				list($table,$column) = explode('.',trim($temp));
				foreach($this->GetTableColumn($table,$database) as $column ){
					$config['alias']["{$table}.{$column}"] = "{$table}.{$column}";
				}
			}
		}
		
		//	Change database
		if( isset($config['database']) ){
			$this->SetDatabase($config['database']);
		}
		
		//  get select query
		if(!$qu = $this->dml()->GetSelect($config)){
			return false;
		}
		
		//  execute
		$records = $this->query($qu);
		if( $records === false ){
			return false;
		}

		//  if limit is 1
		if( isset($config['limit']) and $config['limit'] == 1){
			if( isset($records[0]) ){
			//	return $records[0];
				$records = $records[0];
			}
		}
		
		//  Check cache setting. If record is empty case does not cache.
		if( isset($key) and !empty($records) ){
			$io = $this->Cache()->Set( $key, $records, (int)$config['cache'] );
		}
		
		//  return to records.
		return $records;
	}
	
	function Insert( $config )
	{
		//  Check
		if(!$this->pdo){
			$this->StackError("Does not instanced PDO object.");
			return false;
		}
		
		//  object to array
		if(!is_array($config)){
			$config = Toolbox::toArray($config);
		}

		//	Change database
		if(isset($config['database'])){
			$this->SetDatabase($config['database']);
		}
		
		//  get query
		if(!$qu = $this->dml()->GetInsert($config)){
			return false;
		}
		
		//  execute
		$io = $this->query($qu,'insert');
		if( $io === false){
			return false;
		}
		
		//  new id
		$id = $this->pdo->lastInsertId(/* $name */);
		
		//	Does not auto increments
		if( $id === '0' ){
			$id = true;
		}
		
		return $id;
	}
	
	function Update($config)
	{
		//  Check
		if(!$this->pdo){
			$this->StackError("Does not instanced PDO object.");
			return false;
		}
		
		//  object to array
		if(!is_array($config)){
			$config = Toolbox::toArray($config);
		}

		//	Change database
		if(isset($config['database'])){
			$this->SetDatabase($config['database']);
		}
		
		//  get query
		if(!$qu = $this->dml()->GetUpdate($config)){
			return false;
		}
		
		//  execute
		$num = $this->query($qu,'update');
		
		return $num;
	}
	
	function Delete( $config )
	{
		//  Check
		if(!$this->pdo){
			$this->StackError("Does not instanced PDO object.");
			return false;
		}
		
		//  object to array
		if(!is_array($config)){
			$config = Toolbox::toArray($config);
		}
		
		//	Change database
		if(isset($config['database'])){
			$this->SetDatabase($config['database']);
		}
		
		//  get query
		if(!$qu = $this->dml()->GetDelete($config)){
			return false;
		}
		
		//  execute
		$num = $this->query($qu,'delete');
		
		return $num;
	}
	
	private $_is_transaction;
	
	function Transaction()
	{
		if( $this->_is_transaction ){
			$this->StackError("TRANSACTION has already started.");
			return false;
		}
		
		if(!$this->pdo->beginTransaction() ){
			$this->StackError("TRANSACTION can not be started.");
			return false;
		}

		$this->qu("START TRANSACTION");
		$this->_is_transaction = true;
		
		return true;
	}
	
	function Rollback()
	{
		if(!$this->_is_transaction ){
			$this->StackError("TRANSACTION is not started.");
			return false;
		}
		
		if(!$this->pdo->rollBack() ){
			$this->StackError("Could not be ROLLBACK.");
			return false;
		}
		
		$this->qu("ROLLBACK");
		$this->_is_transaction = null;
		
		return true;
	}
	
	function Commit()
	{
		if(!$this->_is_transaction ){
			$this->StackError("TRANSACTION is not started.");
			return false;
		}
		
		if(!$this->pdo->commit() ){
			$this->StackError("Could not be COMMIT.");
			return false;
		}
		
		$this->qu("COMMIT");
		$this->_is_transaction = true;

		return true;
	}
	
	function Lock( $args )
	{	
		//	tables
		foreach( $args as $table => $value ){

			//	table
			$table = ConfigSQL::Quote($table, $this->driver);
			
			//	value
			switch( $value = strtoupper($value) ){
				case 'READ':
				case 'WRITE':
					break;
				default:
					$value = null;
			}
			$tables[] = "$table $value";
		}
		
		$tables = join(', ',$tables);
		
		//	query
		$qu = "LOCK TABLES ".$tables;
		
		//  execute
		$io = $this->query($qu,'lock');
	}
	
	function Unlock()
	{
		//	query
		$qu = "UNLOCK TABLES";
		
		//  execute
		$io = $this->query($qu,'unlock');
	}
}

class ConfigSQL extends OnePiece5
{
	static function GetQuote( $driver )
	{
		if( empty($driver) ){
			$me = "Empty driver name.";
			throw new OpException($me);
			return false;
		}
		
		switch( strtolower($driver) ){
			case 'mysql':
				$ql = $qr = '`';
				break;
			default:
		}
		return array($ql,$qr);
	}
	
	static function Quote( $var, $driver )
	{
		list( $ql, $qr ) = self::GetQuote($driver);
		return self::_Quote( $var, $ql, $qr );
	}
	
	static function _Quote( $var, $ql, $qr )
	{
		if( is_array($var) ){
			$safe = null;
			foreach( $var as $key => $tmp ){
				$safe[$key] = self::_Quote( $tmp, $ql, $qr );
			}
		}else if( is_string($var) and strlen($var) ){
			//	Replase quote
			$patt = ($ql === $qr) ? $ql: $ql.$qr;
			$patt = preg_quote($patt,'/');
			if( preg_match("/[$patt]/",$var) ){
				$var = preg_replace("/[$patt]/", '', $var);
			}
			
			//	Case of table
			/*
			if( strpos($var,'.') ){
				$temp = explode('.',$var);
				$safe = $ql.trim($temp[0]).$qr.'.'.$ql.trim($temp[1]).$qr;
			}else{
				$safe = $ql.trim($var).$qr;
			}
			*/
			
			$var  = OnePiece5::Escape($var);
			$safe = $ql.trim($var).$qr;
		}else{
			$safe = $var;
		}
		
		return $safe;
	}
	
	static function QuoteType( $var, $driver=null )
	{
		return preg_replace('/[^-_a-z0-9]/i', '', $var);
	}
	
	static function CacheKey( $args )
	{
		return md5(serialize(Toolbox::toArray($args)));
	}
}
