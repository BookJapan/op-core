<?php
/**
 * Sample of how to make the model.
 *
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 *
 */
class Account_Model extends Model_Model
{
	function Init()
	{
		$this->Config('AccountConfig');
	}
	
	function Insert($config)
	{
		return $this->pdo()->insert($config);
	}
	
	function Select($config)
	{
		return $this->pdo()->select($config);
	}
	
	function Update($config)
	{
		return $this->pdo()->update($config);
	}
	
	function Delete($config)
	{
		return $this->pdo()->delete($config);
	}
	
	function Auth( $id, $pw )
	{
		//	Reset.
		$config = $this->config()->update_reset();
		$this->pdo()->update($config);
		
		//	
		$config = $this->config()->select_auth( $id, $pw );
		$record = $this->pdo()->select($config);
		
		//	
		$count = isset($record['failed']) ? $record['failed']: 100;
		
		//	
		$limit = $this->config()->limit_count();
		
		//	
		$io = $count < $limit ? true: false;
		
		//	failed process.
		if(!$io){
			//	record of failed times.
			$config = $this->config()->update_failed( $id );
		}
		
		return $io;
	}
	
}

class AccountConfig extends ConfigMgr
{
	private $table_prefix = 'op';
	private $table_name   = 'account';
	private $limit_time   = 600; // ten minutes.
	private $limit_count  = 10; // failed.
	
	function limit_date()
	{
		$gmtime = time() + date('Z') + $this->limit_time;
		$gmdate = gmdate('Y-m-d H:i:s',$gmtime);
		return $gmdate;
	}
	
	function limit_count()
	{
		return $this->limit_count;
	}
	
	function database()
	{
		$config = parent::database();
		$config->user     = 't_test';
		$config->password = 't_test';
		return $config;
	}
	
	function insert()
	{
		$config = parent::insert( $this->table_name() );
		return $config;
	}
	
	function select()
	{
		$config = parent::select( $this->table_name() );
		$config->limit = 1;
		return $config;
	}
	
	function select_auth( $id, $pw )
	{
		$config = $this->select();
		$config->where->id = $id;
		$config->where->password = $pw;
		return $config;
	}
	
	function select_failed()
	{
		$gmdate = $this->limit_date();
		
		$config = $this->select();
		$config->where->id = $id;
		$config->where->updated = '> $gmdate';
		
		return $config;
	}
	
	function update_reset( $id )
	{
		$gmdate = $this->limit_date();
		
		$config = parent::update( $this->table_name() );
		$config->set->failed = null;
		$config->where->updated = '< $gmdate';
		return $config;
	}
	
	function update_success( $id )
	{
		$config = parent::update( $this->table_name() );
		$config->set->failed = null;
		return $config;
	}
	
	function update_failed( $id )
	{
		$config = parent::update( $this->table_name() );
		$config->set->failed = '+1';
		return $config;
	}
}

