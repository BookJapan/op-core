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
			if(empty($args[$key])){
				$this->StackError("\\$key\ is empty.",'en');
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
		$query  = "CREATE USER {$user}@{$host}";
		$query .= $password ? " IDENTIFIED BY {$password}": null;
		
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
			$this->StackError("Table name has not been set.",'en');
			return false;
		}
		if(!strlen($table) > 64){
			$this->StackError("Table names are limited to 64 bytes.",'en');
			return false;
		}
		
		//  Column
		if( empty($args['column']) ){
			$this->StackError("\$table\ table is not set value of column.",'en');
			return false;
		}else if( $column = $this->ConvertColumn($args['column']) ){
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
	
	/**
	 * Build alter table sql query.
	 * 
	 * @param  array $args
	 * @return boolean|string
	 */
	function GetAlterTable( $args )
	{
		if( isset($args['database']) ){
			$database = ConfigSQL::Quote($args['database'], $this->driver).'.';
		}else{ $database = null; }
		
		if( isset($args['table']) ){
			$table = ConfigSQL::Quote($args['table'], $this->driver);
		}else{
			$this->StackError("Does not set database name.");
			return;
		}
		
		foreach( array('add','change','modify','drop') as $acmd ){
			if( isset($args['column'][$acmd]) ){
				if(!${$acmd} = $this->ConvertColumn($args['column'][$acmd], strtoupper($acmd))){
					return false;
				}
			}else{
				${$acmd} = null;
			}
		}
		
		//	Create SQL
		$query = "ALTER TABLE {$database}{$table} {$add} {$change} {$modify} {$drop}";
		
		return $query;
	}
	
	/**
	 * Rename table name.
	 * 
	 * Parameter $args is only array.
	 * In case of selected database, ...
	 * Table name is include database name, separater is dot.
	 * 
	 * <pre>
	 * $args = array(
	 *   'database' => null, // database name.
	 *   'table'    => 't_onepiece', // old table name. (from)
	 *   'rename'   => 'test.t_onepiece', // new table name. (to)
	 * );
	 * </pre>
	 * 
	 * @param  array $args
	 * @return string $query
	 */
	function GetRenameTable($args)
	{
		if( isset($args['database']) ){
			$database = $args['database'];
		}else{ $database = null; }
		
		if( isset($args['table']) ){
			$table = $args['table'];
			if( strpos($table,'.') !== false ){
				list($database, $table) = explode('.',$table);
			}
		}
		
		if( isset($args['rename']) ){
			$to_table = $args['rename'];
			if( strpos($to_table,'.') !== false ){
				list($to_database, $to_table ) = explode('.',$to_table);
			}else{
				$to_database = $database;
			}
		}
		
		if( empty($table) ){
			$this->StackError("Table name has not been set.",'en');
			return;
		}
		
		if( empty($to_table) ){
			$this->StackError("\rename\ has not been set.",'en');
			return;
		}
		
		if( $database ){
			$database = ConfigSQL::Quote($database, $this->driver).'.';
		}
		
		if( $to_database ){
			$to_database = ConfigSQL::Quote($to_database, $this->driver).'.';
		}else{
			$to_database = $database;
		}
		
		$table = ConfigSQL::Quote($table, $this->driver);
		$to_table = ConfigSQL::Quote($to_table, $this->driver);
		
		//	RENAME TABLE `database`.`table` TO `to_database`.`to_table`;
		return "RENAME TABLE {$database}{$table} TO {$to_database}{$to_table}";
	}
	
	function GetAddPrimaryKey($args, $column=null)
	{
		if( is_string($args) ){
			$table = $alter;
		}else{
			$args = ConfigSQL::Quote($args, $this->driver);
			$database = isset($args['database']) ? $args['database'].'.': null;
			$table    = isset($args['table'])    ? $args['table']       : null;
			$column   = isset($args['column'])   ? $args['column']      : null;
		}
		
		if(!$table){
			$this->StackError("Table name has not been set.");
			return;
		}
		
		if(!$column){
			$this->StackError("Column has not been set.");
			return;
		}
		
		if( is_array($column) ){
			$column = join(',',$column);
		}
		
		return "ALTER TABLE $database $table ADD PRIMARY KEY($column)";
	}
	
	function GetAddIndex($args)
	{
		return $this->_GetIndex($args, 'add');
	}
	
	function GetDropIndex($args)
	{
		return $this->_GetIndex($args, 'drop');
	}
	
	private function _GetIndex( $args, $verb )
	{
		if(!empty($args['database'])){
			$database_name = ConfigSQL::Quote($args['database'], $this->driver) . '.';
		}else{ $database_name = null; }

		if(!empty($args['table'])){
			$table_name = ConfigSQL::Quote($args['table'], $this->driver);
		}else{
			$this->StackError("Table name has not been set.",'en');
			return false;
		}
		
		if(!empty($args['column'])){
			$column = ConfigSQL::Quote($args['column'], $this->driver);
			if( is_array($column) ){
				$column = join(',',$column);
			}
		}else{
			$this->StackError("Column name has not been set.",'en');
			return false;
		}
		
		if(!empty($args['type'])){
			$type = $args['type'];
		}else{
			$this->StackError("Index type has not been set.",'en');
			return false;
		}
		
		if(!empty($args['label'])){
			$label = ConfigSQL::Quote($args['label'], $this->driver);
		}else{ $label = null; }
		
		//	Upper case
		$verb = strtoupper($verb);
		$type = strtoupper($type);
		
		//	Check index type
		switch( $type ){
			case 'INDEX':
			case 'UNIQUE':
			case 'SPATIAL':
			case 'FULLTEXT':
			case 'PRIMARY KEY':
				break;
				
			default:
				$this->StackError("Does not define this definition. \($type)\\",'en');
				return false;
		}
		
		//	Check Add or Drop
		if( 'ADD' === $verb ){
			if( $label ){
				$target = "$label ($column)";
			}else{
				$target = "($column)";
			}
		}else if( 'DROP' === $verb ){
			if( $type === 'PRIMARY KEY' ){
				$target = null;
			}else{
				$type = 'INDEX';
				$target = "$column";
			}
		}else{
			$this->StackError("Does not define this definition. ($verb)");
			return false;
		}
		
		//	ALTER TABLE `t_table` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'auto increment.';

		//	ALTER TABLE `t_table` ADD INDEX (`created`,`updated`);		
		//	ALTER TABLE `t_table` ADD INDEX `label` (`column_name`);
		//	ALTER TABLE `t_table` ADD UNIQUE (`column_name`);
		
		//	ALTER TABLE `t_table` DROP INDEX `column_name`;
		
		//	ALTER TABLE `t_table` ADD  PRIMARY KEY(`id`);
		//  ALTER TABLE `t_table` DROP PRIMARY KEY;
		//  ALTER TABLE `t_table`
		//		DROP PRIMARY KEY,
		//		ADD  PRIMARY KEY(`id`);
		
		return "ALTER TABLE $database_name $table_name $verb $type $target";
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
	
	function GetDropPrimarykey($args)
	{
		if( is_string($args) ){
			$table = $args;
		}else{
			$args = ConfigSQL::Quote($args, $this->driver);
			$database = isset($args['database']) ? $args['database'].'.': null;
			$table    = isset($args['table'])    ? $args['table']       : null;
		}
		
		if(!$table){
			$this->StackError("Table name has not been set.");
			return;
		}
		
		return "ALTER TABLE $database $table DROP PRIMARY KEY";
	}
	
	function GetDropUser()
	{
		//DROP USER 'op_wizard'@'localhost';
	}
	
	function ConvertColumn($args, $verb='' )
	{
		//	Init
		$verb = strtoupper($verb);
		$indexes = array();
		
		//  loop from many columns
		foreach($args as $name => $temp){
			//	column name
			if( empty($temp['name']) ){
				if( isset($temp['field']) ){
					$temp['name'] = $temp['field'];
				}else{
					$temp['name'] = $name;
				}
			}
			
			//	init
			$name_old	 = null;
			$name		 = isset($temp['name'])       ? $temp['name']             : $name;
			$rename		 = isset($temp['rename'])     ? $temp['rename']           : null;
			$type		 = isset($temp['type'])       ? strtoupper($temp['type']) : null;
			$length		 = isset($temp['length'])     ? $temp['length']           : null;
			$value		 = isset($temp['value'])      ? $temp['value']            : null; // Plural form does not know.
			$values		 = isset($temp['values'])     ? $temp['values']           : $value;
			$unsigned	 = isset($temp['unsigned'])   ? $temp['unsigned']         : null;
			$attribute	 = isset($temp['attribute'])  ? $temp['attribute']        : null; // Plural form does not know.
			$attributes	 = isset($temp['attributes']) ? $temp['attributes']       : $attribute;
			$charset	 = isset($temp['charset'])	  ? $temp['charset']          : null;
			$charset	 = isset($temp['character'])  ? $temp['character']        : $charset;
			$collate	 = isset($temp['collate'])    ? $temp['collate']          : null; // For English-speaking countries.
			$collate	 = isset($temp['collation'])  ? $temp['collation']        : $collate;
			$null		 = isset($temp['null'])	      ? $temp['null']             : null;
			$default	 = isset($temp['default'])	  ? $temp['default']          : null;
			$comment	 = isset($temp['comment'])    ? $temp['comment']          : null;
			$first		 = isset($temp['first'])      ? $temp['first']            : null; // Add as the first column.
			$after		 = isset($temp['after'])      ? $temp['after']            : null; // Add after specified column.
			
			$ai			 = isset($temp['auto_increment'])	 ? $temp['auto_increment']	 : null;
			$ai			 = isset($temp['a_i'])				 ? $temp['a_i']				 : $ai;
			$ai			 = isset($temp['ai'])				 ? $temp['ai']				 : $ai;
			$pkey		 = isset($temp['primary_key'])		 ? $temp['primary_key']		 : null;
			$pkey		 = isset($temp['pkey'])				 ? $temp['pkey']			 : $pkey;
			$index		 = isset($temp['index'])			 ? $temp['index']			 : null;
			$unique		 = isset($temp['unique'])			 ? $temp['unique']			 : null;
			
			if( $verb === 'CHANGE' ){
				$name_old = $name;
				$name = $rename;
				unset($rename);
			}
			
			//	Quote value
			$name_old	 = ConfigSQL::Quote($name_old,$this->driver);
			$name		 = ConfigSQL::Quote($name,$this->driver);
			$type		 = ConfigSQL::QuoteType($type,$this->driver);
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
				if(!$pkey){
					$pkey = $pkey === null ? true: false;
				}
				if(!$type){
					$type = 'INT';
				}
			}
			
			//	PRIMARY KEY
			if( $pkey ){
				$pkey = null;
				$pkeys[] = $name;
			}
			
			//	index
			if( empty($index) ){
				$indexes = null;
			}else if( $index === 'unique' or $index === 'uni' ){
				$unique = true;
			}else{
				$indexes[] = $name;
				$index = null;
			}
			
			//	uniques
			if( $unique ){
				$uniques[] = $name;
			}else{
				$uniques = null;
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
			switch($verb){
				case '':
					$index  = $this->ConvertIndexKey('INDEX',  $indexes, null);
					$unique = $this->ConvertIndexKey('UNIQUE', $uniques, null);
					$definition = "$name $type $unsigned $charset $collate $attributes $null $default $comment $index $unique";
					$indexes = null;
					$uniques = null;
					break;

				case 'ADD':
				case 'CHANGE':
				case 'MODIFY':
				//	ALTER TABLE `t_table` ADD `user_id` INT UNSIGNED NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (`user_id`) ;
				//	ALTER TABLE `t_table` CHANGE `_read_` `read_` DATETIME NULL DEFAULT NULL COMMENT 'check already read'
				//	ALTER TABLE `t_table`.`t_count` CHANGE unique `date` `date` DATE , ADD UNIQUE(`date`)
					$definition = "$verb $index $name_old $name $type $unsigned $charset $collate $attributes $null $default $comment $first $after";
					break;
					
				case 'DROP':
					$definition = "{$verb} {$name}";
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
		
		if( isset($args['index']) ){
			//	TODO:
		}
		
		// primary key(s)
		if(isset($pkeys)){
			$join = array();
			foreach($pkeys as $name){
				$join[] = $name;
			}
			//	modifire
			$modifire = $verb ? 'ADD': null;
			$column[] = $modifire.' PRIMARY KEY('.join(',',$join).')';
		}
		
		// indexes
		if( $indexes ){
			$modifire = $verb ? 'ADD': null;
			$column[] = sprintf('%s INDEX(%s)', $modifire, join(",",$indexes));
		}
		
		// uniques
		if( isset($uniques) ){
			$modifire = $verb ? 'ADD': null;
			$column[] = sprintf('%s UNIQUE(%s)', $modifire, join(",",$uniques));
		}
		
		return isset($column) ? join(', ', $column): false;
	}
	
	function ConvertIndexKey($key, $indexes)
	{
		if( empty($indexes) ){
			return null;
		}
		
		$join = array();
		if( is_string($indexes) ){
			$join[] = $this->_ConvertIndexKey($key, $indexes);
		}else if( is_array($indexes) ){
			foreach($indexes as $index){
				$join[] = $this->_ConvertIndexKey($key, $index);
			}
		}
		return join(', ', $join);
	}

	private function _ConvertIndexKey($key, $name, $comment=null)
	{
		$key  = ConfigSQL::Quote($key,  $this->driver);
		$name = ConfigSQL::Quote($name, $this->driver);
		if( $comment ){
			$comment = ConfigSQL::Quote($comment, $this->driver);
			$comment = "COMMENT '$comment'";
		}
		return "$key `$name` (`$name`) $comment";
	}
}
