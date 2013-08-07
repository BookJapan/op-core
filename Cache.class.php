<?php

class Cache extends OnePiece5
{
	/**
	 * Instance of Memcache or Memcached.
	 * 
	 * @var Memcache|Memcached|Redis
	 */
	private $_cache = null;
	
	/**
	 * Do you want to compress the value?
	 * 
	 * @var boolean
	 */
	private $_compress = false;
	
	private $_isConnect = null;
	
	function Init()
	{
		parent::Init();
		
		//  Get use flag
		$redis     = $this->GetEnv('redis');
		$memcache  = $this->GetEnv('memcache');
		$memcached = $this->GetEnv('memcached');
		
		//	get host & port
		$host = $this->GetEnv('cache-host');
		$port = $this->GetEnv('cache-port');
		
		if( is_null($memcache)){
			$memcache = class_exists('Memcache',false);
		}
		
		if( is_null($memcached)){
			$memcached = class_exists('Memcached',false);
		}
		
		if( is_null($redis)){
			$redis = class_exists('Redis',false);
		}
		
		if( $redis ){
			if( $this->_cache = new Redis() ){
				$this->InitRedis();
			}
		}else
		
		if( $memcached ){
			if( $this->_cache = new Memcached( $host, $port ) ){
				$this->InitMemcached();
			}
		}else
		
		if( $memcache ){
			$this->InitMemcache();
		}else{
			$this->mark("not found");
		}
		
		return true;
	}
	
	function InitMemcache( $host='localhost', $port='11211', $weight=10 )
	{	
		//	instance
		if( $this->_cache = $this->GetSession('cache') ){
			return true;
		}else{
			$this->_cache = new Memcache( $host, $port );
		}
		
		//  Change modan method.
		if(!$hash_strategy = $this->GetEnv('memcache.hash_strategy') ){
			$hash_strategy = 'consistent';
		}
		//	Change to consistent from standard. (default standard)
		ini_set('memcache.hash_strategy', $hash_strategy);
		
		//	Add server
		/*
		if(!$io = $this->AddServer( $host, $port, $weight )){
			throw new Exception("Failed addServer method.");
		}
		*/
		
		//	Connect
		if( $this->GetSession('isConnect') === null ){
			$io = $this->_cache->connect($host,$port);
			$this->SetSession('isConnect',$io);
		}
	}
	
	function InitMemcached( $host='localhost', $port='11211', $weight=10 )
	{
		if(!$io = $this->AddServer( $host, $port, $weight )){
			throw new Exception("Failed addServer method.");
		}
	}
	
	function AddServer( $host='localhost', $port='11211', $weight=10 )
	{
		//  Init
		$persistent = true;
		
		//	
		//$this->mark( get_class($this->_cache) );
		switch(get_class($this->_cache)){
			case 'Memcached':
				$io = $this->_cache->addServer( $host, $port, $weight );
				break;
				
			case 'Memcache':
				$io = $this->_cache->addServer( $host, $port, $persistent, $weight );
				break;
	
			default:
				$io = false;
		}
		
		return $io;
	}
	
	function Set( $key, $value, $expire=0 )
	{
	//	$this->mark("$key, $value, $expire");
		
		//  Does not installed memcache module.
		static $skip;
		
		//	
		if( $skip ){
			return null;
		}
		
		//	Check
		if( empty($this->_cache) or empty($this->_isConnect) ){
			$skip = true;
			return null;
		}
		
		switch( $name = get_class($this->_cache) ){
			case 'Memcached':
				break;
				
			case 'Memcache':
				$compress = $this->_compress ? MEMCACHE_COMPRESSED: null;
				break;
				
			default:
				$this->mark($name);
				return false;
		}
		
		//  TODO: compress option
		return $this->_cache->Set( $key, $value, $compress, $expire );
	}
	
	function Get( $key )
	{
		static $skip;
		if( $skip ){
			return null;
		}
		
		//	Check (forever skipping?)
		if( empty($this->_cache) or empty($this->_isConnect) ){
			$skip = true;
			return null;
		}
		
		//	TODO: compress option
		$value = $this->_cache->Get( $key /* ,MEMCACHE_COMPRESSED */ );
		
		return $value;
	}
	
	function Increment( $key, $value=1 )
	{
		//	Not incremented, if does not exists value.
		return $this->_cache->increment( $key, $value );
	}
	
	function Decrement( $key, $value=1 )
	{
		//	Not decremented, if does not exists value.
		return $this->_cache->decrement( $key, $value );	
	}
	
	function Delete( $key )
	{
		return $this->_cache->delete( $key );
	}
	
	function Flash()
	{
		return $this->_cache->flush();
	}
	
	function resetServerList()
	{
		return $this->_cache->resetServerList();
	}
}

