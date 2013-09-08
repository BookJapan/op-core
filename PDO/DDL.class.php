<?php


class DDL extends OnePiece5
{
	private $pdo = null;
	private $driver = null;
	
	function SetPDO( $pdo, $driver )
	{
		$this->pdo = $pdo;
		$this->driver = $driver;
	}
	
	function GetPassword( $args )
	{
		$user = isset($args['name']) ? $args['name']: null;
		$user = isset($args['user']) ? $args['user']: $user;
		$host = $args['host'];
		$password = $args['password'];
		
		//  SET PASSWORD FOR 'op_model_opuser'@'localhost' = PASSWORD( '***' )
		$query = "SET PASSWORD FOR '{$user}'@'{$host}' = PASSWORD('{$password}')";
		
		return $query;
	}
	
	function GetCreateUser( $args )
	{
		$user = isset($args['name']) ? $args['name']: null;
		$user = isset($args['user']) ? $args['user']: $user;
		$host = $args['host'];
		$password = $args['password'];
	
		if(!$host){
			$this->StackError("Empty host name.");
			return false;
		}
	
		if(!$user){
			$this->StackError("Empty user name.");
			return false;
		}
	
		if(!$password){
			$this->StackError("Empty password name.");
			return false;
		}
	
		if( strlen($user) > 16 ){
			$this->StackError("User name is maximum 16 character.");
			return false;
		}
		
		//	CREATE USER 'user-name'@'host-name' IDENTIFIED BY '***';
		$query = "CREATE USER '{$user}'@'{$host}' IDENTIFIED BY '{$password}'";
	
		return $query;
	}
	
	function GetCreateDatabase( $args )
	{
		//  Check 
		if( empty($args['database']) ){
			$this->StackError("Database name is empty.");
			return false;
		}
		
		//	IF NOT EXIST
		$if_not_exist = 'IF NOT EXISTS';
		
		//	Database
		$database = ConfigSQL::Quote( $args['database'], $this->driver );
		
		//	COLLATE
		if( isset($args['collate']) ){
			$collate = 'COLLATE '.$args['collate'];
		}else{
			//	default
			$collate = 'COLLATE utf8_general_ci';
		}
		
		//	CHARACTER SET
		if( isset($args['character']) ){
			$character = 'CHARACTER SET '.$args['character'];
		}else{
			//	default
			if(	$collate  == 'COLLATE utf8_general_ci'){
				$character = 'CHARACTER SET utf8';
			}else{
				$character = '';
			}
		}
		
		//	文字コードの設定があれば（必ずある）
		$default = 'DEFAULT';
		
		//	queryの作成
		$query = "CREATE DATABASE {$if_not_exist} {$database} {$default} {$character} {$collate}";
		
		return $query;
	}
	
	function GetCreateTable( $args )
	{
		if( empty($args['table']) ){
			if( isset($args['name']) ){
				$args['table'] = $args['name'];
			}
			$this->StackError("Empty table name.");
			return false;
		}
		
		//	TEMPORARY
		$temporary = isset($args['temporary']) ? 'TEMPORARY': null;
		
		//	IF NOT EXIST
		$if_not_exist = 'IF NOT EXISTS';
		
		//	Database
		if( isset($args['database']) ){
			$database = ConfigSQL::Quote($args['database'], $this->driver );
			$database .= ' . ';
		}else{
			$database = null;
		}
		
		//	Table
		if( isset($args['table']) ){
			$table = ConfigSQL::Quote($args['table'], $this->driver );
		}
		
		//  Column
		if( $column = $this->ConvertColumn($args) ){
			$column = '('.$column.')';
		}else{
			return false;
		}
		
		//	Database Engine
		if( isset($args['engine']) ){
			$engine = "ENGINE = ".$args['engine'];
		}else{
			$engine = "ENGINE = MYISAM";
		}
		
		//	COLLATE
		if( isset($args['collate']) ){
			$collate = 'COLLATE '.$args['collate'];
		}else{
			//	default
			$collate = null;
		}
		
		//	CHARACTER SET
		if( isset($args['character']) ){
			$character = 'CHARACTER SET '.$args['character'];
		}else{
			$character = null;
		}
		
		//	TABLE COMMENT
		if( isset($args['comment']) ){
			$comment = "COMMENT = '{$args['comment']}'";
		}else{
			$comment = null;
		}
		
		//	SQL文の作成
		$query = "CREATE {$temporary} TABLE {$if_not_exist} {$database}{$table} {$column} {$engine} {$character} {$collate} {$comment}";
		
		return $query;
	}
	
	function GetAlterTable( $args )
	{
		if( !isset($args['database']) ){
			$this->StackError("Does not set database name.");
		}
		
		if( !isset($args['table']) ){
			$this->StackError("Does not set database name.");
		}
		
		if( !isset($args['driver']) ){
			$args['driver'] = $this->driver;
		}
		
		//  Escape  
		$database = ConfigSQL::Quote( $args['database'], $args['driver']);
		$table    = ConfigSQL::Quote( $args['table'],    $args['driver']);
		
		//	Added
		if( isset($args['add']) ){
			if(!$add = $this->ConvertColumn( $args['add'], 'ADD' )){
				return false;
			}
		}else{ $add = null; }
	
		//	Change
		if( isset($args['change']) ){
			if(!$change = $this->ConvertColumn( $args['change'], 'CHANGE' )){
				return false;
			}
		}else{ $change = null; }
	
		//	 Remove
		if( isset($args['drop']) ){
			if(!$drop = $this->ConvertColumn( $args['drop'], 'DROP' )){
				return false;
			}
		}else{ $drop = null; }
		
		//	Create SQL
		$query = "ALTER TABLE {$database}.{$table} {$add} {$change} {$drop}";
	
		return $query;
	}
	
	function GetDropDatabase( $args )
	{
		if( empty($args['database']) ){
			$this->StackError("Empty database name.");
			return false;
		}
		
		$database  = ConfigSQL::Quote( $args['database'], $this->driver );
		
		$query = "DROP DATABASE IF EXISTS {$database}";
		
		return $query;
	}
	
	function GetDropTable()
	{
		if( empty($args['database']) ){
			$this->StackError("Empty database name.");
			return false;
		}
		
		if( empty($args['table']) ){
			$this->StackError("Empty table name.");
			return false;
		}

		$database  = ConfigSQL::Quote( $args['database'], $this->driver );
		$table     = ConfigSQL::Quote( $args['table'],    $this->driver );
		$temporary = empty($args['temporary']) ? null: 'TEMPORARY';
		
		$query = "DROP {$temporary} TABLE IF EXISTS {$database}.{$table}";
		
		return $query;
	}
	
	function GetDropUser()
	{
		//DROP USER 'op_wizard'@'localhost';
	}
	
	function ConvertColumn( $args, $ACD='' )
	{
		if( empty($args['column']) ){
			$this->StackError("Empty column");
			return false;
		}
		
		//	INIT
		$indexes = array();
	//	$pkeys	 = null;
		
		//	Quote mark
		list( $ql, $qr ) = ConfigSQL::GetQuote($this->driver);
		
		//  loop from many columns
		foreach($args['column'] as $name => $temp){
			
			//	column name
			if( empty($temp['name']) ){
				if( isset($temp['field']) ){
					$temp['name'] = $temp['field'];
				}else{
					$temp['name'] = $name;
				//	$this->StackError("Empty column name. ($name)");
				//	continue;
				}
			}
			
			//	init
			$type		 = isset($temp['type'])       ? strtoupper($temp['type']) : null;
			$name		 = isset($temp['name'])       ? $ql.$temp['name'].$qr     : $ql.$name.$qr;
			$rename		 = isset($temp['rename'])     ? $ql.$temp['rename'].$qr   : null;
			$length		 = isset($temp['length'])     ? $temp['length']           : null;
			$value		 = isset($temp['value'])      ? $temp['value']            : null; // 複数形苦手対応
			$values		 = isset($temp['values'])     ? $temp['values']           : $value;
			$attribute	 = isset($temp['attribute'])  ? $temp['attribute']        : null; // 複数形苦手対応
			$attributes	 = isset($temp['attributes']) ? $temp['attributes']       : $attribute;
			$charset	 = isset($temp['charset'])	  ? $temp['character']        : null;
			$charset	 = isset($temp['character'])  ? $temp['character']        : $charset;
			$collate	 = isset($temp['collate'])    ? $temp['collate']          : null; // 英語圏対応
			$collation	 = isset($temp['collation'])  ? $temp['collation']        : $collate;
			$null		 = isset($temp['null'])	      ? $temp['null']             : null;
			$default	 = isset($temp['default'])	  ? $temp['default']				 : null;
			$comment	 = isset($temp['comment'])    ? "COMMENT '{$temp['comment']}'": null;
			$first		 = (isset($temp['first']) and $temp['first']) ? $temp['first']  : null; // 先頭に追加
			$after		 = (isset($temp['after']) and $temp['after']) ? "AFTER {$ql}{$temp['after']}{$qr}": null; // 指定カラムの後ろに追加
			
			$ai			 = isset($temp['auto_increment']) ? $temp['auto_increment']: null;
			$ai			 = isset($temp['a_i'])    ? $temp['a_i']  : null;
			$ai			 = isset($temp['ai'])     ? $temp['ai']   : null;
			$pkey		 = isset($temp['pkey'])   ? $temp['pkey'] : null;
			$index		 = isset($temp['index'])  ? $temp['index']: null;
			$unique		 = isset($temp['unique']) ? $temp['unique']: null;
			
			//	type
			switch($type){
				case 'TIMESTAMP':
					$attributes	 = "ON UPDATE CURRENT_TIMESTAMP";
					$default	 = "DEFAULT CURRENT_TIMESTAMP";
					$null		 = 'NOT NULL';
					break;
		
				case 'SET':
				case 'ENUM':
					if(!$values){
						$values = $length;
						$length = '';
					}
					$join = array();
					foreach(explode(',',$values) as $value){
						$join[] = trim($value);
					}
					$values = "'".join("','",$join)."'";
					
				default:
					if( $length or $values){
						//	INT, VARCHAR, ENUM, SET
						$type .= "({$length}{$values})";
					}
			}
				
			//	auto_increment
			if( $ai ){
				$attributes = "AUTO_INCREMENT";
				$type = 'INT';
				$pkey = true;
			}
						
			//	PRIMARY KEY
			if( $pkey ){
			//	$pkey = "PRIMARY KEY"; // TODO: only mysql, other engine unknown.
				$pkey = null;
				$pkeys[] = $name;
			}
			
			//	index
			if( empty($index) ){
				$index = null;
			}else if( $index === 'unique' ){
				$unique = true;
			}else{
			//	$index_type = 'USING BTREE';
			//	$indexes[] = sprintf('INDEX %s %s (%s)', 'index_'.count($indexes), $index_type, $name );
				$indexes[] = $name;
				$index = null;
			}
			
			//	uniques
			if( $unique ){
				$uniques[] = $name;
			}
				
			//  default
			if( isset($temp['default']) ){
				$default = $temp['default'];
				if( is_null($default) ){
					$default = "DEFAULT NULL";
				}else{
					$default = "DEFAULT $default";
				}
			}
			
			//  Added first column
			$first = $first ? 'FIRST': null;
				
			//	character set
			if( $charset ){
				$charset = "CHARACTER SET $charset";
			}
				
			//	COLLATE
			if( $collate ){
				$collate = "COLLATE $collate";
			}
			
			//  
			if(!empty($rename) and empty($type) ){
				$this->StackError('"type" is empty. ("type" is required "rename".)');
				return false;
			}
			
			//  Doth not select both.
			if( $first and $after ){
				$this->StackError('FIRST and AFTER are selected. Either one.');
				return false;
			}
			
			//  Character lenght.
			if( $type == 'CHAR' or $type == 'VARCHAR' ){
				if( !$length or !$values ){
					$this->StackError("length is empty. (name=$name, type=$type)");
					return false;
				}
			}
			
			//  Column permit NULL?
			if( is_bool($null)){
				$null = $null ? 'NULL': 'NOT NULL';
			}
			
			//  Create define
			switch($ACD){
				case '':
					$definition = "$name $type $charset $collate $attributes $pkey  $null $default $comment";
					break;
					
				case 'CHANGE':
					if(!$rename){
						$rename = $name;
					}
					
				case 'ADD':
				//	ALTER TABLE `op_message` CHANGE `_read_` `read_` DATETIME NULL DEFAULT NULL COMMENT 'check already read'
					$definition = "$ACD $index $rename $name $type $attributes $null $default $comment $first $after";
					break;
	
				case 'DROP':
					$definition = "{$ACD} {$name}";
					break;
			}
				
			//	Anti oracle only?
			switch( strtolower($this->driver) ){
				case 'oracle':
					$definition = "({$definition})";
					break;
				case 'mysql':
				case 'pgsql':
				case 'db2':
				//	$definition = "COLUMN {$definition}";
				//	$definition = "{$definition}";
					break;
				default:
					$this->core->StackError('Undefined product name. ($product)');
			}
			$column[] = $definition;
		}
		
		// primary key(s)
		if(isset($pkeys)){
			//  TODO use standard array function
			$join = array();
			foreach($pkeys as $name){
				$join[] = $name;
			}
			//	modifire
			$modifire = $ACD ? 'ADD': null;
			$column[] = $modifire.' PRIMARY KEY('.join(',',$join).')';
		}
		
		// indexes
		if( $indexes ){
			$modifire = $ACD ? 'ADD ': null;
		//	$column[] = $modifire.' INDEX('.join(",",$indexes).')';
			$column[] = sprintf('%sINDEX(%s)', $modifire, join(",",$indexes));
		}
		
		// uniques
		if( isset($uniques) ){
			$modifire = $ACD ? 'ADD ': null;
		//	$column[] = $modifire.' UNIQUE('.join(",",$uniques).')';
			$column[] = sprintf('%sUNIQUE(%s)', $modifire, join(",",$uniques));
		}
		
		return isset($column) ? join(', ', $column): false;
	}
}
