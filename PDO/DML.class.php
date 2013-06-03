<?php
/**
 * 
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 *
 */
class DML extends OnePiece5
{
	//  OLD
	//private $pdo = null;
	private $ql  = null;
	private $qr  = null;
	private $is_table_join = null;

	//  NEW
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
	
	/*
	function Quote( $var )
	{
		if( is_array($var) ){
			foreach( $var as $tmp ){
				$safe[] = $this->Quote($tmp);
			}
		}else if( strpos($var,'.') ){
			$temp = explode('.',$var);
			$this->d($temp);
			$safe = $this->ql .trim($temp[0]). $this->qr.'.'.$this->ql .trim($temp[1]). $this->qr;
		}else{
			$safe = $this->ql .trim($var). $this->qr;
		}
		return $safe;
	}
	*/
	
	function GetSelect( $conf )
	{
		//  database
		if(isset($conf['database'])){
			$database = $this->ql.$conf['database'].$this->qr;
		}else{
			$database = null;
		}
		
		//  init flag
		$this->is_table_join = false;
		
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
		if(isset($conf['where'])){
			$conf['wheres']['and'] = $conf['where'];
		}
		
		//  where (or)
		if(isset($conf['where-or'])){
			$conf['wheres']['or'] = $conf['where-or'];
		}
		
		//  wheres
		if(isset($conf['wheres'])){
			if(!$where = 'WHERE ' . $this->ConvertWheres($conf['wheres'])){
				return false;
			}
		}else{
			$where = null;
		}
		
		//  in
		if(isset($conf['where-in'])){
			if(!$where .= $this->ConvertWhereIn($conf, 'AND') ){
				return false;
			}
		}
		
		//  in (or)
		if(isset($conf['where-in-or'])){
			if(!$where .= $this->ConvertWhereIn($conf, 'OR') ){
				return false;
			}
		}
		
		//  between (and)
		if(isset($conf['where-between'])){
			if(!$where .= $this->ConvertWhereBetween($conf, 'AND') ){
				return false;
			}
		}
		
		//  between (or)
		if(isset($conf['where-between-or'])){
			if(!$where .= $this->ConvertWhereBetween($conf, 'OR') ){
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
		
		return "SELECT $column FROM $table $where $group $having $order $limit $offset ";
	}
	
	function GetInsert( $conf )
	{
		//  database
		if(isset($conf['database'])){
			$database = $this->ql.$conf['database'].$this->qr;
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
			$database = $this->ql.$conf['database'].$this->qr;
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
		if(isset($conf['wheres'])){
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
		if( isset($conf['limit']) ){
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
			$database = $this->ql.$conf['database'].$this->qr;
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
		if(isset($conf['wheres'])){
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
		//  if table join
		if( strpos($column, '.') ){
			$temp = explode('.',trim($column));
			$column = $this->ql.trim($temp[0]).$this->ql.'.'.$this->ql.trim($temp[1]).$this->ql;
		}else{
			if(!empty($conf['join'])){
				$this->StackError('Faild column name. (if table join, table_name.column_name)');
			}
			$column = $this->ql.trim($column).$this->ql;
		}
		return $column;
	}
	
	protected function ConvertTable( $conf )
	{
		if(!isset($conf['table'])){
			$this->StackError('Empty table. Please set table name.');
			return false;
		}
		
		if( is_string($conf['table']) ){
			if( $io = strpos($conf['table'], '=') ){
				//  table join
				if(!$table = $this->ConvertTableJoin($conf) ){
					return;
				}
			}else{
				//  single table
				$table = $this->ql.$conf['table'].$this->qr;
			}
		}else{
			$this->mark('Does not implement yet.');
		}
		
		return $table;
	}
	
	protected function ConvertTableJoin( $conf )
	{
		//  current table join is only two table yet.
		
		//  replase space
		$table = str_replace(' ', '', $conf['table']);
		
		//  parse
		if(!preg_match('/^([-_a-z0-9\.]+) ?([><=]{1,3}) ?([-_a-z0-9\.]+)$/i',trim($table),$match) ){
			$this->StackError("Illigal define.({$conf['table']})");
			return false;
		}
		
		//  table join flag
		$this->is_table_join = true;
		
		$left  = $match[1];
		$ope   = $match[2];
		$right = $match[3];
		
		switch( $ope ){
			case '=':
				$join = 'JOIN';
				break;
			case '<=':
				$join = 'LEFT JOIN';
				break;
			case '=>':
				$join = 'RIGHT JOIN';
				break;
			case '>=<':
				$join = 'INNER JOIN';
				break;
			default:
				$join = $ope;
		}
		
		list( $left_table, $left_column) = explode('.',$left);
		list( $right_table, $right_column) = explode('.',$right);
		
		//  fat mode
		if( !empty($conf['fat']) ){
			
		}
		
		//  quoter
		$ql = $this->ql;
		$qr = $this->qr;
		
		//  quote
		$left_table   = $ql.$left_table.$qr;
		$right_table  = $ql.$right_table.$qr;
		$left_column  = $ql.$left_column.$qr;
		$right_column = $ql.$right_column.$qr;
		
		//  on
		$on = " ON $left_table.$left_column = $right_table.$right_column";
		
		//  using
		if( isset($conf['using']) ){
			$on    = null;
			$using = $this->ConvertUsing($conf);
		}else{
			$using = null;
		}
		
		//  join
		$joind_table = "$left_table $join $right_table $on $using";
		
		return $joind_table;
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
			/*
			if(!(is_string($var) or is_numeric($var)) ){
				$this->StackError("Set is only string. ($key)");
				continue;
			}
			*/
			if( is_array($var) or is_object($var) ){
				$type = gettype($var);
				$this->StackError("Does not supports this type. (key=$key, type=$type)");
				continue;
			}
			switch(strtoupper($var)){
				case 'NULL':
				case 'NOW()':
					break;
				default:
					$var = $this->pdo->quote($var);
			}
			$join[] = $this->ql.$key.$this->qr.'='.$var;
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
		$join = array();
		$agg  = array();
		$return = null;
		
		if( isset($conf['column']) ){
			if( is_array($conf['column']) ){
				$cols = $conf['column'];
			}else if( is_string($conf['column']) ){
				$cols = explode(',',$conf['column']);
			}else{
				$this->StackError('column is not array or string.');
				return false;
			}
			
			$temp = array();
			foreach( $cols as $key => $var ){
				if( is_numeric($key) ){
					$temp[] = ConfigSQL::Quote( $var, $this->driver );
				}else{
					$temp[] = ConfigSQL::Quote( $key, $this->driver )
							 ." AS "
							 .ConfigSQL::Quote( $var, $this->driver );
				}
			}
			$cols = join(', ',$temp);
			$temp = null;
		}else{
			$cols = null;
		}
		
		//  
		if( isset($conf['alias']) ){
			if(!$this->ConvertAlias( $conf, $join )){
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
		
		//  init
	//	$return = null;
		
		//  select columns
		if( $cols ){
			$return = $cols;
		}
		
		//  exists select column
		if( !count($join) and !count($agg) and !$cols){
			$return = '*';
		}else{
			
			//  Standard
			//if( $temp = array_diff( $join, $agg ) ){
			if( $join ){
				$return .= $return ? ', ': '';
				$return .= '`'.implode( '`, `', $join ).'`';
			}
						
			//  aggregate
			if( $agg ){
				$return .= $return ? ', ': '';
				$return .= implode( ', ', $agg );
			}
		}
		
		//  Added astrisk
		/*
		if( isset($conf['column']['all']) and $conf['column']['all'] ){
			//  No touch
		}else{
			$return = $return ? '*, '.$return: '*';
		}
		*/
		
		return $return;
	}
	
	protected function ConvertAlias( $conf, &$join )
	{
		foreach( $conf['alias'] as $key => $var ){
			$join[] = sprintf("{$this->ql}%s{$this->qr} as {$this->ql}%s{$this->qr}",
			 trim($this->pdo->quote($var),"'"), trim($this->pdo->quote($key),"'") );
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

	protected function ConvertWheres( $wheres, $joint='AND' )
	{
		foreach( $wheres as $ope => $arr ){
			switch($ope){
				case 'and':
				case 'or':
					if(!$join[] = $this->ConvertWhere($arr, $ope) ){
						return false;
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
		
		return join($joint,$join);
	}
	
	protected function ConvertWhere( $where, $joint='AND')
	{
		//  check
		if(!is_array($where) ){
			$this->StackError('$where is not array.');
			return false;
		}
		
		foreach($where as $key => $var){
			$column = $this->EscapeColumn($key);
			
			//  
			if( is_array($var) ){
				
				//  WHERE id IN ( 1, 2, 3 )
				switch($key = strtoupper(trim($key))){
					case 'LIKE':
					case 'NOT LIKE':
						foreach( $var as $column => $value ){
							$column = $this->EscapeColumn($column);
							$value  = $this->pdo->quote($value);
							$join[] = "$column $key $value";
						}
						break;
						
					case 'BETWEEN':
						foreach( $var as $column => $value ){
							$column = $this->EscapeColumn($column);
							$temp   = explode('-',$value);
							$less   = (int)$temp[0];
							$grat   = (int)$temp[1];
							$join[] = "$column BETWEEN $less TO $grat";
						}
						break;
						
					case 'IN':
					case 'NOT IN':
						foreach( $var as $column => $arr ){
							foreach( $arr as $temp ){
								$in[] = $this->pdo->quote($temp);
							}
							$column = $this->EscapeColumn($column);
							$temp   = join(', ', $in);
							$join[] = "$column $key ( $temp )";
						}
						break;
					default:
						$this->mark("Does not support this. ($key)");
				}
				
				continue;
				
			}else if( is_null($var) or strtolower($var) === 'null' ){
				//  TODO: more speed up!
				$join[] = "$column IS NULL";
				continue;
			}else if( strtolower($var) === '!null' or strtolower($var) === '! null' or strtolower($var) === 'not null' ){
				//  TODO: more speed up!
				$join[] = "$column IS NOT NULL";
				continue;
		//	}else if(preg_match('/^([><]?=) ([-0-9: ]+)$/i',$var,$match)){
			}else if(preg_match('/^([><]?=?) ([-0-9: ]+)$/i',$var,$match)){
				//  TODO: more speed up!				
				$ope = $match[1];
				$var = trim($match[2]);
			}else{
				$ope = '=';
			}
			
			//  escape column name
			$var = $this->pdo->quote($var);
			
			//  stack where
			$join[] = "$column $ope $var";
		}
		
		return '('.join(" $joint ", $join).')';
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
	//	$this->d($having);
		foreach( $having as $key => $var ){
			if(preg_match('/^([><!]?=?) /i',$var,$match)){
			//	$this->d($match);
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
		//$this->d($join);
		return '( '.join(" $joint ",$join).' )';
	}
	
	protected function ConvertOrder( $conf )
	{
		if( is_string($conf['order']) ){
			$orders = explode(',',$conf['order']);
		}else{
			$orders = $conf['order'];
		}
		
		foreach( $orders as $order ){
			if( preg_match('/^desc | desc$/i', trim($order), $match)){
				$desc = ' DESC';
				$order = preg_replace(array('/^desc | desc$/i'),'',$order);
			}else{
				$desc = '';
			}
			$order = preg_replace(array('/^asc /i'),'',$order);
			$join[] = $this->ql.trim($order).$this->qr.$desc;
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
	
	/*
	function ConvertBetween()
	{
		
	}
	
	function ConvertLikes( $likes )
	{
		
	}
	
	function ConvertLike( $column, $value )
	{
		$key = 'LIKE';
		return $this->ConvertX( $column, $key, $value );
	}
	
	function ConvertNotLike( $column, $value )
	{
		$key = 'LIKE';
		return $this->ConvertX( $column, $key, $value );
	}
	
	function ConvertX( $column, $key, $value)
	{
		if(!is_string($value)){
			$this->StackError('Does match type. not string.');
			return false;
		}
		
		if( is_null($value) ){
			$value = 'NULL';
		}else{
			$value = $this->pdo->quote($value);
		}
		
		return "$column $key $value";
	}
	
			$column = $this->EscapeColumn($key);
			
			//  
			if( is_array($var) ){
				
				//  WHERE id IN ( 1, 2, 3 )
				switch($key = strtoupper(trim($key))){
					case 'LIKE':
					case 'NOT LIKE':
						foreach( $var as $column => $value ){
							$column = $this->EscapeColumn($column);
							$value  = $this->pdo->quote($value);
							$join[] = ;
						}
						break;
						
					case 'BETWEEN':
						foreach( $var as $column => $value ){
							$column = $this->EscapeColumn($column);
							$temp   = explode('-',$value);
							$less   = (int)$temp[0];
							$grat   = (int)$temp[1];
							$join[] = "$column BETWEEN $less TO $grat";
						}
						break;
						
					case 'IN':
					case 'NOT IN':
						foreach( $var as $column => $arr ){
							foreach( $arr as $temp ){
								$in[] = $this->pdo->quote($temp);
							}
							$column = $this->EscapeColumn($column);
							$temp   = join(', ', $in);
							$join[] = "$column $key ( $temp )";
						}
						break;
					default:
						$this->mark("Does not support this. ($key)");
				}
				
				continue;
	*/
}
