<?php

class Cache extends OnePiece5
{
	/**
	 * Instance of Memcache or Memcached or Redis.
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
	
	/**
	 * Connection flag.
	 * 
	 * @var boolean
	 */
	private $_isConnect = null;
	
	/**
	 * String of 'memcache' or 'memcached' or 'redis'.
	 * 
	 * @var string
	 */
	private $_cache_type = null;
	
	/**
	 * If this flag is true case, skip security check for key name.
	 * (be a little faster.)
	 * 
	 * @var boolean
	 */
	private $_is_force_md5 = null;
	
	private $_use_cas = true;
	private $_cas_list = array();
	
	function Init()
	{
		parent::Init();
		
		//  Get use flag
		$use_redis     = $this->GetEnv('OP_USE_REDIS');
		$use_memcache  = $this->GetEnv('OP_USE_MEMCACHE');
		$use_memcached = $this->GetEnv('OP_USE_MEMCACHED');
		
		//	get host & port
		$host = $this->GetEnv('OP_CACHE_HOST');
		$port = $this->GetEnv('OP_CACHE_PORT');
		
		$host = $host ? $host : 'localhost';
		$port = $port ? $port : '11211';
		
		if( is_null($use_memcached)){
			$use_memcached = class_exists('Memcached',false);
		}else
		
		if( is_null($use_memcache)){
			$use_memcache = class_exists('Memcache',false);
		}else
		
		if( is_null($use_redis)){
			$use_redis = class_exists('Redis',false);
		}
		
		if( $use_redis ){
			if( $this->_cache = new Redis() ){
				$this->InitRedis();
			}
		}else
		
		if( $use_memcached ){
			$this->InitMemcached( $host, $port );
		}else
		
		if( $use_memcache ){
			$this->InitMemcache( $host, $port );
		}else{
			$this->mark("not found",'cache');
		}
		
		return true;
	}
	
	function InitMemcache( $host='localhost', $port='11211', $weight=10 )
	{
		$this->_cache_type = 'memcache';
		
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
		$this->_cache_type = 'memcached';
		
		//	Connect
		if( $this->_cache = new Memcached( $persistent_id = null ) ){
			//	Do not know whether the connection is successful at this stage.
			$this->_isConnect = true;
		}
		
		//	Add server pool.
		if(!$io = $this->AddServer( $host, $port, $weight )){
			$this->StackError('AddServer method is failed.');
		}
	}
	
	function AddServer( $host='localhost', $port='11211', $weight=10 )
	{
		//  Init
		$persistent = true;
		
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
	
	function SetCompress( $var=true )
	{
		if( $this->_cache_type === 'memcached' ){
			//	Memcached instance's default is true.
			$this->_cache->setOption( Memcached::OPT_COMPRESSION, $var );
		}else{
			$this->_compress = $var;
		}
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
		
		//	key
		if(!is_string($key)){
			$type = gettype($key);
			$this->StackError("key is not string. (type=$type)");
			return false;
		}
		
		if( $this->_is_force_md5 ){
			//	skip security check for key name.
			$key = md5($key);
		}else{
			//	security check
			if(!$this->CheckKeyName($key)){
				$this->StackError("Illegal key name. ($key)");
				return false;
			}
		}
		
		switch( $name = get_class($this->_cache) ){
			case 'Memcached':
				if( isset( $this->_cas_list[md5($key)] ) ){
					$cas = $this->_cas_list[md5($key)];
					return $this->_cache->cas( $cas, $key, $value, $expire );
				}else{
					return $this->_cache->Set( $key, $value, $expire );
				}
		
			case 'Memcache':
				$compress = $this->_compress ? MEMCACHE_COMPRESSED: null;
				return $this->_cache->Set( $key, $value, $compress, $expire );
		
			default:
				$this->StackError("undefine $name.");
		}
	}
	
	function Get( $key, $use_cas=true )
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
		
		switch( $this->_cache_type ){
			case 'memcache':
				return $this->_cache->Get( $key /* ,MEMCACHE_COMPRESSED */ );
				
			case 'memcached':
				if( $use_cas ){
					$cas = &$this->_cas_list[md5($key)];
				}else{
					$cas = null;
					unset(  $this->_cas_list[md5($key)]);
				}
				return $this->_cache->Get( $key, null, $cas );
				
			default:
				$this->StackError("undefined {$this->_cache_type}.");
		}
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
	
	function GetStatus()
	{
		$code = $this->_cache->getResultCode();
		$mess = $this->_cache->getResultMessage();
		$status = "$mess ($code)";
		return $status;
	}
}
