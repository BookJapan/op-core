<?php
# vim: tabstop=4
/**
 *  DCL5.class.php
 *
 * @version   1.0
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2011 (C) Tomoaki Nagahara All right reserved.
 * @package   op-core
 */

/**
 *  DCL5
 *
 * @version   1.0
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2011 (C) Tomoaki Nagahara All right reserved.
 * @package   op-core
 */
class DCL5 extends OnePiece5
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
		
		foreach(array('host','database','table','user') as $key){
			if(!${$key}){
				$this->StackError("Empty {$key} name.");
				return false;
			}
		}
		
		//	privilege
		$privilege = $this->GetPrivilege($args);
		
		//  database
		$database = ConfigSQL::Quote( $database, $this->driver );
		
		//  Case of all tables does not quote. 
		if( $table !== '*' ){
			$table = ConfigSQL::Quote( $table, $this->driver );
		}
		
		//	user, host
		$user = $this->pdo->quote($user);
		$host = $this->pdo->quote($host);
		
		//	password (identified)
		if( $password ){
			$identified = "IDENTIFIED BY ".$this->pdo->quote($password);
		}else{
			$identified = null;
		}
		
		//  Create Query -- GRANT {privilege} ON レベル TO user)
		$query = "GRANT {$privilege} ON {$database}.{$table} TO {$user}@{$host} $identified";
		
		return $query;
	}
	
	function GetRevoke( $args )
	{	
		$host		 = isset($args['host'])        ? $args['host']        : null;
		$database	 = isset($args['database'])    ? $args['database']    : null;
		$table		 = isset($args['table'])       ? $args['table']       : null;
		$user		 = isset($args['user'])        ? $args['user']        : null;
		
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
		
		//	privilege
		$privilege = $this->GetPrivilege($args);
		
		//  Do quote
		$database = ConfigSQL::Quote( $database, $this->driver );
		
		//  Case of all tables does not quote.
		if( $table !== '*' ){
			$table = ConfigSQL::Quote( $table, $this->driver );
		}
		
		//	Do quote
		$host = $this->pdo->quote($host);
		$user = $this->pdo->quote($user);
		
		//  Create Query
		return "REVOKE {$privilege} ON {$database}.{$table} FROM {$user}@{$host}";
	}
	
	function GetPrivilege( $args )
	{
		//	valid privilege
		$valid_privilege = array('SELECT','INSERT','UPDATE','DELETE','REFERENCES','USAGE','ALL PRIVILEGES');
		
		if( !isset($args['privilege']) ){
			
			$privilege = 'ALL PRIVILEGES';
			
		}else if( is_bool($args['privilege']) ){

			$privilege = $args['privilege'] ? 'ALL PRIVILEGES': 'USAGE';
			
		}else if( is_string($args['privilege']) ){
			
			//	String
			$join = array();
			foreach( explode(',',$args['privilege']) as $priv ){
				
				if(!in_array( strtoupper(trim($priv)), $valid_privilege) ){
					$this->StackError("Does not permit value. ($priv)");
					continue;
				}
				
				$join[] = strtoupper(trim($priv));
			}
			
			//	valid privilege (does not specified column)
			$privilege = join(', ', $join);
			
		}else if( is_array($args['privilege']) ){
				
			//	Array
			foreach( $args['privilege'] as $priv => $spec_column ){
		
				if(!in_array( strtoupper($priv), $valid_privilege) ){
					$this->StackError("Does not permit value. ($priv)");
					continue;
				}
		
				$join = array();
				foreach( explode(',',$spec_column) as $column_name ){
					$join[] = ConfigSQL::Quote( trim($column_name), $this->driver );
				}
				$column = join(', ',$join);
				$privs[] = strtoupper($priv)." ($column)";
			}
				
			$privilege = join(', ',$privs);
		}
		
		return $privilege;
	}
}
