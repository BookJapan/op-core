<?php

class Cache extends OnePiece5
{
	/**
	 * Instance of Memcache or Memcached.
	 * 
	 * @var Memcache|Memcached
	 */
	private $cache     = null;
	
	/**
	 * Do you want to compress the value?
	 * 
	 * @var boolean
	 */
	private $compress  = false;
	
	function Init()
	{
		parent::Init();
		
		//  Get value
		$memcache  = $this->GetEnv('memcache');
		$memcached = $this->GetEnv('memcached');
		
		//  If undefined
		if( is_null($memcache)){
			$memcache = class_exists('Memcache');
		}

		//  If undefined
		if( is_null($memcached)){
			$memcached = class_exists('Memcached');
		}
		
		//  Instance
		if( $memcached ){
			$this->cache = new Memcached();
			$this->InitMemcached();
		}
		
		//  Instance
		if( $memcache ){
			$this->cache = new Memcache();
			$this->InitMemcache();
		}
		
		return true;
	}
	
	function InitMemcache()
	{
		//  Change modan method.
		if(!$hash_strategy = $this->GetEnv('memcache.hash_strategy') ){
			$hash_strategy = 'consistent';
		}
		ini_set('memcache.hash_strategy', $hash_strategy);
		
		//  Added server
		if(!$io = $this->cache->addServer( $host, $port, $persistent, $weight )){
			throw new Exception("Failed addServer method.");
		}
		
		$this->AddMemcacheServer();
	}
	
	function InitMemcached( $host='localhost', $port='11211', $weight=10 )
	{
		if(!$io = $this->cache->addServer( $host, $port, $weight )){
			throw new Exception("Failed addServer method.");
		}
	}
	
	function AddMemcacheServer( $host='localhost', $port='11211', $weight=10 )
	{
		//  Init
		$persistent = true;
		
		return $this->cache->addServer( $host, $port, $persistent, $weight );
	}
	
	function Set( $key, $value, $expire=0 )
	{
		switch( $name = get_class($this->cache) ){
			case 'Memcache':
				$compress = $this->compress ? MEMCACHE_COMPRESSED: null;
				break;
			case 'Memcached':
				break;
		}
		
		//  TODO: Supports compress
		$this->cache->Set( $key, $value, $compress, $expire );
	}
	
	function Get( $key )
	{
		//	What is this?
		static $skip;
		if( $skip ){
			return null;
		}
		
		//	Check (forever skipping?)
		if( empty($this->cache) ){
			$skip = true;
			return null;
		}
		
		//	TODO:
		$value = $this->cache->Get( $key /* ,MEMCACHE_COMPRESSED */ );
		
		return $value;
	}
	
	function Increment( $key, $value=1 )
	{
		//	Not incremented, if does not exists value.
		$this->cache->increment( $key, $value );
	}
	
	function Decrement( $key, $value=1 )
	{
		//	Not decremented, if does not exists value.
		$this->cache->decrement( $key, $value );	
	}
	
	function Delete( $key )
	{
		$this->cache->delete( $key );
	}
	
	function Flash()
	{
		$this->cache->flush();
	}
}
