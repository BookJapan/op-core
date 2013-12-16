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
		$redis     = $this->GetEnv('USE_REDIS');
		$memcache  = $this->GetEnv('USE_MEMCACHE');
		$memcached = $this->GetEnv('USE_MEMCACHED');
		
		//	get host & port
		$host = $this->GetEnv('cache-host');
		$port = $this->GetEnv('cache-port');
		
		$host = $host ? $host : 'localhost';
		$port = $port ? $port : '11211';
		
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
			$this->InitMemcache( $host, $port );
		}else{
		//	$this->mark("not found");
		}
		
		return true;
	}
	
	function InitMemcache( $host='localhost', $port='11211', $weight=10 )
	{
		//  Change modan method.
		if(!$hash_strategy = $this->GetEnv('memcache.hash_strategy') ){
			$hash_strategy = 'consistent';
		}
		
		//	Change to consistent from standard. (default standard)
		ini_set('memcache.hash_strategy', $hash_strategy);
		
		//	Connect
		if( $this->_cache = memcache_pconnect('localhost','11211') ){
			$this->_isConnect = true;
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
	
	function CheckKeyName($key)
	{
		if( preg_match("|^([^-_a-z])$|i",$key,$match) ){
			$this->mark($match[1]);
			return false;
		}
		return true;
	}
	
	function Set( $key, $value, $expire=0 )
	{
		static $skip;
		if( $skip ){
			return null;
		}else if(!$this->_isConnect){
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
		
		//	key
		if(!is_string($key)){
		//	$key = serialize($key);
			$type = gettype($key);
			$this->StackError("key is not string. (type=$type)");
			return false;
		}
		
		//	check
		if(!$this->CheckKeyName($key)){
			$this->StackError("Illegal key name. ($key)");
			return false;
		}
		
		//  TODO: compress option
	//	$this->mark( get_class($this->_cache) );
	//	$this->mark("$key, $value, $compress, $expire");
		return $this->_cache->Set( $key, $value, $compress, $expire );
	}
	
	function Get( $key )
	{
		static $skip;
		if( $skip ){
			return null;
		}else if(!$this->_isConnect){
			$skip = true;
			return null;
		}
		
		//	key
		if(!is_string($key)){
			//	$key = serialize($key);
			$type = gettype($key);
			$this->StackError("key is not string. (type=$type)");
			return false;
		}

		//	check
		if(!$this->CheckKeyName($key)){
			$this->StackError("Illegal key name. ($key)");
			return false;
		}
		
		//	TODO: compress option
		$value = $this->_cache->Get( $key /* ,MEMCACHE_COMPRESSED */ );
		
		return $value;
	}
	
	function Increment( $key, $value=1 )
	{
		static $skip;
		if( $skip ){
			return null;
		}else if(!$this->_isConnect){
			$skip = true;
			return null;
		}
		
		//	key
		if(!is_string($key)){
			//	$key = serialize($key);
			$type = gettype($key);
			$this->StackError("key is not string. (type=$type)");
			return false;
		}

		//	check
		if(!$this->CheckKeyName($key)){
			$this->StackError("Illegal key name. ($key)");
			return false;
		}
		
		//	Not incremented, if does not exists value.
		return $this->_cache->increment( $key, $value );
	}
	
	function Decrement( $key, $value=1 )
	{
		static $skip;
		if( $skip ){
			return null;
		}else if(!$this->_isConnect){
			$skip = true;
			return null;
		}
		
		//	key
		if(!is_string($key)){
			//	$key = serialize($key);
			$type = gettype($key);
			$this->StackError("key is not string. (type=$key)");
			return false;
		}

		//	check
		if(!$this->CheckKeyName($key)){
			$this->StackError("Illegal key name. ($key)");
			return false;
		}
		
		//	Not decremented, if does not exists value.
		return $this->_cache->decrement( $key, $value );	
	}
	
	function Delete( $key )
	{
		static $skip;
		if( $skip ){
			return null;
		}else if(!$this->_isConnect){
			$skip = true;
			return null;
		}
		
		//	key
		if(!is_string($key)){
			//	$key = serialize($key);
			$type = gettype($key);
			$this->StackError("key is not string. (type=$type)");
			return false;
		}

		//	check
		if(!$this->CheckKeyName($key)){
			$this->StackError("Illegal key name. ($key)");
			return false;
		}
		
		return $this->_cache->delete( $key );
	}
	
	function Flash()
	{
		static $skip;
		if( $skip ){
			return null;
		}else if(!$this->_isConnect){
			$skip = true;
			return null;
		}
		
		return $this->_cache->flush();
	}
	
	function resetServerList()
	{
		static $skip;
		if( $skip ){
			return null;
		}else if(!$this->_isConnect){
			$skip = true;
			return null;
		}
		
		return $this->_cache->resetServerList();
	}
}
