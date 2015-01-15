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
		$args['memcache']	 = class_exists('Memcache', false);
		$args['memcached']	 = class_exists('Memcached',false);
		$args['type']		 = $this->_cache_type;
		$args['type(real)']	 = get_class($this->_cache);
		$args['connect']	 = $this->_isConnect;
		$args['compress']	 = $this->_compress;
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
	 * @param  mixed   $value
	 * @param  integer $expire
	 * @return NULL|boolean
	 */
	function Replace( $key, $value, $expire=null )
	{
		switch( $name = get_class($this->_cache) ){
			case 'Memcached':
				//	Set
				$io = $this->_cache->replace( $key, $value, $expire );
				break;
		
			case 'Memcache':
				$compress = $this->_compress ? MEMCACHE_COMPRESSED: null;
				//	set
				$io = $this->_cache->replace( $key, $value, $compress, $expire );
				break;
		
			default:
				$this->StackError("undefine $name.");
		}
		return $io;
	}
	
	/**
	 * Set value to memcache.
	 * 
	 * @param  string  $key
	 * @param  integer|string|array $value
	 * @param  integer $expire
	 * @return NULL|boolean
	 */
	function Set( $key, $value, $expire=null )
	{
		if(!$this->_isConnect){
			return false;
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
		
		//	If expire is false.
		if( $expire === false ){
			return $this->Delete($key);
		}
		
		//	Anti Injection, and separate each domain.
		$md5 = md5( $key . $this->_domain );
		
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
				//	Set
				$io = $this->_cache->set( $md5, $value, $expire );
				break;
				
			case 'Memcache':
				$compress = $this->_compress ? MEMCACHE_COMPRESSED: null;
				//	set
				$io = $this->_cache->set( $md5, $value, $compress, $expire );
				break;
				
			default:
				$this->StackError("undefine $name.");
		}
		
		return $io;
	}
	
	function Get( $key )
	{
		if(!$this->_isConnect){
			return null;
		}
		
		//	If case of admin.
		if( $this->Admin() ){
			if(!$cache = Toolbox::GetRequest('cache',null,true) ){
				//	Cache to invalid.
				$this->mark("Cache is invalid for admin only", __CLASS__);
				return null;
			}
		}
		
		//	Check key
		if(!is_string($key)){
			$type = gettype($key);
			$this->StackError("key is not string. (type=$type)");
			return false;
		}
		
		//	Anti Injection, and separate each domain.
		$md5 = md5( $key . $this->_domain );
		
		//	
		switch( $this->_cache_type ){
			case 'memcache':
				$compress = $this->_compress ? MEMCACHE_COMPRESSED: null;
				return $this->_cache->Get( $md5, $compress );
				
			case 'memcached':
				return $this->_cache->Get( $md5 );
				
			default:
				$this->StackError("undefined {$this->_cache_type}.");
		}
	}
	
	function Cas( $key, &$value, $expire=null )
	{
		static $cas_list;
		
		//	check connection.
		if(!$this->_isConnect){
			return false;
		}
		
		//	expire
		$expire = $expire ? $expire: $this->_expire;
		
		//	Anti Injection, and separate each domain.
		$key = md5( $key . $this->_domain );
		
		//	Case of Memcache
		if( get_class($this->_cache) === 'Memcache' ){
			//	toggle
			if(!isset($cas_list[$key])){
				//	get
				$cas_list[$key] = true;
				$value = $this->Get($key);
				return false;
			}else{
				//	set
				$cas_list[$key] = false;
				return $this->Set($key, $value, $expire);
			}
		}
		
		//	Check cas value
		if(!isset($cas_list[$key])){
			$value = $this->_cache->get($key, null, $cas_list[$key]);
			if(!$cas_list[$key]){
				$io = $this->_cache->set($key, $value, $expire);
			}
			return isset($io) ? $io: false;
		}
		
		//	Check as set
		if(!$io = $this->_cache->cas($cas_list[$key], $key, $value, $expire)){
			$value = $this->_cache->get($key, null, $cas_list[$key]);
		}
		
		return $io;
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
