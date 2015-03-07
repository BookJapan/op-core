<?php


class DDL5 extends OnePiece5
{
	/**
	 * @var PDO
	 */
	private $pdo = null;
	private $driver = null;
	
	function SetPDO( $pdo, $driver )
	{
		$this->pdo = $pdo;
		$this->driver = $driver;
	}
	
	function GetPassword($args)
	{
		$user = isset($args['name']) ? $args['name']: null;
		$user = isset($args['user']) ? $args['user']: $user;
		$host = $args['host'];
		$password = $args['password'];

		$host = $this->pdo->quote($host);
		$user = $this->pdo->quote($user);
		$password = $this->pdo->quote($password);
		
		//  SET PASSWORD FOR 'user-name'@'permit-host' = PASSWORD('***')
		return "SET PASSWORD FOR {$user}@{$host} = PASSWORD({$password})";
	}
	
	function GetCreateUser( $args )
	{
		foreach(array('host','user','password') as $key){
			if(!${$key}){
				$this->StackError("\{$key}\ is empty.",'en');
				return false;
			}
		}
		
		$name = isset($args['name']) ? $args['name']: null;
		$user = isset($args['user']) ? $args['user']: $name;
		$host = $args['host'];
		$password = $args['password'];
		
		$host = $this->pdo->quote($host);
		$user = $this->pdo->quote($user);
		$password = $this->pdo->quote($password);
		
		//	CREATE USER 'user-name'@'host-name' IDENTIFIED BY '***';
		$query = "CREATE USER {$user}@{$host} IDENTIFIED BY {$password}";
		
		return $query;
	}
	
	function GetCreateDatabase( $args )
	{
		//  Check database name
		if( empty($args['database']) ){
			if( empty($args['name']) ){
				$this->StackError("Database name is empty.");
				return false;
			}else{
				$args['database'] = $args['name'];
			}
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
		//	TEMPORARY
		$temporary = isset($args['temporary']) ? 'TEMPORARY': null;
		
		//	IF NOT EXIST
		$if_not_exist = empty($args['if_not_exist']) ? null: 'IF NOT EXISTS';
		
		//	Database
		if( isset($args['database']) ){
			$database = ConfigSQL::Quote($args['database'], $this->driver );
			$database .= ' . ';
		}else{
			$database = null;
		}
		
		//	Table name		
		$table = isset($args['name'])  ? $args['name']: null;
		$table = isset($args['table']) ? $args['table']: $table;
		$table = ConfigSQL::Quote( $table, $this->driver );
		if(!$table ){
			$this->StackError("Put name of \table\.",'en');
			return false;
		}
		if(!strlen($table) > 64){
			$this->StackError("Table names are limited to 64 bytes.",'en');
			return false;
		}
		
		//  Column
		if( $column = $this->ConvertColumn($args) ){
			$column = '('.$column.')';
		}else{
			return;
		}
		
		//	Database Engine
		if( isset($args['engine']) ){
			$engine = "ENGINE = ".ConfigSQL::QuoteType($args['engine']);
		}else{
			$engine = "ENGINE = InnoDB";
		}
		
		//	COLLATE
		if( isset($args['collate']) ){
			$collate = 'COLLATE '.ConfigSQL::QuoteType($args['collate']);
		}else{
			//	default
			$collate = 'COLLATE utf8_general_ci';
		}
		
		//	CHARACTER SET
		$charset = isset($args['charset']) ? $args['charset']: null;
		$charset = isset($args['character']) ? $args['character']: $charset;
		if( $charset ){
			$character = 'CHARACTER SET '.ConfigSQL::QuoteType($charset);
		}else{
			if( preg_match('/ utf8_/i', $collate) ){
				//	default
				$character = 'CHARACTER SET utf8';
			}else{
				$character = null;
			}
		}
		
		//	COMMENT
		if( isset($args['comment']) ){
			$comment = "COMMENT = ".$this->pdo->quote($args['comment']);
		}else{
			$comment = null;
		}
		
		//	Generate query.
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
	
	function GetAddIndex($args)
	{
		//	ALTER TABLE `t_count` ADD UNIQUE (`date`);
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
			$this->StackError("Put the value of column.",'en');
			return false;
		}
		
		//	INIT
		$indexes = array();
		
		//  loop from many columns
		foreach($args['column'] as $name => $temp){
			
			//	column name
			if( empty($temp['name']) ){
				if( isset($temp['field']) ){
					$temp['name'] = $temp['field'];
				}else{
					$temp['name'] = $name;
				}
			}
			
			//	init
			$type		 = isset($temp['type'])       ? strtoupper($temp['type']) : null;
			$name		 = isset($temp['name'])       ? $temp['name']             : $name;
			$rename		 = isset($temp['rename'])     ? $temp['rename']           : null;
			$length		 = isset($temp['length'])     ? $temp['length']           : null;
			$value		 = isset($temp['value'])      ? $temp['value']            : null; // 複数形苦手対応
			$values		 = isset($temp['values'])     ? $temp['values']           : $value;
			$unsigned	 = isset($temp['unsigned'])   ? $temp['unsigned']         : null;
			$attribute	 = isset($temp['attribute'])  ? $temp['attribute']        : null; // 複数形苦手対応
			$attributes	 = isset($temp['attributes']) ? $temp['attributes']       : $attribute;
			$charset	 = isset($temp['charset'])	  ? $temp['charset']          : null;
			$charset	 = isset($temp['character'])  ? $temp['character']        : $charset;
			$collate	 = isset($temp['collate'])    ? $temp['collate']          : null; // 英語圏対応
			$collate	 = isset($temp['collation'])  ? $temp['collation']        : $collate;
			$null		 = isset($temp['null'])	      ? $temp['null']             : null;
			$default	 = isset($temp['default'])	  ? $temp['default']          : null;
			$comment	 = isset($temp['comment'])    ? $temp['comment']          : null;
			$first		 = (isset($temp['first']) and $temp['first']) ? $temp['first'] : null; // Add top.
			$after		 = (isset($temp['after']) and $temp['after']) ? $temp['after'] : null; // Add after specified column.
			
			$ai			 = isset($temp['auto_increment'])	 ? $temp['auto_increment']	 : null;
			$ai			 = isset($temp['a_i'])				 ? $temp['a_i']				 : $ai;
			$ai			 = isset($temp['ai'])				 ? $temp['ai']				 : $ai;
			$pkey		 = isset($temp['primary_key'])		 ? $temp['primary_key']		 : null;
			$pkey		 = isset($temp['pkey'])				 ? $temp['pkey']			 : $pkey;
			$index		 = isset($temp['index'])			 ? $temp['index']			 : null;
			$unique		 = isset($temp['unique'])			 ? $temp['unique']			 : null;
			
			//	Quote value
			$name		 = ConfigSQL::Quote($name,$this->driver);
			$type		 = ConfigSQL::QuoteType($type,$this->driver);
			$rename		 = ConfigSQL::Quote($rename,$this->driver);
			$values		 = ConfigSQL::Quote($values,$this->driver);
			$attributes	 = ConfigSQL::Quote($attributes,$this->driver);
			$charset	 = ConfigSQL::Quote($charset,$this->driver);
			$collate	 = ConfigSQL::Quote($collate,$this->driver);
			$null		 = ConfigSQL::Quote($null,$this->driver);
			$default	 = ConfigSQL::Quote($default,$this->driver);
			$first		 = ConfigSQL::Quote($first,$this->driver);
			$after		 = ConfigSQL::Quote($after,$this->driver);
			
			if( $length and $type !== 'ENUM' and $type !== 'SET' ){
				if(!is_numeric($length)){
					$length	 = $this->pdo->quote($length);
				}
			}
			
			if( $comment ){
				$comment = "COMMENT ".$this->pdo->quote($comment);
			}
			
			if( $after ){
				$after = "AFTER $after";
			}
			
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
						$length = null;
					}
					if( is_string($values) ){
						$values = explode(',',$values);
					}
					$values = "'".join("','",array_map('trim',$values))."'";
					
				default:
					if( $length or $values){
						//	INT, VARCHAR, ENUM, SET
						$type .= "({$length}{$values})";
					}
			}
			
			//	auto_increment
			if( $ai ){
				$attributes = "AUTO_INCREMENT";
				$unsigned = true;
				$pkey = true;
				if(!$type){
					$type = 'INT';
				}
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
			
			//  Do not select both.
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
			
			//	 unsigned
			if( $unsigned ){
				$unsigned = 'UNSIGNED';
			}
			
			//  Create define
			switch($ACD){
				case '':
					$definition = "$name $type $unsigned $charset $collate $attributes $pkey $null $default $comment";
					break;
					
				case 'CHANGE':
					if(!$rename){
						$rename = $name;
					}
					
				case 'ADD':
				//	ALTER TABLE `t_table` ADD `id` INT UNSIGNED NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (`thread_id`) ;
				//	ALTER TABLE `t_table` CHANGE `_read_` `read_` DATETIME NULL DEFAULT NULL COMMENT 'check already read'
				//	ALTER TABLE `t_table`.`t_count` CHANGE unique `date` `date` DATE , ADD UNIQUE(`date`)
					$definition = "$ACD $index $rename $name $type $unsigned $charset $collate $attributes $null $default $comment $first $after";
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
