<?php
/**
 * i18n.class.php
 * 
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 */
/**
 * i18n
 * 
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 */
class i18n extends Api
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
	
	private $_use_memcache	 = true;
	private $_use_database	 = true;
	private $_cache_expire	 = 3600; // 60 min
	
	private $_db_single		 = false;
	private $_db_prod		 = 'mysql';
	private $_db_host		 = 'localhost';
	private $_db_port		 = '3306';
	private $_db_user		 = 'i18n';
	private $_db_password	 = '';
	private $_db_name		 = 'onepiece';
	private $_db_charset	 = 'utf8';
	
	private $_table_prefix	 = 'op';
	private $_table_name	 = 'i18n';
	
	private $_lang;
	
	/**
	 * PDO interface object.
	 * 
	 * @var PDO5
	 */
	private $_pdo = null;
	
	function SetProp( $key, $var )
	{
		$this->{$key} = $var;
	}
	
	function GetProp( $key )
	{
		return $this->{$key};
	}
	
	function SetLang( $lang )
	{
		$this->_lang = $lang;
		$this->SetCookie('lang',$lang);
	}
	
	function GetLang()
	{
		if(!$lang = $this->_lang){
			$lang = $this->GetCookie('lang');
		}
		return $lang;
	}
	
	function FetchJson( $url, $expire )
	{
		//	Execute
		$body = $this->Curl($url,60*60*24*7);
		
		if(!$body){
			$this->StackError("Curl is failed.");
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
	
	function GetDatabase()
	{
		if( $this->_db_single ){
			$config = $this->GetEnv('database');
		}else{
			$config  = new Config();
			$config->driver		 = $this->_db_prod;
			$config->host		 = $this->_db_host;
			$config->port		 = $this->_db_port;
			$config->user		 = $this->_db_user;
			$config->password	 = $this->_db_password;
			$config->database	 = $this->_db_name;
			$config->charset	 = $this->_db_charset;
		}
		return $config;
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
		if($_is_connect === false){
			return false;
		}
		
		if(!$this->_pdo){
			$this->_pdo = parent::PDO($name);
		}
		
		if(!$this->_pdo->isConnect()){
			$config = $this->GetDatabase();
			if(!$_is_connect = $this->_pdo->Connect($config)){
				$config = $this->GetSelftestConfig();
				$this->Wizard()->SetSelftest(__CLASS__, $config);
				return false;
			}
		}
		
		return $this->_pdo;
	}
	
	function En($text,$to=null)
	{
		return $this->Get($text,'en',$to);
	}
	
	function Ja($text,$to=null)
	{
		return $this->Get($text,'ja',$to);
	}
	
	function Get( $text, $from='en', $to=null )
	{
		//	
		if(!$to){
			if(!$to = $this->GetLang()){
				$to = Env::Get('lang');
			}
		}
		
		//	
		$form = strtolower($from);
		$to   = strtolower($to);
		
		//	
		$url = $this->Config()->url('i18n');
		$url .= '?';
		$url .= 'text='.urlencode($text);
		$url .= '&from='.urlencode($from);
		$url .= '&to='.urlencode($to);
		
		//	Cache key.
		$key = md5($url);
		
		//	Check memcache
		if( $this->_use_memcache and $translate = $this->Cache()->Get($key) ){
			//	Hit
			return $translate;
		}
		
		//	Check database
		if( $this->_use_database and $translate = $this->Select( $text, $from, $to ) ){
			//	Hit
			if( $this->_use_memcache ){
				//	Save memcache
				$this->Cache()->Set( $key, $translate, $this->_cache_expire );
			}
			return $translate;
		}
		
		//	Get translate from API
		if(!$json = parent::Curl($url)){
			//	Fail
			return $text;
		}
		
		//	parse json
		$json = json_decode($json,true);
		
		//	check translate
		if( empty($json['translate']) ){
			//	Fail
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
	
	function GetTableName()
	{
		if( $this->_table_prefix ){
			$table_name = $this->_table_prefix.'_'.$this->_table_name;
		}else{
			$table_name = $this->_table_name;
		}
		return $table_name;
	}
	
	function Select( $text, $from, $to )
	{
		if(!$pdo = $this->pdo()){
			return false;
		}
		
		$config = array();
		$config['table'] = $this->GetTableName();
		$config['limit'] = 1;
		$config['where'][self::_COLUMN_ID_] = md5("$text, $from, $to");
		
		$record = $pdo->Select($config);
		
		return isset($record[self::_COLUMN_TEXT_TO_]) ? $record[self::_COLUMN_TEXT_TO_]: null;
	}
	
	function Insert( $text, $from, $to, $translate )
	{
		if(!$translate){
			return false;
		}

		if(!$pdo = $this->pdo()){
			return false;
		}
		
		$config = array();
		$config['table'] = $this->GetTableName();
		$config['set'][self::_COLUMN_ID_]		 = md5("$text, $from, $to");
		$config['set'][self::_COLUMN_LANG_FROM_] = $from;
		$config['set'][self::_COLUMN_LANG_TO_]	 = $to;
		$config['set'][self::_COLUMN_TEXT_FROM_] = $text;
		$config['set'][self::_COLUMN_TEXT_TO_]	 = $translate;
		$config['set']['created'] = gmdate('Y-m-d H:i:s');
		
		return $pdo->Insert($config);
	}
	
	const _COLUMN_ID_		 = 'id';
	const _COLUMN_LANG_FROM_ = 'source';
	const _COLUMN_LANG_TO_	 = 'target';
	const _COLUMN_TEXT_FROM_ = 'text';
	const _COLUMN_TEXT_TO_	 = 'translation';
	
	function GetSelftestConfig()
	{
		//  Create config
		$config = new Config();
	
		//	Title & Message
		$config->form->title   = 'Setup i18n table';
		$config->form->message = 'Please enter the root password.';
	
		//	Database
		$config->database = $this->GetDatabase();
		
		//  Tables (op_user)
		$table_name = $this->GetTableName();
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

class Config_i18n extends OnePiece5
{
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
}
