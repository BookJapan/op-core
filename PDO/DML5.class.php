<?php
/**
 * 
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 *
 */
class DML5 extends OnePiece5
{
	//  OLD
	//private $pdo = null;
	private $ql  = null;
	private $qr  = null;
	private $_is_table_join = null;

	//  NEW
	/**
	 * PHP's PDO
	 * 
	 * @var PDO
	 */
	private $pdo    = null;
	private $driver = null;
	
	function SetPDO( $pdo, $driver )
	{
		$this->pdo = $pdo;
		$this->driver = $driver;
	}
	
	function InitQuote($driver)
	{
		switch($driver){
			case 'mysql':
				$this->ql = '`';
				$this->qr = '`';
				break;
			default:
				$this->mark(__METHOD__.": Does not implement yet.(driver=$driver)");
		}
	}
	
	function GetSelect( $conf )
	{
		//  database
		if(isset($conf['database'])){
			$database = $this->ql.$conf['database'].$this->qr;
		}else{
			$database = null;
		}
		
		//  init flag
		$this->_is_table_join = false;
		
		//  table
		if(isset($conf['table'])){
			$table = $this->ConvertTable($conf);
		}else{
			$this->StackError('Empty table. Please set table name.');
			return false;
		}
		
		//  column
		if(!$column = $this->ConvertSelectColumn($conf)){
			return false;
		}
		
		//  where (and)
		if( isset($conf['where-and']) ){
			$conf['where'] = $conf['where-and'];
		}
		if( isset($conf['where_and']) ){
			$conf['where'] = $conf['where_and'];
		}
		if( isset($conf['where']) ){
			$conf['wheres']['and'] = $conf['where'];
		}
		
		//  where (or)
		if( isset($conf['where-or']) ){
			$conf['where_or'] = $conf['where-or'];
		}
		if( isset($conf['where_or']) ){
			$conf['wheres']['or'] = $conf['where_or'];
		}
		
		//  wheres
		if(!empty($conf['wheres'])){
			if(!$where = $this->ConvertWheres($conf['wheres'])){
				var_dump($where);
				return false;
			}
		}else{
			$where = null;
		}
		
		//	in
		if( isset($conf['in']) ){
			$conf['where-in'] = $conf['in'];
		}
		
		//  in (and)
		if(isset($conf['where-in'])){
			$is_where = $where ? ' AND ': null;
			if(!$where .= $is_where.$this->ConvertWhereIn($conf, 'AND') ){
				return false;
			}
		}
		
		//  in (or)
		if(isset($conf['where-in-or'])){
			$is_where = $where ? ' AND ': null;
			if(!$where .= $is_where.$this->ConvertWhereIn($conf, 'OR') ){
				return false;
			}
		}
		
		//	not in
		if( isset($conf['not-in']) ){
			$conf['where-not-in'] = $conf['not-in'];
		}
		
		//  not in (and)
		if(isset($conf['where-not-in'])){
			$is_where = $where ? ' AND ': null;
			if(!$where .= $is_where.$this->ConvertWhereNotIn($conf, 'AND') ){
				return false;
			}
		}
		
		//  not in (or)
		if(isset($conf['where-not-in-or'])){
			$is_where = $where ? ' AND ': null;
			if(!$where .= $is_where.$this->ConvertWhereNotIn($conf, 'OR') ){
				return false;
			}
		}
		
		//	between
		if( isset($conf['between']) ){
			$conf['where-between'] = $conf['between'];
		}
		
		//  between (and)
		if( isset($conf['where-between']) ){
			$is_where = $where ? ' AND ': null;
			if(!$where .= $is_where.$this->ConvertWhereBetween($conf, 'AND') ){
				return false;
			}
		}
		
		//  between (or)
		if(isset($conf['where-between-or'])){
			$is_where = $where ? ' AND ': null;
			if(!$where .= $is_where.$this->ConvertWhereBetween($conf, 'OR') ){
				return false;
			}
		}
		
		//	each columns
		if( isset($conf['where_column']) ){
			$is_where = $where ? ' AND ': null;
			if(!$where .= $is_where.$this->_convertWhereAtColumn($conf['where_column'])){
				return false;
			}
		}
		
		//  sub select
		if(isset($conf['where-select'])){
			if(!$where .= $this->GetSelect($conf['where-select']) ){
				return false;
			}
		}
		
		//  group
		if( isset($conf['group']) ){
			if(!$group = $this->ConvertGroup($conf)){
				return false;
			}
		}else{
			$group = null;
		}
		
		//  having
		if( isset($conf['having']) ){
			$conf['havings']['and'] = $conf['having'];
		}
		
		//  havings
		if( isset($conf['havings']) ){
			if(!$having = $this->ConvertHavings($conf)){
				return false;
			}
		}else{
			$having = null;
		}
		
		//  order
		if(isset($conf['order'])){
			$order = $this->ConvertOrder($conf);
		}else{
			$order = null;
		}
		
		//  limit
		if(isset($conf['limit'])){
			$limit = $this->ConvertLimit($conf);
		}else{
			$limit = null;
		}
		
		//  offset
		if( isset($conf['offset']) ){
			if(!$offset = $this->ConvertOffset($conf)){
				return false;
			}
		}else{
			$offset = null;
		}
		
		//	option
		if(!empty($conf['distinct']) or !empty($conf['distinctrow'])){
			$options[] = 'DISTINCT';
		}
		
		//	merge option
		if(isset($options)){
			$option = join(' ',$options);
		}else{
			$option = null;
		}
		
		//	WHERE modifire
		$is_where = $where ? 'WHERE': null;
		
		return "SELECT $option $column FROM $table $is_where $where $group $having $order $limit $offset ";
	}
	
	function GetInsert( $conf )
	{
		//  database
		if(isset($conf['database'])){
			$database = $this->ql.$conf['database'].$this->qr.'.';
		}else{
			$database = null;
		}
				
		//  table
		if(isset($conf['table'])){
			$table = $this->ConvertTable($conf);
		}else{
			$this->StackError('Empty table. Please set table name.');
			return false;
		}
		
		//  set or values
		if(isset($conf['set'])){
			$set = 'SET '.$this->ConvertSet($conf);
			$values = null;
		}else if(isset($conf['values'])){
			list($set, $values) = $this->ConvertValues($conf);
		}else{
			$this->StackError("Does not 'set' or 'values'.");
			return false;
		}
		
		//  update
		if( isset($conf['update']) and $conf['update'] ){
			$ignore  = 'IGNORE';
			$update  = 'ON DUPLICATE KEY UPDATE ';
			
			if( is_bool($conf['update']) ){
				$update .= $this->ConvertSet($conf);
			}else
			if( is_array($conf['update']) ){
				$temp = $conf;
				$temp['set'] = $conf['update'];
				$update .= $this->ConvertSet($temp);
			}
		}else{
			$update = null;
		}
		
		return "INSERT INTO {$database} {$table} {$set} {$values} {$update} ";
	}

	function GetUpdate( $conf )
	{
		//  database
		if(isset($conf['database'])){
			$database = $this->ql.$conf['database'].$this->qr.'.';
		}else{
			$database = null;
		}
				
		//  table
		if(isset($conf['table'])){
			if(!$table = $this->ConvertTable($conf)){
				return false;
			}
		}else{
			$this->StackError('Empty table. Please set table name.');
			return false;
		}
		
		//  set
		if(isset($conf['set'])){
			$set = $this->ConvertSet($conf);
		}else{
			$this->StackError('Empty set.');
			return false;
		}
		
		//  where
		if(isset($conf['where'])){
			$conf['wheres']['and'] = $conf['where'];
		}
		
		//  where(s)
		if(!empty($conf['wheres'])){
			$where = 'WHERE ' . $this->ConvertWheres($conf['wheres']);
		}else{
			$this->StackError('Empty where. (ex. $conf[where][id]=1)');
			return false;
		}
		
		//  order
		if(isset($conf['order'])){
			$order = $this->ConvertOrder($conf);
		}else{
			$order = null;
		}
		
		//  limit
		if($this->_is_table_join){
			//	Can not use LIMIT by JOIN TABLE case.
			$limit = null;
		}else if( isset($conf['limit']) ){
			if( $conf['limit'] == -1 ){
				$limit = null;
			}else{
				$limit = $this->ConvertLimit($conf);
			}
		}else{
			$this->StackError('Empty limit. Update is required limit. (If want to change all, limit is -1.)');
			return false;
		}

		//  offset
		if(isset($conf['offset'])){
			if(!$offset = $this->ConvertOffset($conf)){
				return false;
			}
		}else{
			$offset = null;
		}
		
		return "UPDATE $table SET $set $where $order $limit $offset";
	}
	
	function GetDelete( $conf )
	{
		//  database
		if(isset($conf['database'])){
			$database = $this->ql.$conf['database'].$this->qr.'.';
		}else{
			$database = null;
		}
				
		//  table
		if(isset($conf['table'])){
			$table = $this->ConvertTable($conf);
		}else{
			$this->StackError('Empty table. Please set table name.');
			return false;
		}
		
		//  where
		if(isset($conf['where'])){
			$conf['wheres']['and'] = $conf['where'];
		}
		
		//  where(s)
		if(!empty($conf['wheres'])){
			$where = 'WHERE ' . $this->ConvertWheres($conf['wheres']);
		}else{
			$this->StackError('Empty where. (ex. $conf[where][id]=1)');
			return false;
		}
		
		//  order
		if( isset($conf['order']) ){
			$order = $this->ConvertOrder($conf);
		}else{
			$order = null;
		}
		
		//  offset
		if( isset($conf['offset']) ){
			$offset = $this->ConvertOffset($conf);
		}else{
			$offset = null;
		}
		
		//  limit
		if( isset($conf['limit']) ){
			if( $conf['limit'] == -1 ){
				$limit = null;
			}else{
				$limit = $this->ConvertLimit($conf);
			}
		}else{
			$this->StackError('Empty limit. Delete is required limit. (If want to change all, limit is -1.)');
			return false;
		}
		
		//  create sql
		return "DELETE FROM $table $where $order $limit $offset";
	}
	
	protected function EscapeColumn( $column )
	{
		return $this->_QuoteColumn($column);
	}
	
	private function _QuoteColumn( $column )
	{
		//  if table join
		if( strpos($column, '.') ){
			list($table_name,$column_name) = explode('.',trim($column));
			$column = $this->_quote($table_name).'.'.$this->_quote($column_name);
		}else{
			if(!empty($conf['join'])){
				//	Please tell us about the condition this is necessary. (leave comment.)
				$this->StackError('Faild column name. (if table join, table_name.column_name)');
			}
			$column = $this->_quote($column);
		}
		return $column;
	}
	
	private function _quote( $var )
	{
		return $this->ql.trim($var).$this->qr;
	}
	
	protected function ConvertTable( $conf )
	{
		if(!isset($conf['table'])){
			$this->StackError('Empty table. Please set table name.');
			return false;
		}
		
		if(!is_string($conf['table']) ){
			$type = gettype($conf['table']);
			$this->StackError("Does not implement this type at table yet. (type=$type)");
			return false;
		}
		
		if( strpos($conf['table'],'=') ){
			//  join table
			return$this->ConvertTableJoin($conf);
		}
		
		//  single table
		return $this->_quote($conf['table']);
	}
	
	protected function ConvertTableJoin( $conf )
	{
		//  quoter
		$ql = $this->ql;
		$qr = $this->qr;
		
		//  table join flag
		$this->_is_table_join = true;
		
		//  replase space
		$table = str_replace(' ', '', $conf['table']);
		
		//	explode each table
		$tables = explode( '=', $table);
		
		//	init
		$join		  = null;
		$join_flag	  = null;
		$table_join   = null;
		$table_left   = null;
		$table_right  = null;
		$column_left  = isset($conf['using']) ? $conf['using']: null;
		$column_right = isset($conf['using']) ? $conf['using']: null;
		
		//	loop (multi table)
		foreach( $tables as $table ){
			
			//	trim (space)
			$table = trim($table);
			
			//	join
			if( $table{strlen($table)-1} == '<' ){
				$join = 'LEFT JOIN';
			}else
			if( $table{0} == '>' ){
				$join = 'RIGHT JOIN';
			}else
			{
				if(!$join){
					$join = 'JOIN';
				}
			}
			
			//	trim
			$table = trim($table,'<>');
			
			//	check join column
			if( strpos( $table, '.') ){
				//	separate column
				list($table,$column) = explode('.',$table);
			}else{
				$column = null;
			}
			
			//	escape
			$table = $ql.$table.$qr;
			if( $column ){
				$column = $ql.$column.$qr;
			}
			
			//	save left side table
			if( empty($table_left) ){
				$table_left = $table;
				if( $column ){
					$column_left = $column;
				}
			}else{
				$table_right = $table;
				if( $column ){
					$column_right = $column;
				} 
			}
			
			//	Does not support using yet
			$using = null;
			$condition = null;
			
			if( $table_left and $table_right ){
				
				//	create condition
				$condition = "ON $table_left.$column_left = $table_right.$column_right";
				
				//	create table join 
				if( $join_flag === true ){
					$table_left = null;
				}
				$table_join .= "$table_left $join $table_right $condition";
				
			//	$join			 = null;
				$join_flag		 = true;
				$table_left		 = $table_right;
				$table_right	 = null;
				$column_left	 = $column_right;
				$column_right	 = null;
			}
		}
		
		return $table_join;
		
		//  using
		if( isset($conf['using']) ){
			$on    = null;
			$using = $this->ConvertUsing($conf);
		}else{
			$using = null;
		}
	}
	
	protected function ConvertUsing( $conf )
	{
		if( empty($conf['using']) ){
			return null;
		}
		
		if( is_string($conf['using']) ){
			$usings = explode(',', $conf['using']);
		}else if(is_array($conf['using'])){
			$usings = $conf['using'];
		}else if( is_object($conf['using']) ){
			$usings = Toolbox::toArray($conf['using']);
		}
		
		foreach( $usings as $str ){
			$join[] = $this->ql .trim($str). $this->qr;
		}
		
		$using = 'USING (' .implode(', ', $join). ')';
		
		return $using;
	}
	
	protected function ConvertSet( $conf )
	{
		foreach( $conf['set'] as $key => $var ){
			
			//	Escape
			if(strpos($key,'.')){
				list($table,$column) = explode('.',$key);
				$key  = '';
				$key .= $this->ql.$table.$this->qr;
				$key .= '.';
				$key .= $this->ql.$column.$this->qr;
			}else{
				$key = $this->ql.$key.$this->qr;
			}
			
			//	Case of not support value.
			if( is_array($var) or is_object($var) ){
				$type = gettype($var);
				$this->StackError("Does not supports this type. (key=$key, type=$type)");
				continue;
			}
			
			//	Case of null value.
			if( is_null($var) ){
				$join[] = "{$key}=NULL";
				continue;
			}
			
			//	Case of string or integer
			switch( is_string($var) ? strtoupper($var): $var ){
				case '':
					$var = $this->pdo->quote($var);
					break;
					
				case 'NULL':
				case 'NOW()':
					break;
					
				case '++':
					$var = "$key + 1";
					break;

				case '--':
					$var = "$key - 1";
					break;
						
			//	case 'INCREMENT(1)':
				case strncmp( $var, 'INCREMENT(', 10) === 0 ? true: false;
				case strncmp( $var, 'DECREMENT(', 10) === 0 ? true: false;
					if( preg_match('/^(IN|DE)CREMENT\((-?[0-9]+)\)$/i',$var,$match) ){
						$num = $match[2];
						$var = "$key + $num";
					}						
					break;
					
				default:
					$var = $this->pdo->quote($var);
			}
			$join[] = "{$key}={$var}";
		}
		
		return join(', ',$join);
	}
	
	protected function ConvertValues( $conf )
	{
		foreach( $conf['values'] as $key => $var ){
			//  value
			switch($var){
				case 'NULL':
				case 'NOW()':
					//  not escape
					$vars[] = $var;
					break;
				default:
					//  escape
					$var = $this->pdo->quote($var);
			}
			//  column
			$cols[] = $key;
			$vars[] = $var;
		}
		
		$set = '('.join(',',$cols).')';
		$values = 'VALUES ('.join(',',$vars).')';
		return array($set,$values);	
	}
	
	protected function ConvertSelectColumn($conf)
	{
		//  init
		$join	 = array();
		$agg	 = array();
		$alias	 = array();
		$return	 = null;
		
		if( empty($conf['column']) ){
			$cols = null;
		}else{		
			if( is_array($conf['column']) ){
				/**
				 * Example:
				 * array('id','name','timestamp');
				 * array('id'=>true,'name'=>true,'timestamp'=>false);
				 */
				$cols_temp = $conf['column'];
			}else if( is_string($conf['column']) ){
				/**
				 * Example:
				 * array('column'=>'id,name,timestamp');
				 */
				$cols_temp = explode(',',$conf['column']);
			}else{
				$this->StackError('column is not array or string.');
				return false;
			}
			
			$temp = array();
			foreach( $cols_temp as $key => $var ){
				
				if( $key === '*' ){
					/**
					 * Example: 
					 * $config->column->{'*'} = true;
					 */	
					if( $var ){
						array_unshift($temp, $key);
					}
				}else if( is_bool($var) and $var ){
					/**
					 * Example: 
					 * $config->column->column_name = true;
					 */
					$temp[] = ConfigSQL::Quote( $key, $this->driver );
				}else if( is_numeric($key) ){
					/**
					 * Example:
					 * $config->column[] = 'column_name';
					 */
					$temp[] = ConfigSQL::Quote( $var, $this->driver );
				}else{
					/**
					 * Example: 
					 * $config->column->alias_name = "t_table.column_name";
					 * $config->column->user_id = "t_user.id";
					 */
					$temp[] = ConfigSQL::Quote( $var, $this->driver )
							 ." AS "
							 .ConfigSQL::Quote( $key, $this->driver );
				}
			}
			$cols = join(', ',$temp);
			$temp = null;
		}
		
		//  
		if( isset($conf['alias']) ){
			if(!$this->ConvertAlias( $conf, $alias )){
				return false;
			}
		}
		
		//  COUNT(), MAX(), MIN(), AVG() 
		if( isset($conf['agg']) ){
			if(!$this->ConvertAggregate( $conf, $agg )){
				return false;
			}
		}
		
		if( isset($conf['case']) ){
			if(!$this->ConvertCase($conf, $join )){
				return false;
			}
		}
		
		//  select columns
		if( $cols ){
			$return = $cols;
		}
		
		//  exists select column
		if( !count($join) and !count($agg) and !count($alias) and !$cols){
			$return = '*';
		}else{
			
			//  Standard (used only case?)
			if( $join ){
				$return .= $return ? ', ': '';
				$return .= '`'.implode( '`, `', $join ).'`';
			}
			
			//  aggregate
			if( $agg ){
				$return .= $return ? ', ': '';
				$return .= implode( ', ', $agg );
			}
			
			//  alias
			if( $alias ){
				$return .= $return ? ', ': '';
				$return .= implode( ', ', $alias );
			}
		}
		
		return $return;
	}
	
	protected function ConvertAlias( $conf, &$alias )
	{
		foreach( $conf['alias'] as $from => $to ){
			//	make from
			if( strpos($from,'.') ){
				list($table,$column) = explode('.',$from);
				$from  = "";
				$from .= $this->ql.trim($this->pdo->quote($table),"'").$this->qr;
				$from .= '.';
				$from .= $this->ql.trim($this->pdo->quote($column),"'").$this->qr;
			}else{
				$from  = $this->ql.trim($this->pdo->quote($from),"'").$this->qr;
			}
			//	make to
			$to = $this->ql.trim($this->pdo->quote($to),"'").$this->qr;
			//	join
			$alias[] = "$from AS $to";
		}
		
		return true;
	}
	
	protected function ConvertAggregate( $conf, &$join )
	{
		/**
		 * COUNT() into column name, NULL is not counted.
		 * COUNT(*) is counting NULL.
		 */
		foreach( $conf['agg'] as $key => $var ){
			$key = strtoupper($key);
			$key = trim($this->pdo->quote($key), '\'');
			$var = trim($this->pdo->quote($var), '\'');
			$join[] = "$key($var)";
		}
		
		return true;
	}

	protected function ConvertWheres( $wheres, $joint=' AND ' )
	{
		foreach( $wheres as $ope => $arr ){
			switch(strtolower($ope)){
				case 'and':
				case 'or':
					$tmp = $this->ConvertWhere($arr, $ope);
					if( $tmp ){
						$join[] = $tmp;
					}
					break;
					
				//  Case of nest.
				case 'wheres':
					$join[] = $this->ConvertWheres($arr);
					break;
					
				default:
					$this->mark("![.red .bold[Does not define '$ope']]");
			}
		}
		return isset($join) ? join($joint,$join): true;
	}

	protected function ConvertWhereBetween( $conf, $joint=' AND ')
	{
		foreach($conf['between'] as $column => $value){
			$column = $this->EscapeColumn($column);
			$temp   = explode('-',$value);
			$less   = (int)$temp[0];
			$grat   = (int)$temp[1];
			$join[] = "$column BETWEEN $less AND $grat";
		}
		return '('.join(" $joint ", $join).')';
	}
	
	protected function ConvertWhereIn( $conf, $joint=' AND ')
	{
		foreach($conf['in'] as $column => $value){
			$column = $this->EscapeColumn($column);
			foreach(explode(',',$value) as $target){
				$temp[] = $this->pdo->quote(trim($target));
			}
			$join[] = "$column IN (".join(',',$temp).")";
		}
		return '('.join(" $joint ", $join).')';
	}
	
	private function _convertBetweenValue( $var )
	{
		if( preg_match('/(from)? ?([-0-9:]+) *(to|and) *([-0-9:]+)/i',$var,$match) ){
			$from = $this->pdo->quote(trim($match[2]));
			$to   = $this->pdo->quote(trim($match[4]));
			$var = "$from AND $to";
		}else{
			$this->StackError("Does not match between's value is this '$var'. (ex.: \$var = 'From 1 to 10')");
			$var = null;
		}
		return $var;
	}
	
	private function _convertInValue( $var )
	{
		foreach(explode(',',$var) as $tmp){
			$join[] = $this->pdo->quote(trim($tmp));
		}
		if(isset($join)){
			$var = "(".join(',',$join).")";
		}else{
			$io = false;
		}
		return $var;
	}
	
	private function _convertLikeValue( &$var )
	{
		$var = $this->pdo->quote($var);
		return $var;
	}
	
	private function _getWhereModifier( $key )
	{
		switch($key = strtolower($key)){
			case 'in':
				$modifier = 'IN';
				break;
			case 'not_in':
				$modifier = 'NOT IN';
				break;
			case 'like':
				$modifier = 'LIKE';
				break;
			case 'not_like':
				$modifier = 'NOT LIKE';
				break;
			case 'null':
				$modifier = 'IS NULL';
				break;
			case 'not_null':
				$modifier = 'IS NOT NULL';
				break;
			case 'between':
				$modifier = 'BETWEEN';
				break;
			case 'not_between':
				$modifier = 'NOT BETWEEN';
				break;
			default:
				$modifier = '=';
		}
		return $modifier;
	}
	
	/**
	 * Different conditions of the same column.
	 * 
	 * @param  array  $where_column
	 * @return string
	 */
	private function _convertWhereAtColumn( $where_column )
	{
		$charset = $this->GetEnv('charset');
		$result = array();
		foreach( $where_column as $column_name => $condition ){
			//	table.column -> `table`.`column`
			$column_name = $this->EscapeColumn($column_name);
			$join_and = null;
			$join_or  = null;
			foreach( $condition as $key => $csv ){
				if(is_null($csv)){ continue; }
				if(is_array($csv)){ $this->StackError("Specified at array was abolished."); continue; }
				$modifier = $this->_getWhereModifier($key);
				foreach(str_getcsv(str_replace('&quot;','"',$csv)) as $var){
					switch($modifier){
						case 'IN':
							$join_or[] = "$column_name $modifier ".$this->_convertInValue($var);
							break;
						case 'NOT IN':
							$join_and[] = "$column_name $modifier ".$this->_convertInValue($var);
							break;
						case 'LIKE':
							$join_or[] = "$column_name $modifier ".$this->_convertLikeValue($var);
							break;
						case 'NOT LIKE':
							$join_and[] = "$column_name $modifier ".$this->_convertLikeValue($var);
							break;
						case 'IS NULL':
							$join_or[] = "$column_name $modifier";
							break;
						case 'IS NOT NULL':
							$join_and[] = "$column_name $modifier";
						 	break;
						case 'BETWEEN':
							$join_or[] = "$column_name $modifier ".$this->_convertBetweenValue($var);
							break;
						case 'NOT BETWEEN':
							$join_and[] = "NOT $column_name BETWEEN ".$this->_convertBetweenValue($var);
							break;
						default:
							$join_or[] = "$column_name $modifier ".$this->pdo->quote($var);
					}
				}
			}
			//	each columns.
			$ands = empty($join_and) ? null: join(' AND ',$join_and);
			$ors  = empty($join_or)  ? null: join(' OR ', $join_or);
			
			if( $ands and $ors ){
				$result[] = "($ands) AND ($ors)";
			}else if( $ands or $ors ){
				$result[] = "($ands"."$ors)";
			}else{
				//	noting
			}
		}
		return empty($result) ? null: join(' AND ',$result);
	}
	
	protected function ConvertWhere( $where, $joint=' AND ')
	{
		$join = array();
		
		//  check
		if(!is_array($where) ){
			$this->StackError('$where is not array.');
			return false;
		}
		
		foreach($where as $key => $var){
			$modifier = strtoupper($key);
			$column = $this->EscapeColumn($key);
			
			//  value is case of some value.
			if( is_array($var) ){
				
				//  WHERE id IN ( 1, 2, 3 )
				switch(strtoupper(trim($key))){
					case 'LIKE':
					case 'NOT LIKE':
						foreach( $var as $column => $value ){
							$column = $this->EscapeColumn($column);
							$value  = $this->pdo->quote($value);
							$join[] = "$column $modifier $value";
						}
						break;
						
					case 'BETWEEN':
						foreach( $var as $column => $value ){
							$column = $this->EscapeColumn($column);
							if( preg_match('/([-0-9: ]+) ?(-|to|and) ?([-0-9: ]+)/i',$value,$match) ){
								//	OK
								$less   = (int)$match[1];
								$grat   = (int)$match[3];
							}else{
								//	NG
								$this->StackError("Does not match between format.");
							}
							$join[] = "$column BETWEEN $less AND $grat";
						}
						break;
						
					case 'IN':
					case 'NOT IN':
						foreach( $var as $column => $arr ){
							
							//	Check format (missing column name)
							if( is_numeric($column) ){
								$this->StackError('Missing column name into "IN" ');
								break;
							}
							
							foreach( $arr as $temp ){
								$in[] = $this->pdo->quote($temp);
							}
							$column = $this->EscapeColumn($column);
							$temp   = join(', ', $in);
							$join[] = "$column $modifier ( $temp )";
						}
						break;
					default:
						//var_dump($key);
						//var_dump($var);
						$tmp = $this->_convertWhereAtColumn(array($key=>$var));
						//var_dump($tmp);
						if( $tmp ){
							$join[] = $tmp;
						}
						//$this->mark("Does not support this. ($key)");
				}
				
				continue;
				
			}else if( is_null($var) or strtolower($var) === 'null' ){
				$join[] = "$column IS NULL";
				continue;
			}else if( strtolower($var) === '!null' or strtolower($var) === '! null' or strtolower($var) === 'not null' ){
				$join[] = "$column IS NOT NULL";
				continue;
		//	}else if(preg_match('/^([><]?=?) ([-0-9: ]+)$/i',$var,$match)){ // ([-0-9: ]+)$ This is only number? 
			}else if(preg_match('/^([><!]?=?) (.+)$/i',$var,$match)){
				/**
				 * ex: 
				 * $config->where->column_name = '<= $number';
				 * $config->where->column_name = '>= $number';
				 * $config->where->column_name = '!= $string';
				 * $config->where->column_name = '!  $string';
				 */
				$ope = $match[1];
				$var = trim($match[2]);
			}else{
				$ope = '=';
			}
			
			//	Adjustment
			if( $ope === '!'){
				$ope = '!=';
			}
			
			//  escape column name
			$var = $this->pdo->quote($var);
			
			//  stack where
			$join[] = "$column $ope $var";
		}
		
		return empty($join) ? null: '('.join(" $joint ", $join).')';
	}
	
	protected function ConvertGroup( $conf )
	{
		return "GROUP BY " . $this->ql.$conf['group'].$this->ql;
	}
	
	protected function ConvertHavings( $conf )
	{
		if( isset($conf['havings']['and']) ){
			$join[] = $this->ConvertHaving($conf['havings']['and'],'AND');
		}
		
		if( isset($conf['havings']['or']) ){
			$join[] = $this->ConvertHaving($conf['havings']['and'],'OR');
		}
		
		return 'HAVING '.join(' AND ', $join);
	}
	
	protected function ConvertHaving( $having, $joint )
	{
		foreach( $having as $key => $var ){
			if(preg_match('/^([><!]?=?) /i',$var,$match)){
				$ope = $match[1];
				$var = preg_replace("/^$ope /i",'',$var);
			}else{
				$ope = '=';
			}
			$key = $this->pdo->quote($key);
			$key = trim($key,'\'');
			$var = $this->pdo->quote($var);
			$join[] = "$key $ope $var";
		}
		
		return '( '.join(" $joint ",$join).' )';
	}
	
	protected function ConvertOrder( $conf )
	{
		if( is_string($conf['order']) ){
			$orders = explode(',',trim($conf['order']));
		}else{
			$orders = $conf['order'];
		}
		
		foreach( $orders as $order ){
			$sc = null;
			if( preg_match('/ (asc|desc)$/i', trim($order), $match)){
				$order = preg_replace('/ (asc|desc)$/i','',trim($order));
				$sc = " ".strtoupper($match[1]);
			}
			$join[] = $this->_QuoteColumn($order).$sc;
		}
		
		return 'ORDER BY '.join(', ',$join);
	}
	
	protected function ConvertOffset( $conf )
	{
		if( empty($conf['limit']) ){
			$this->StackError('If uses offset case, required limit.');
			return false;
		}
		return "OFFSET ".(int)$conf['offset'];
	}
	
	protected function ConvertLimit( $conf )
	{
		return "LIMIT ".(int)$conf['limit'];
	}
}
