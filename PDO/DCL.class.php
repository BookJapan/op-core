<?php
/**
 * DCL:Data Control Language
 */
class DCL extends OnePiece5
{
	private $pdo = null;
	private $driver = null;
	
	function SetPDO( $pdo, $driver )
	{
		$this->pdo = $pdo;
		$this->driver = $driver;
	}
	
	function GetGrant( $args )
	{
		$host		 = isset($args['host'])        ? $args['host']        : null;
		$database	 = isset($args['database'])    ? $args['database']    : null;
		$table		 = isset($args['table'])       ? $args['table']       : null;
		$user		 = isset($args['user'])        ? $args['user']        : null;
		$password	 = isset($args['password'])    ? $args['password']    : null;
		$privilege	 = isset($args['privilege'])   ? $args['privilege']   : 'ALL PRIVILEGES';
		$column      = isset($args['column'])      ? $args['column']      : null;
		$identified  = isset($args['password'])    ? "IDENTIFIED BY '{$password}'": null;
		
		if(!$host){
			$this->StackError("Empty host name.");
			return false;
		}

		if(!$database){
			$this->StackError("Empty database name.");
			return false;
		}

		if(!$table){
			$this->StackError("Empty table name.");
			return false;
		}
		
		if(!$user){
			$this->StackError("Empty user name.");
			return false;
		}
		
		//  Do quote
		$database = ConfigSQL::Quote( $database, $this->driver );
		
		//  All tables, not quote. 
		if( $table !== '*' ){
			$table = ConfigSQL::Quote( $table,    $this->driver );
		}
		
		//  Quote columns
		if( $column ){
			$join = array();
			foreach( explode(',',$column) as $temp ){
				$join[] = ConfigSQL::Quote( $temp, $this->driver );
			}
			$column = implode(', ', $join);
		}
		
		//  Create Query
		$query = "GRANT {$privilege} {$column} ON {$database}.{$table} TO '{$user}'@'{$host}' $identified";
		
		return $query;
	}
	
	function GetRevoke()
	{
		
	}
}
