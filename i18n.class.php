<?php
/**
 * i18n.class.php
 * 
 * @version   1.0
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2013 (C) Tomoaki Nagahara All right reserved.
 * @package   op-core
 */

/**
 * i18n
 * 
 * @version   1.0
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2013 (C) Tomoaki Nagahara All right reserved.
 * @package   op-core
 */
class i18n extends OnePiece5
{
	/**
	 * @return Config_i18n
	 */
	function Config()
	{
		static $config;
		if(!$config){
			$config = new Config_i18n();
		}
		return $config;
	}
	
	private $_debug;
	private $_lang;
	private $_country;
	private $_use_memcache	 = true;
	private $_use_database	 = true;
	private $_cache_expire	 = 3600; // 60 min
	
	/**
	 * PDO interface object.
	 *
	 * @var PDO5
	 */
	private $_pdo = null;
	
	function init()
	{
		parent::init();
		if( $config = $this->GetEnv('memcache') ){
			if( isset($memcache->use) ){
				$this->_use_memcache = $memcache->use;
			}
		}
		
		$this->_debug = new Config();
		
		if( $this->_use_memcache ){
			$this->_debug->hit->memcache = 0;
		}
		
		if( $this->_use_database ){
			$this->_debug->hit->database = 0;
		}
		
		$this->_debug->hit->internet = 0;
	}
	
	function Debug()
	{
		$this->D($this->_debug);
	}
	
	function SetProp( $key, $var )
	{
		$this->{$key} = $var;
	}
	
	function GetProp( $key )
	{
		return $this->{$key};
	}
	
	function SetLocale($locale)
	{
		foreach( array('-','_') as $needle ){
			if( strpos($lang, $needle) ){
				list($lang, $country) = explode($needle, $lang);
			}
		}
		
		$this->SetLang($lang);
		$this->SetCountry($country);
	}
	
	function GetLocale()
	{
		$lang = $this->GetLang();
		if( $coutry = $this->GetCountry() ){
			return "{$lang}-{$coutry}";
		}
		return $lang;
	}
	
	/**
	 * Set language code.
	 * 
	 * @param string $lang
	 */
	function SetLang( $lang )
	{
		foreach( array('-','_') as $needle ){
			if( strpos($lang, $needle) ){
				list($lang, $country) = explode($needle, $lang);
			}
		}
		
		if( isset($country) ){
			$this->_country = $country;
		}
		
		$this->_lang = $lang;
		$this->SetCookie('lang',$lang);
	}
	
	/**
	 * Get language code.
	 * 
	 * @return string $lang
	 */
	function GetLang()
	{
		if(!$lang = $this->_lang){
			$lang = $this->GetCookie('lang','en'); // default is 'en'
		}
		return $lang;
	}
	
	/**
	 * Set country code.
	 * 
	 * @param string $country
	 */
	function SetCountry($country)
	{
		$this->_country = $country;
		$this->SetCookie('country',$country);
	}
	
	/**
	 * Get country code.
	 * 
	 * @return string $country
	 */
	function GetCountry()
	{
		if(!$country = $this->_country){
			$country = $this->GetCookie('country');
		}
		return $country;
	}
	
	function FetchJson( $url, $expire )
	{
		//	Execute
		$body = file_get_contents($url);
		
		if(!$body){
			$this->StackError("file_get_contents is failed.");
		}else{
			$json = json_decode($body,true);
			if(!$json){
				$this->StackError("JSON decode is failed.");
				$this->Cache()->Delete(md5($url));
			}
		}
		
		return isset($json) ? $json: false;
	}
	
	/**
	 * Get support language list
	 * 
	 * @param  string|null $lang
	 * @return array
	 */
	function GetLanguageList($lang=null)
	{
		//	translate language
		if(!$lang){
			$lang = $this->GetEnv('lang');
		}
		
		//	URL
		$url = $this->Config()->url('lang').$lang;
		
		//	Execute
		$json = $this->FetchJson($url, 0);
		
		//	Check
		if( $error = $json['error'] ){
			$this->StackError("Can not get language list. ($error)");
		}
		
		return isset($json['language']) ? $json['language']: array('en'=>'English');
	}
	
	/**
	 * Return PDO5 object.
	 * 
	 * @param  string $name
	 * @return PDO5
	 */
	function pdo($name=null)
	{
		static $_is_connect = null;
		if( $_is_connect === false ){
			return false;
		}
		
		if(!$this->_pdo){
			$this->_pdo = parent::PDO($name);
		}
		
		if(!$io = $this->_pdo->isConnect()){
			if(!$_is_connect = $this->_pdo->Connect($this->Config()->database())){
				if( $this->Admin() ){
					$this->Wizard()->SetNameByClass(__CLASS__);
				}else{
					$this->StackError("Does not connect to i18n database.");
				}
				return false;
			}
		}
		return $this->_pdo;
	}
	
	function Bulk( $message, $from='en-US', $to=null )
	{
		if(!$to){
			$to = $this->GetLocale();
		}
		
		$tr = $this->Get( $message, $from, $to );
		$en = $this->Get( $message, $from, $from ); // Remove back slash
		
		if( $to === $from ){
			return $tr;
		}else{
			return "$tr ($en)";
		}
	}
	
	function En($text,$to=null)
	{
		return $this->Get($text,'en-US',$to);
	}
	
	function Ja($text,$to=null)
	{
		return $this->Get($text,'ja-JP',$to);
	}
	
	function Get( $text, $from='en-US', $to=null )
	{
		//	Connection to cloud.
		static $_connection = true;
		
		//	Get locale. (ja-JP, en-US, en-UK, zh-CN, zh-TW, zh-HK )
		if(!$to){
			$to = $this->GetLocale();
		}
		
		//	If last character a backslash.
		if( strlen($text) > 2 and $text{strlen($text)-2} === '\\' ){
			$text = rtrim($text);
		}
		
		//	
		$url = $this->Config()->url('i18n');
		$url .= '?';
		$url .= 'text='.urlencode($text);
		$url .= '&from='.urlencode($from);
		$url .= '&to='.urlencode($to);
		
		//	Cache key.
		$key = md5($url);
		
		//	Check memcache
		if( $this->_use_memcache ){
			if( $translate = $this->Cache()->Get($key) ){
				//	Record of hit.
				$this->_debug->hit->memcache++;
				return $translate;
			}
		}
		
		//	Check database connect.
		if(!$this->pdo()){
			return $text;
		}
		
		//	Check database
		if( $this->_use_database){
			//	Get from database.
			if( $translate = $this->Select( $text, $from, $to ) ){
				//	Record of hit.
				$this->_debug->hit->database++;
				//	Save to memcache.
				if( $this->_use_memcache ){
					$this->Cache()->Set( $key, $translate, $this->_cache_expire );
				}
				//	return result.
				return $translate;
			}
		}
		
		//	Cloud connection.
		if(!$_connection){
			return $text;
		}
		
		//	Get translate from API
		if( $json = file_get_contents($url) ){
			//	Record of access.
			$this->_debug->hit->internet++;
		}else{
			//	Fail
			$_connection = false;
			return $text;
		}
		
		//	parse json
		$json = json_decode($json,true);
		
		//	check translate
		if( empty($json['translate']) ){
			return $text;
		}
		
		//	get translate
		$translate = $json['translate'];
		
		//	Save memcache
		if( $this->_use_memcache and $translate ){
			$this->Cache()->Set( $key, $translate, $this->_cache_expire );
		}
		
		//	Save database
		if( $this->_use_database and $translate ){
			$this->Insert( $text, $from, $to, $translate );
		}
		
		//	Case of does not fetch.
		if(!$translate){
			$translate = $text;
		}
		
		//	Finish
		return $translate;
	}
	
	function Select( $text, $from, $to )
	{
		if(!$pdo = $this->pdo()){
			return false;
		}
		
		//	get record
		$select = $this->Config()->select($text, $from, $to);
		$record = $pdo->Select($select);
		
		//	if exists
		$text = isset($record[Config_i18n::_COLUMN_TEXT_TO_]) ? $record[Config_i18n::_COLUMN_TEXT_TO_]: null;
		
		return $text;
	}
	
	function Insert( $text, $from, $to, $translate )
	{
		if(!$translate){
			return false;
		}

		if(!$pdo = $this->pdo()){
			return false;
		}
		
		$config = $this->Config()->insert($text, $from, $to, $translate);
		$result = $pdo->Insert($config);
		
		return $result;
	}
}

class Config_i18n extends OnePiece5
{
	private $_database;
	
	function url($key)
	{
		static $domain;
		if(!$domain){
			$domain = $this->Admin() ? 'http://api.uqunie.com': 'http://api.uqunie.com';
		}
		switch($key){
			case 'i18n':
				$url = "$domain/i18n/";
				break;
			case 'lang':
				$url = "$domain/i18n/lang/";
				break;
		}
		return $url;
	}
	
	function insert( $text, $from, $to, $translate )
	{
		$config = array();
		$config['table'] = $this->table_name();
		$config['set'][self::_COLUMN_ID_]		 = md5("$text, $from, $to");
		$config['set'][self::_COLUMN_LANG_FROM_] = $from;
		$config['set'][self::_COLUMN_LANG_TO_]	 = $to;
		$config['set'][self::_COLUMN_TEXT_FROM_] = $text;
		$config['set'][self::_COLUMN_TEXT_TO_]	 = $translate;
		$config['set']['created'] = gmdate('Y-m-d H:i:s');
		return $config;
	}
	
	function select( $text, $from, $to )
	{
		$database = $this->database();
		
		$config = array();
		$config['database'] = $database->name;
		$config['table'] = $this->table_name();
		$config['limit'] = 1;
	//	$config['cache'] = false; // 60*60*24*1; // 1 day
		$config['where'][self::_COLUMN_ID_] = md5("$text, $from, $to");
		
		return $config;
	}
	
	function init()
	{
		parent::Init();
		$this->_init_database();
	}
	
	private function _init_database()
	{
		$this->_database = new Config();
		$this->_database->driver = 'mysql';
		$this->_database->host	 = 'localhost';
		$this->_database->port	 = '3306';
		$this->_database->user	 = 'op_i18n';
		$this->_database->password	 = 'i18n';
		$this->_database->database	 = 'onepiece';
		$this->_database->charset	 = 'utf8';
		$this->_database->table_prefix = 'op';
		$this->_database->table_name = 'i18n';
	}
	
	function database()
	{
		static $config = null;
		if(!$config){
			//	get single database config
			if(!$config = $this->GetEnv('database')){
				$config = new Config();
			}
			
			//	old define
			if( is_string($config) ){
				$database_name = $config;
				$config = new Config();
				$config->database = (string)$database_name;
			}
			
			//	loop to each value
			foreach(array('driver','host','port','user','password','database','charset','name') as $key){
			//	if( $key === 'name'){ continue; }
				if(!is_string($config->$key)){
					$config->{$key} = $this->_database->$key;
				}
			}
			
			//	use name property
			if( isset($config->name) ){
				$config->name = $config->database;
			}else{
				$config->database = $config->name;
			}
		}
		
		return $config;
	}
	
	function table_name()
	{
		$database = $this->database();
		$prefix = isset($this->_database->table_prefix) ? $this->_database->table_prefix.'_': null;
		return $prefix.$this->_database->table_name;
	}
	
	const _COLUMN_ID_		 = 'id';
	const _COLUMN_LANG_FROM_ = 'source';
	const _COLUMN_LANG_TO_	 = 'target';
	const _COLUMN_TEXT_FROM_ = 'text';
	const _COLUMN_TEXT_TO_	 = 'translation';
	
	function selftest()
	{
		//  Create config
		$config = new Config();
	
		//	Title & Message
		$config->form->title   = 'Setup i18n table';
		$config->form->message = 'Please enter the root password.';
	
		//	Database
		$config->database = $this->database();
		
		//  Tables (op_user)
		$table_name = $this->table_name();
		$config->table->{$table_name}->table   = $table_name;
		$config->table->{$table_name}->comment = 'Translation table.';
		
		//  Columns
		$column_name = self::_COLUMN_ID_;
		$config->table->{$table_name}->column->{$column_name}->name		 = $column_name;
		$config->table->{$table_name}->column->{$column_name}->type		 = 'char';
		$config->table->{$table_name}->column->{$column_name}->length	 = '32';
		//	$config->table->{$table_name}->column->{$column_name}->index	 = true;
		$config->table->{$table_name}->column->{$column_name}->pkey		 = true;
		$config->table->{$table_name}->column->{$column_name}->comment	 = '';
		
		$column_name = self::_COLUMN_LANG_FROM_;
		$config->table->{$table_name}->column->{$column_name}->name		 = $column_name;
		$config->table->{$table_name}->column->{$column_name}->type		 = 'varchar';
		$config->table->{$table_name}->column->{$column_name}->length	 = '5';
		$config->table->{$table_name}->column->{$column_name}->comment	 = 'Original language';
		
		$column_name = self::_COLUMN_LANG_TO_;
		$config->table->{$table_name}->column->{$column_name}->name		 = $column_name;
		$config->table->{$table_name}->column->{$column_name}->type		 = 'varchar';
		$config->table->{$table_name}->column->{$column_name}->length	 = '5';
		$config->table->{$table_name}->column->{$column_name}->comment	 = 'Translation language';
		
		$column_name = self::_COLUMN_TEXT_FROM_;
		$config->table->{$table_name}->column->{$column_name}->name		 = $column_name;
		$config->table->{$table_name}->column->{$column_name}->type		 = 'text';
		$config->table->{$table_name}->column->{$column_name}->comment	 = 'Original text.';
		
		$column_name = self::_COLUMN_TEXT_TO_;
		$config->table->{$table_name}->column->{$column_name}->name		 = $column_name;
		$config->table->{$table_name}->column->{$column_name}->type		 = 'text';
		$config->table->{$table_name}->column->{$column_name}->comment	 = 'Translation text.';
		
		$column_name = 'created';
		$config->table->{$table_name}->column->{$column_name}->name		 = $column_name;
		$config->table->{$table_name}->column->{$column_name}->type		 = 'datetime';
		$config->table->{$table_name}->column->{$column_name}->comment	 = '';
		
		$column_name = 'updated';
		$config->table->{$table_name}->column->{$column_name}->name		 = $column_name;
		$config->table->{$table_name}->column->{$column_name}->type		 = 'datetime';
		$config->table->{$table_name}->column->{$column_name}->comment	 = '';
		
		$column_name = 'deleted';
		$config->table->{$table_name}->column->{$column_name}->name		 = $column_name;
		$config->table->{$table_name}->column->{$column_name}->type		 = 'datetime';
		$config->table->{$table_name}->column->{$column_name}->comment	 = '';
		
		$column_name = 'timestamp';
		$config->table->{$table_name}->column->{$column_name}->name		 = $column_name;
		$config->table->{$table_name}->column->{$column_name}->type		 = 'timestamp';
		$config->table->{$table_name}->column->{$column_name}->comment	 = '';
		
		return $config;
	}
}
