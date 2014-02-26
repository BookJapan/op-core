<?php
/**
 * 
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 *
 */
class PDO5 extends OnePiece5
{
	/* @var $pdo PDO */
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
	 * 
	 * @param  string $name
	 * @throws OpException
	 * @return DML5
	 */
	function DML( $name='DML5' )
	{
		if( empty($this->dml) ){

			$path  = $this->GetEnv('op-root');
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
			//  success
			if( $st instanceof PDOStatement ){
				switch($key){
					case 'alter':
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
				}
			}else{
				$this->d($st);
			}
		}else{
			switch(strtolower($key)){
				case 'use':
					$result = true;
					break;
					
				default:
					//  failed
					$result = false;
					$temp = $this->pdo->errorInfo();
					$this->StackError("{$temp[2]} : ![.gray[{$this->qu}]]");
			}
		}
		
		return $result;
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
	
	function Connect( $config )
	{
		if( is_array($config) ){
			$config = Toolbox::toObject($config);
		}
		
		if( empty($config->driver) ){
			$this->StackError("Does not set driver. (ex: $config->drive = 'mysql';)");
			return false;
		}
		
		//  init
		$this->driver   = isset($config->driver)   ? $config->driver  : null;
		$this->host     = isset($config->host)     ? $config->host    : null;
		$this->port     = isset($config->port)     ? $config->port    : null;
		$this->user     = isset($config->user)     ? $config->user    : null;
		$password       = isset($config->password) ? $config->password: null;
		$this->database = isset($config->database) ? $config->database: null;
		$this->charset  = isset($config->charset)  ? $config->charset : null;
		
		$options = array();
		
		try {
			//	Supports PHP 5.1 ( USE db_name is not supports. )
			/*
			$io = version_compare( PHP_VERSION, 5.1 );
			$this->mark($io);
			*/
			$db  = $this->database ? 'dbname='.$this->database.';': null;
			if( $this->charset ){
				if( $this->driver == 'mysql' ){
					$options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES '{$this->charset}'";
				}
			}
			
			//	Create DNS
			$dns = "{$this->driver}:{$db}host={$this->host}";
			
			/*
			//	Create PDO
			$dns = "{$this->driver}: host={$this->host}";
			if( $this->database ){
				$dns .= " dbname={$this->database}";
			}
			*/
			
			if(!$this->pdo = new PDO( $dns, $this->user, $password, $options )){
				$this->StackError("Can not connect database. ( $dns, {$this->user} )");
				return false;
			}
		}catch( PDOException $e){
			$this->StackError( $e->GetMessage() );
			return false;
		}
		
		//  Database select
		if( $this->database ){
			$this->Database( $this->database, $this->charset );
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
			$me = "Database name is not string. ($type)";
			//throw new OpException($me);
			$this->StackError($me);
			return false;
		}
		
		//	Quote
		$db_name = ConfigSQL::Quote( $db_name, $this->driver );
		
		if( $this->query("USE $db_name",'use') === false){
			$me = "Database select is failed.";
			//throw new OpException($me);
			$this->StackError($me);
			return false;
		}
		
		if( $charset ){
			$this->SetCharset($charset);
		}
		
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
	
	function GetDatabaseList($config=null)
	{
		//  init config
		if(!is_array($config)){
			$config = Toolbox::toArray($config);
		}
		
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
	
	function GetTableList( $config=null, $like=null )
	{
		if(is_string($config)){
			//  set database
			$database = $config;
		}else{
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
		
		//	Convert LIKE
		if( $like ){
			$like = 'LIKE ' . ConfigSQL::Quote( $like, $htis->driver);
		}
		
		//  create qu
		$qu = "SHOW TABLES FROM `$database` $like ";
		
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
		
		//  length
		foreach( $records as $record ){
			$record = array_change_key_case( $record, CASE_LOWER );
			$name = $record['field'];
			if(preg_match('/([a-z]+)\(([0-9]+)\)/',$record['type'],$match)){
				$record['type']   = $match[1];
				$record['length'] = $match[2];
			}
			$struct[$name] = $record;
		}
		
		return $struct;
	}
	
	function GetTableColumn( $table, $database=null )
	{
		foreach($this->GetTableStruct($table,$database) as $struct ){
			$columns[] = $struct['field'];
		}
		return $columns;
	}
	
	function GetUserList()
	{
		//  Select database
		$this->Database('mysql');
		
		//  Get users list
		$select = new Config();
		$select->table = 'user';
		$select->column = 'User';
		$record = $this->select($select);
		
		//  
		for( $i=0, $c=count($record); $i<$c; $i++ ){
			$result[] = $record[$i]['User'];
		}
		
		return $result;
	}

	function Quick( $string, $config=null)
	{
		//  TODO:
		/*
		$cache_key = md5($string .'; '. serialize($config));
		if( $value = $this->Cache()->Get($cache_key) ){
			return $value;
		}
		*/
		
		//  Get value
		//list( $left, $value ) = explode('=', trim($string) );
		if( preg_match('/(.+)[^><=]([=<>]{1,2})(.+)/', $string, $match) ){
			$left  = $match[1];
			$ope   = $match[2] == '=' ? null: $match[2].' ';
			$value = $match[3];
		//	$this->d($match);
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
		//$this->mark("column=$column, location=$location, ope=$ope, value=$value");
	
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
	//	$config->d();
	
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
		
		return $return;
		
		//==============================================//
		
		if( $record === false ){
			$this->mark();
			return false;
		}
		
		//  return all
		if( !$column or $limit != 1 ){
			$this->mark();
			return $record;
		}
		
		//  return one
		if( count($columns) === 1 ){
			$this->d($columns);
		//	return isset($record[$columns[0]]) ? $record[$columns[0]]: null;
			return array_shift($record);
		}
	
		//  return many
		foreach( $record as $key => $var ){
		//	$return[$key] = $var; // default is not index key.
			$return[] = $var;
		}
	
		return $return;
	}
	
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
		$io = $this->query($qu);
		
		return $io;
	}
	
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
	
	function CreateUser( $conf )
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
	
	function Password( $conf )
	{
		//  object to array
		if(!is_array($conf)){
			$conf = Toolbox::toArray($conf);
		}
		
		//  get select query
		$qu = $this->ddl()->GetPassword($conf);
			
		//  execute
		return $this->query( $qu, 'create' );
	}
	
	function Grant( $conf )
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
		return $this->query( $qu, 'create' );
	}
	
	function Revoke( $conf )
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
	 * @param  unknown $conf
	 * @return boolean
	 */
	function AlterTable( $conf )
	{
		//  object to array
		if(!is_array($conf)){
			$conf = Toolbox::toArray($conf);
		}
		//  get select query
		if(!$qu = $this->dcl()->GetAlterTable($conf)){
			return false;
		}
		
		//	ALTER TABLE `t_table` CHANGE `column_name` `column_name` VARCHAR( 11 ) NOT NULL DEFAULT '0'
		
		//  execute
		return $this->query( $qu, 'create' );
	}
	
	/**
	 * Add new column
	 * 
	 * @param  array|Config $conf
	 * @return boolean|string
	 */
	function AddColumn( $conf )
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
	function ChangeColumn( $conf )
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
	
	function AddIndex( $conf )
	{
	//	ALTER TABLE `t_table` ADD INDEX `column_name` ( `column_name` ); 
	}
	
	function DropPrimarykey( $table_name )
	{
	//	ALTER TABLE op_account DROP PRIMARY KEY
		$table_name = ConfigSQL::Quote( $table_name, $this->driver );
		$qu = "ALTER TABLE $table_name DROP PRIMARY KEY";
		return $this->query( $qu, 'alter' );
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
		
		return $count;
	}
	
	function Select( $args )
	{
		if(!$this->isConnect){
			$this->StackError("Not connected. (isConnect is false)");
			return false;
		}
		
		//  Check
		if(!$this->pdo){
			$this->StackError("Does not instanced PDO object.");
			return false;
		}
		
		//	empty config
		if( empty($args) ){
			$this->StackError("Does not set config. (empty)");
			return false;
		}

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
		
		//	fat mode in the case of join table.
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
		
		//  get query
		if(!$qu = $this->dml()->GetDelete($config)){
			return false;
		}
		
		//  execute
		$num = $this->query($qu,'delete');
		
		return $num;
	}
	
	function Transaction()
	{
		$this->pdo->beginTransaction();
	}
	
	function Rollback()
	{
		$this->pdo->rollBack();
	}
	
	function Commit()
	{
		$this->pdo->commit();
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
		
		if( is_array($var) ){
			$safe = null;
			foreach( $var as $tmp ){
				$safe[] = self::Quote($tmp);
			}
		}else if( strpos($var,'.') ){
			$temp = explode('.',$var);
			$safe = $ql.trim($temp[0]).$qr.'.'.$ql.trim($temp[1]).$qr;
		}else{
			$safe = $ql.trim($var).$qr;
		}
		
		/*
		if( empty($safe) ){
			$this->mark($var);
			$this->mark($driver);
			OnePiece5::StackError("Empty args.");
			$safe = null;
		}
		*/
		
		return $safe;
	}
	
	static function CacheKey( $args )
	{
		return md5(serialize(toolbox::toarray($args)));
	}	
}
