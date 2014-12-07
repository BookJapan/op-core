<?php
/**
 * Cache.class.php
 * 
 * @author tomoaki.nagahara <tomoaki.nagahara@gmail.com>
 */
/**
 * Cache
 * 
 * @author tomoaki.nagahara <tomoaki.nagahara@gmail.com>
 */
class Cache extends OnePiece5
{
	/**
	 * Instance of Memcache or Memcached.
	 * 
	 * @var Memcache|Memcached
	 */
	private $_cache = null;
	
	/**
	 * Do you want to compress the value?
	 * 
	 * @var boolean
	 */
	private $_compress = true;
	
	/**
	 * Connection flag.
	 * 
	 * @var boolean
	 */
	private $_isConnect = null;
	
	/**
	 * String of 'memcache' or 'memcached'.
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
	
	/**
	 * Use cas flag. (Memcached library only)
	 * 
	 * @var boolean
	 */
	private $_use_cas = true;
	
	/**
	 * List of cas. 
	 * 
	 * @var array
	 */
	private $_cas_list = array();
	
	/**
	 * Cache is separate to each domain.
	 * 
	 * @var string
	 */
	private $_domain = null;

	/**
	 * Default expire time.
	 * 
	 * Defult is 30 days.
	 * Initialization do in the init method.
	 *
	 * @var integer
	 */
	private $_expire = null;
	
	function Test()
	{
		$key = 'count';
		$var = $this->Get($key);
		$var = $var +1;
		$this->Set($key,$var);
		$this->mark('Count: '.$var);
	}
	
	function Debug()
	{
		$args = array();
		$args['type']		 = $this->_cache_type;
		$args['connect']	 = $this->_isConnect;
		$args['compress']	 = $this->_compress;
		$args['use cas']	 = $this->_use_cas;
		$args['cache']		 = $this->_cache;
		$this->d($args);
	}
	
	function Init()
	{
		parent::Init();
		
		//	get host & port
		$host = $this->GetEnv('OP_CACHE_HOST');
		$port = $this->GetEnv('OP_CACHE_PORT');
		
		$host = $host ? $host : 'localhost';
		$port = $port ? $port : '11211';
	
		$is_memcache  = class_exists('Memcache',false);
		$is_memcached = class_exists('Memcached',false);
	
		if( $is_memcached ){
			$this->InitMemcached( $host, $port );
		//	$this->SetCompress(true); // memcached instance's defualt is true?
		}else if( $is_memcache ){
			$this->InitMemcache( $host, $port );
		}else{
			$this->mark("not found Memcache and Memcached",'cache');
		}
		
		//	Cache is separate to each domain.
		$this->_domain = Toolbox::GetDomain();
		
		//	Default expire time.
		$this->_expire = 60*60*24*30;
		
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
		switch(get_class($this->_cache)){
			case 'Memcached':
				$io = $this->_cache->addServer( $host, $port, $weight );
				break;
				
			case 'Memcache':
				$persistent = true;
				$io = $this->_cache->addServer( $host, $port, $persistent, $weight );
				break;
	
			default:
				$io = false;
		}
		
		return $io;
	}
	
	function SetCompress( $var )
	{
		if( $this->_cache_type === 'memcached' ){
			//	Memcached instance's default is true.
			$this->_cache->setOption( Memcached::OPT_COMPRESSION, $var );
		}else{
			$this->_compress = $var;
		}
	}
	
	/**
	 * Replace value by key
	 * 
	 * @param  string  $key
	 * @param  integer|string|array $value
	 * @param  integer $expire
	 * @return NULL|boolean
	 */
	function Replace( $key, $value, $expire=null )
	{
		return $this->Set($key, $value, $expire, true );
	}
	
	/**
	 * Set value to memcache.
	 * 
	 * @param  string  $key
	 * @param  integer|string|array $value
	 * @param  integer $expire
	 * @param  boolean $replace
	 * @return NULL|boolean
	 */
	function Set( $key, $value, $expire=null, $replace=false )
	{
		static $skip;
		if( $skip ){
			return null;
		}else if(!$this->_isConnect){
			$skip = true;
			return null;
		}
		
		//	check value
		if( is_resource($value) ){
			$this->StackError("This key's value is resource. ($key)");
			return false;
		}
		
		//	key
		if(!is_string($key)){
			$type = gettype($key);
			$this->StackError("key is not string. (type=$type)");
			return false;
		}
		
		//	Anti Injection, and separate each domain.
		$key = md5( $key . $this->_domain );
		
		//	Can not serialize SimpleXMLElement.
		if( $value instanceof SimpleXMLElement ){
			$this->mark("Can not serialize SimpleXMLElement. (Serialization of 'SimpleXMLElement' is not allowed)");
			return false;
		}
		
		//	expire
		if( is_null($expire) ){
			$expire = $this->_expire;
		}
		
		//	
		switch( $name = get_class($this->_cache) ){
			case 'Memcached':
				$io = null;
				//	Replace
				if( $replace ){
					$io = $this->_cache->replace( $key, $value, $expire );
				}
				//	Set
				if(!$io ){
					$io = $this->_cache->set( $key, $value, $expire );
				}
				break;
				
			case 'Memcache':
				$compress = $this->_compress ? MEMCACHE_COMPRESSED: null;
				$io = null;
				//	replace
				if( $replace ){
					$io = $this->_cache->replace( $key, $value, $compress, $expire );
				}
				//	set
				if(!$io ){
					$io = $this->_cache->set( $key, $value, $compress, $expire );
				}
				break;
				
			default:
				$this->StackError("undefine $name.");
		}
		
		return $io;
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
			$type = gettype($key);
			$this->StackError("key is not string. (type=$type)");
			return false;
		}
		
		//	Anti Injection, and separate each domain.
		$key = md5( $key . $this->_domain );
		
		switch( $this->_cache_type ){
			case 'memcache':
				return $this->_cache->Get( $key /* ,MEMCACHE_COMPRESSED */ );
				
			case 'memcached':
				if( $use_cas ){
					$cas = &$this->_cas_list[$key];
				}else{
					$cas = null;
					unset($this->_cas_list[$key]);
				}
				return $this->_cache->Get( $key, null, $cas );
				
			default:
				$this->StackError("undefined {$this->_cache_type}.");
		}
	}
	
	/**
	 * Delete data by key.
	 * 
	 * @param  string $key
	 * @return NULL|boolean
	 */
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
		
		//	Anti Injection, and separate each domain.
		$key = md5( $key . $this->_domain );
		
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
