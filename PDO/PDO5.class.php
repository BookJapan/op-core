<?php
/**
 * 
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 *
 */
class PDO5 extends OnePiece5
{
	private $pdo = null;
	private $dcl = null;
	private $ddl = null;
	private $dml = null;
	private $qu	 = null;
	private $qus = array();
	private $isConnect	 = null;
	private $driver		 = null;
	private $host		 = null;
	private $user		 = null;
	private $database	 = null;
	private $charset	 = null;
	
	function DML( $name=null )
	{
		if( empty($this->dml) ){
			if(!class_exists('DML',false)){
				$io = include_once('PDO/DML.class.php');
				if(!$io){
					throw new Exception("Include failed.(PDO/DML.class.php)");
				}
			}
			
			//  Init
			$this->dml = new DML();
			$this->dml->SetPDO( $this->pdo, $this->driver );
			$this->dml->InitQuote($this->driver);
		}
		return $this->dml;
	}
	
	function DDL( $name=null )
	{
		if( empty($this->ddl) ){
			if(!class_exists('DDL',false)){
				$io = include_once('PDO/DDL.class.php');
				if(!$io){
					throw new Exception("Include failed.(PDO/DDL.class.php)");
				}
			}
			
			//  Init
			$this->ddl = new DDL();
			$this->ddl->SetPDO( $this->pdo, $this->driver );
		}
		return $this->ddl;
	}
	
	function DCL( $name=null )
	{
		if( empty($this->dcl) ){
			if(!class_exists('DCL',false)){
				$io = include_once('PDO/DCL.class.php');
				if(!$io){
					throw new Exception("Include failed.(PDO/DCL.class.php)");
				}
			}
			
			//  Init
			$this->dcl = new DCL();
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
						$result = $st->rowCount();
						break;
						
					default:
						$result = $st->fetchAll(PDO::FETCH_ASSOC);
				}
			}else{
				$this->d($st);
			}
		}else{			
			//  failed
			$result = false;
			$temp = $this->pdo->errorInfo();
			$this->StackError("{$temp[2]} : {$this->qu}");
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
		
		//  init
		$this->driver   = isset($config->driver)   ? $config->driver  : null;
		$this->host     = isset($config->host)     ? $config->host    : null;
		$this->user     = isset($config->user)     ? $config->user    : null;
		$password       = isset($config->password) ? $config->password: null;
		$this->database = isset($config->database) ? $config->database: null;
		$this->charset  = isset($config->charset)  ? $config->charset : null;
		
		$options = array();
		
		try {
			$dns = "{$this->driver}:host={$this->host}";
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
		if(!is_string($db_name)){
			$type = gettype($db_name);
			$me = "Database name is not string. ($type)";
			//throw new OpException($me);
			$this->StackError($me);
			return false;
		}
		
		if( $this->query("USE $db_name") === false){
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
	
	function GetTableList($config=null)
	{
		//  init config
		if(!is_array($config)){
			$config = Toolbox::toArray($config);
		}
		
		//  database
		$database = isset($config['database']) ? $config['database']: $this->database;
		
		//  like
		$like = isset($config['like']) ? $config['like']: null;
		
		//  select database
		if( $database ){
			if(!$this->Database($database)){
				return false;
			}
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
		$limit  = isset($config->limit)  ? $config->limit:  1;
		$offset = isset($config->offset) ? $config->offset: null;
		$order  = isset($config->order)  ? $config->order:  null;
		
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
		$config->cache    = 1;
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
		if(!$qu = $this->ddl()->GetPassword($conf)){
			return false;
		}
		
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
		
		//  execute
		return $this->query( $qu, 'create' );
	}
	
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
		return $this->query( $qu, 'create' );
	}
	
	/**
	 * 
	 */
	function Count( $conf )
	{
		//  object to array
		if(!is_array($conf)){
			$conf = Toolbox::toArray($conf);
		}
		
		//  added count aggregate
		if( empty($conf['agg']['count']) ){
			$conf['agg']['count'] = '*';
		}
		
		//  get select query
		if(!$qu = $this->dml()->GetSelect($conf)){
			return false;
		}
		
		//  execute
		if(!$count = $this->query($qu,'count')){
			return false;
		}
		
		return $count;
	}
	
	function Select( $conf )
	{
		if(!$this->isConnect){
			$this->StackError("Does not isConnect.");
			return false;
		}
		
		//  Check
		if(!$this->pdo){
			$this->StackError("Does not instanced PDO object.");
			return false;
		}
		
		//  object to array
		if(!is_array($conf)){
			$conf = Toolbox::toArray($conf);
		}
		
		//  Check cache setting.
		if(!empty($conf['cache'])){
			$key = serialize($conf);
			if( $records = $this->Cache()->Get($key) ){
				$this->Qu(var_export($conf,true));
			//	$records['cached'] = date('Y-m-d H:i:s');
				return $records;
			}
		}
		
		//  get select query
		if(!$qu = $this->dml()->GetSelect($conf)){
			return false;
		}

		//  execute
		$records = $this->query($qu);
		
		if( $records === false ){
			return false;
		}
		
		//  if limit is 1
		if( isset($conf['limit']) and $conf['limit'] == 1){
			if( isset($records[0]) ){
				return $records[0];
			}
		}
		
		//  Check cache setting.
		if(!empty($conf['cache'])){
			$key = serialize($conf);
			$this->Cache()->Set( $key, $records, (int)$conf['cache'] );
		}
		
		//  return to records.
		return $records;
	}
	
	function Insert( $conf )
	{
		//  Check
		if(!$this->pdo){
			$this->StackError("Does not instanced PDO object.");
			return false;
		}
		
		//  object to array
		if(!is_array($conf)){
			$conf = Toolbox::toArray($conf);
		}
		
		//  get query
		if(!$qu = $this->dml()->GetInsert($conf)){
			return false;
		}
		
		//  execute
		if(!$this->query($qu) === false){
			return false;
		}
		
		//  new id
		$id = $this->pdo->lastInsertId(/* $name */);
			
		return $id;
	}
	
	function Update($conf)
	{
		//  Check
		if(!$this->pdo){
			$this->StackError("Does not instanced PDO object.");
			return false;
		}
		
		//  object to array
		if(!is_array($conf)){
			$conf = Toolbox::toArray($conf);
		}
		
		//  get query
		if(!$qu = $this->dml()->GetUpdate($conf)){
			return false;
		}
		
		//  execute
		$num = $this->query($qu,'update');
		
		return $num;
	}
	
	function Delete( $conf )
	{
		//  Check
		if(!$this->pdo){
			$this->StackError("Does not instanced PDO object.");
			return false;
		}
		
		//  object to array
		if(!is_array($conf)){
			$conf = Toolbox::toArray($conf);
		}
		
		//  get query
		if( $this->qu = $this->dml()->GetDelete($conf)){
			$this->qus[] = $this->qu;
		}else{
			return false;
		}
		
		//  execute
		$num = $this->query($qu,'update');
		
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
		}
		return array($ql,$qr);
	}
	
	static function Quote( $var, $driver )
	{	
		list( $ql, $qr ) = self::GetQuote($driver);
		
		if( is_array($var) ){
			foreach( $var as $tmp ){
				$safe[] = self::Quote($tmp);
			}
		}else if( strpos($var,'.') ){
			$temp = explode('.',$var);
			$safe = $ql.trim($temp[0]).$qr.'.'.$ql.trim($temp[1]).$qr;
		}else{
			$safe = $ql.trim($var).$qr;
		}
		
		if( empty($safe) ){
			var_dump($var);
			var_dump($driver);
			OnePiece5::StackError("Empty args.");
		}
		
		return $safe;
	}
		
}
