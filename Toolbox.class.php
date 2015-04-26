<?php
/**
 * The Toolbox for present OnePiece-Framework.
 *
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 */
class Toolbox
{
	function __call( $func, $args )
	{
		OnePiece5::mark("$func is not implements.");
	}
	
	function Command( $command, $args=array() )
	{
		$command  = escapeshellcmd($command);
		$command .= ' 2>&1';
		foreach($args as $var){
			$var = escapeshellarg($var);
			$command .= " $var";
		}
		exec( $command, $output, $return );
		$output = implode("\n",$output);
		return array('output'=>$output,'return'=>$return);
	}
	
	static function toString( $args, $separater=', ' )
	{
		$type = gettype($args);
		switch($type){
			case 'array':
			case 'object':
				break;
			
			case 'boolean':
				return $args ? 'true': 'false';
			
			default:
				return (string)$args;
		}
		
		foreach($args as $key => $var){
			if(!is_string($var)){
				$var = self::toString($var);
			}
			$join[] = $var;
		}
		
		return implode( $separater, $join );
	}
	
	static function toArray($args)
	{
		$type = gettype($args);
		switch($type){
			case 'array':
			case 'object':
				return self::toArrayFromObject($args);
		}
		
		return array($args);
	}
	
	static function toArrayFromObject( $obj )
	{
		$arr = array();
		foreach( (array)$obj as $key => $var ){
			if( is_object($var) or is_array($var) ){
				$var = self::toArrayFromObject($var);
			}
			$arr[$key] = $var;
		}
	
		return $arr;
	}
	
	static function toConfig($args)
	{
		return self::toObject($args);
	}
	
	static function toObject($args)
	{
		switch( $type = gettype($args) ){
			case 'object': // Convert child property.
			case 'array':
				return Toolbox::toObjectFromArray($args);
				
			case 'null':
			case 'NULL':
				return null;
			
			case 'string':
				print $args;
				return;
				
			default:
				print '<p>'.__METHOD__ . ": Does not support type. ($type)</p>";
		}
	}
	
	static function toObjectFromArray($arr)
	{
		$obj = new Config();
		foreach($arr as $key => $var){
			switch($type = gettype($var)){
				case 'object':
				case 'array':
					$obj->$key = Toolbox::toObjectFromArray($var);
					break;
				default:
					$obj->$key = $var;
			}
		}
		
		return $obj;
	}
	
	/**
	 * Get secure request
	 * 
	 * @param string $key      
	 * @param string $method default is $_SERVER['REQUEST_METHOD']. (GET/POST/REQUEST(include cookie)/BOTH(GET&POST))
	 * @param mixed  $default
	 * @return boolean|string|array
	 */
	static function GetRequest( $key=null, $method=null, $default=null )
	{
		//	
		if(!$method){
			$method = $_SERVER['REQUEST_METHOD'];
		}
		
		//	
		switch(strtolower($method)){
			case 'get':
				$request = $_GET;
				break;
				
			case 'post':
				$request = $_POST;
				break;
				
			case 'request': // include cookie
				$request = $_REQUEST;
				break;
				
			case 'both':
				$request = $_GET + $_POST;
				break;
				
			case 'head':
			case 'quit':
			case 'options':
				$request = array();
				break;
				
			default:
				OnePiece5::StackError("An unexpected error: empty method type. \($method)\\",'en');
				return false;
		}
		
		//	default feature
		if(!is_null($default)){
			if(!is_array($default)){
				$temp = $default;
				$default = array();
				$default[$key] = $temp;
			}
			foreach($default as $k => $v ){
				if(!isset($request[$k])){
					$request[$k] = $v;
				}
			}
		}
		
		//	
		if( is_null($key) ){
			//  null
			$args = $request;
		}else{
			//  string or array
			if( is_string($key) ){
				$keys = explode(',',$key);
			}else if( is_array($key) ){
				$keys = $key;
			}
			
			//  get intersect
			$args = array_intersect_key( $request, array_flip($keys) );
			
			//  if want only one
			if( count($keys) === 1 ){
				$args = array_shift($args);
			}
		}
		
		// Escape
		if( !is_null($args) ){
			$args = OnePiece5::Escape($args);
		}
		
		return $args;
	}
	
	static function SetRequest( $value, $key, $method=null )
	{
		if(!$method){
			$method = $_SERVER['REQUEST_METHOD'];
		}
		
		switch(strtolower($method)){
			case 'get':
				$request = &$_GET;
				break;
				
			case 'post':
				$request = &$_POST;
				break;
				
			case 'request': // include cookie
				$request = &$_REQUEST;
				break;
				
			case 'both':
				OnePiece5::mark('not yet implements');
				break;
				
			default:
				OnePiece5::StackError('An unexpected error: empty method type.');
				return false;
		}
		
		$request[$key] = OnePiece5::Escape($value);
	}
	
	/*
	function Module( $name, $args=null )
	{
		$path  = self::ConvertPath(OnePiece5::GetEnv('module-dir'));
		$path .= '/' . $name . '/' . $name.'.module.php';
		
		if( file_exists($path) ){
			include_once($path);
		}else{
			OnePiece5::StackError("does not file exists. ($name.module.php)");
			return null;
		}
		
		$module_name = 'Module_' . $name;
		
		return new $module_name($args);
	}
	*/
	
	static function Copy($object)
	{
		OnePiece5::StackError("Used checking. Is this use?");
		
		if( !$object ){
			return new OnePiece5();
		}
		
		$class_name = get_class($object);
		$return = new $class_name();
		foreach( $object as $key => $var ){
			// value
			switch( gettype($var) ){
				case 'array':
					$copy = self::Copy($var);
					break;
				case 'object':
					$copy = self::Copy($var);
					break;
				default:
					$copy = $var;
			}
			// key
			switch( gettype($object) ){
				case 'array':
					$return[$key] = $copy;
					break;
				case 'object':
					$return->{$key} = $copy;
					break;
			}
		}
		
		return $return;
	}
	
	/**
	 * Will check whether within the same network.
	 * 
	 * <pre>
	 * self::CIDR( $_SERVER['SERVER_ADDR'], $_SERVER['REMOTE_ADDR'], 27 );
	 * </pre>
	 *
	 * @param  string  $ip1
	 * @param  string  $ip2
	 * @param  integer $prefix
	 * @return boolean
	 */
	static function CIDR( $ip1, $ip2, $prefix )
	{
		OnePiece5::StackError("Used checking. Is this use?");
		
		$mask = 32 - $prefix;
		$ip1 = ip2long($ip1) >> $mask << $mask;
		$ip2 = ip2long($ip2) >> $mask << $mask;
		return $ip1 === $ip2 ? true: false;
	}
	
	/*
	function ConvertConfigFromPath( $args )
	{
		return $config;
	}
	*/
	
	static function ConvertConfigToArray( $args )
	{
		OnePiece5::StackError("Used checking. Is this use?");
		
		$type = gettype($args);
		
		switch($type){
			case 'string':
				$path = Toolbox::ConvertPath($args);
				if( file_exists($path) ){
					include($path);
				}else{
					OnePiece5::StackError("File does not exist. ($path)");
					return false;
				}
				if(isset($_config)){
					$config = $_config;
				}else if(isset($_conf)){
					$config = $_conf;
				}else if(isset($_forms)){
					$config = $_forms;
				}else if(isset($_form)){
					$config[] = $_form;
				}
				break;
			case 'array':
				$config = $args;
				break;
			case 'object':
				$config = self::ConvertArrayFromObject($args);
				break;
		}
		
		return $config;
	}
	
	static function GetFileListFromDir($path='./')
	{
		OnePiece5::StackError("Used checking. Is this use?");
		
		$list = array();
		
		if( $dir = opendir($path) ){
			while($file = readdir($dir)){
				if( $file === '.' or $file === '..' ){
					continue;
				}
				if( preg_match('|^\.|',$file)){
					continue;
				}
				$list[] = $file;
			}
		}
		
		return $list;
	}
	
	/**
	 * Get URL
	 * 
	 * @param  array $conf
	 * @return string
	 */
	static function GetURL($conf=array())
	{
		//	cache feature
		$ckey = md5(serialize($conf));
		static $cache;
		if( isset($cache[$ckey]) ){
			return $cache[$ckey];
		}
		
		//	init
		$scheme	 = isset($conf['scheme']) ? $conf['scheme']: true;
		$domain	 = isset($conf['domain']) ? $conf['domain']: true;
		$host	 = isset($conf['host'])   ? $conf['host']:   false; // Host name is not domain.
		$port	 = isset($conf['port'])   ? $conf['port']:   false;
		$path	 = isset($conf['path'])   ? $conf['path']:   true;
		$query	 = isset($conf['query'])  ? $conf['query']:  false;
		
		/**
		 * $_SERVER['HTTP_X_FORWARDED_FOR'] is IP-Address
		 * $_SERVER['HTTP_X_FORWARDED_HOST'] is Host name
		 * $_SERVER['HTTP_X_FORWARDED_SERVER'] is Server name
		 * 
		 * Difference between HTTP_X_FORWARDED_HOST and HTTP_X_FORWARDED_SERVER unclear.
		 */
		if( $domain ){
			foreach( array('HTTP_X_FORWARDED_HOST','HTTP_HOST','HOSTNAME') as $key ){
				if( isset($_SERVER[$key]) ){
					$domain = $_SERVER[$key];
				}
			}
		}else{
			$domain = null;
		}
		
		if( $scheme ){
		//	$scheme = $_SERVER['SERVER_PORT'] !== '443' ? 'http://': 'https://';
			$scheme = isset($_SERVER['HTTPS']) ? 'https://': 'http://';
		}else{
			$scheme = null;
		}
		
		if( $port ){
			$port = ':'.$_SERVER['SERVER_PORT'];
		}else{
			$port = null;
		}
		
		if(!$path){
			$path  = null;
			$query = null;
		}else
		
		if( $query ){
			$path = $_SERVER['REQUEST_URI'];
		}else{
			list($path) = explode('?', $_SERVER['REQUEST_URI']);
		}
		
		return $cache[$ckey] = $scheme.$domain.$port.$path;
	}
	
	/**
	 * Get Domain name
	 * 
	 * <pre>
	 * $conf = array( 'scheme' => true, 'port' => true, 'path' => true );
	 * </pre>
	 * 
	 * @param  array $conf
	 * @return string
	 */
	static function GetDomain( $conf=array() )
	{
		$conf['scheme'] = isset($conf['scheme']) ? $conf['scheme']: false;
		$conf['port']   = isset($conf['port'])   ? $conf['port']  : false;
		$conf['path']   = isset($conf['path'])   ? $conf['path']  : false;
		return self::GetURL($conf);
	}
	
	static function SetMIME($mime)
	{
		//	
		Env::Set('mime',$mime);
		
		//	
		$charset = Env::Get('charset');
		
		//	
		header("Content-type: $mime; charset=$charset");
	}
	
	static function GetMIME($only_sub_type=null)
	{
		static $_mime = null;
		if( $_mime ){
			if( $only_sub_type ){
				list($main, $sub) = explode('/',$_mime);
				$mime = $sub;
			}else{
				$mime = $_mime;
			}
			return $mime;
		}
		
		//	Header has already been sent.
		if( $_is_send = headers_sent($file,$line) ){
			//	Get headers list.
			foreach( $list = headers_list() as $header ){
				list($key, $var) = explode(':',$header);
				if( strtolower($key) === 'content-type' ){
					list($mime, $charset) = explode(';',trim($var).';');
				}
			}
			$_mime = $mime;
		}
		
		if( empty($mime) ){
			$mime = Env::Get('mime');
		}
		
		if( empty($mime) ){
			//	Route table base.
			if( $route = Env::Get('route') ){
				$mime = $route['mime'];
			}else{
				list($uri) = explode('?', $_SERVER['REQUEST_URI'].'?');
				if( preg_match('|\.([a-z0-9]+)$|i',$uri,$match) ){
					$mime = Router::CalcMime($match[1]);
				}
			}
		}
		
		if( empty($mime) ){
			$mime = 'text/html';
		}
		
		//	parse
		if( $mime and $only_sub_type ){
			list($temp,$mime) = explode('/',$mime);
		}
		
		return strtolower(trim($mime));
	}
	
	static function isLocalhost()
	{
		if(!isset($_SERVER['REMOTE_ADDR'])){
			return false;
		}
		
		if( $_SERVER['REMOTE_ADDR'] === '127.0.0.1' or $_SERVER['REMOTE_ADDR'] === '::1' ){
			return true;
		}
		
		return false;
	}
	
	static function isHtml()
	{
		$mime = self::GetMIME();
		return $mime === 'text/html' ? true: false;
	}
	
	static function isCLI()
	{
		return isset($_SERVER['SHELL']) ? true: false;
	}
	
	static function Curl( $url, $args=null, $method='get')
	{
		OnePiece5::StackError("This method is deprecated.");
		
		if( $args ){
			foreach( $args as $key => $var ){
				$join[] = urlencode($key).'='.urlencode($var);
			}
			$url .= '?'.join('&',$join);
		}
		
		//	CURL
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$json = curl_exec($ch);
		curl_close($ch);
	
		return json_decode($json,true);
	}
	
	static function Textarea($text)
	{
		if(!OnePiece5::Admin()){
			return;
		}
		
		print '<div style="font-size:small; margin-top:0.3em; margin-left:0.2em;">'.OnePiece5::GetCallerLine().'</div>';
		print '<textarea style="margin:2px; width:99%; height:5em;">';
		print OnePiece5::Escape($text);
		print '</textarea>'.PHP_EOL;
	}
	
	static function ConvertMeta($meta)
	{
		//	Checking meta modifier.
		if(!preg_match('|^([a-z]+):/[^/]?|i',$meta,$match)){
			return $meta;
		}
		
		//	Which are modifier.
		switch( $modifier = $match[1] ){
			case 'op':
				$real = $_SERVER['OP_ROOT'];
				break;
				
			case 'app':
				$real = $_SERVER['APP_ROOT'];
				break;
				
			case 'doc':
				$real = $_SERVER['DOC_ROOT'];
				break;
				
			case 'dot':
				static $debug_backtrace;
				if(!$debug_backtrace){
					$debug_backtrace = debug_backtrace();
				}
				while( $temp = array_shift($debug_backtrace) ){
					$func = $temp['function'];
					if( $func==='include' or $func==='include_once' or $func==='require' or $func==='require_once' or $func==='Template' or $func==='GetTemplate' ){
						$file = $temp['args'][0];
						$file = self::ConvertMeta($file);
					}else{
						continue;
					}
					$real = dirname($file).'/';
					break;
				}
				
				$debug_backtrace = null;
				break;
				
			case 'ctrl':
				$route = Env::Get('route');
				$real = rtrim($_SERVER['APP_ROOT'],'/').$route['path'];
				break;
				 
			case 'layout':
				$real = Env::Get('layout-root');
				break;
		}
		
		//	Generate full path.
		$modifier = preg_quote($modifier,'|');
		$path = preg_replace("|^$modifier:/|", $real, $meta);
		
		return $path;
	}
	
	/**
	 * Used at file system. (convert to real path)
	 * 
	 * @param string $path
	 */
	static function ConvertPathForApp($path)
	{
		static $app;
		if(!$app){
			$app = dirname($_SERVER['SCRIPT_FILENAME']).'/';
		}
		return preg_replace('|^app:/|', $app, $path);
	}
	
	/**
	 * Used at Browser. (From document root path.)
	 * 
	 * @param string $path
	 */
	static function ConvertURLforApp($url)
	{
		$app = rtrim(self::GetRewriteBase(),'/').'/';
		return preg_replace('|^app:/|', $app, $url);
	}
	
	/**
	 * Calculate rewrite base.
	 * 
	 * It's application directory from document root.
	 * 
	 * @return string;
	 */
	static function GetRewriteBase()
	{
		if( empty($_SERVER['_REWRITE_BASE_']) ){
			//	root
			$app_root = dirname($_SERVER['SCRIPT_FILENAME']);
			$doc_root = rtrim($_SERVER['DOCUMENT_ROOT'],'/');
			
			//	Check alias.
			if( preg_match("|^($doc_root)(.*)|",$app_root,$match) ){
				//	not alias
				$is_alias = false;
				$real_app_dir = $match[1];
				$rewrite_base = $match[2] ? $match[2]: '/';
			}else{
				//	alias
				$is_alias = true;
				$real_app_dir = dirname($_SERVER['SCRIPT_FILENAME']);
				$rewrite_base = dirname($_SERVER['SCRIPT_NAME']).'/';
			}
			
			$_SERVER['_REWRITE_BASE_'] = $rewrite_base;
		}
		
		return $_SERVER['_REWRITE_BASE_'];
	}
	
	/**
	 * Set rewrite base.
	 * 
	 * @param string $rewrite_base
	 */
	static function SetRewriteBase($rewrite_base)
	{
		$_SERVER['_REWRITE_BASE_'] = $rewrite_base;
	}
	
	/**
	 * Convert to path of file system from meta string.
	 * 
	 * @param  string $path
	 * @return string
	 */
	static function ConvertPath($path)
	{
		//	APP Root
		if( preg_match('|^app:/|i',$path) ){
			return self::ConvertPathForApp($path);
		}
		
		//	ConvertMeta
		return self::ConvertMeta($path);
	}
	
	/**
	 * Convert to URL of file system from meta string.
	 * 
	 * @param  string $url
	 * @return string
	 */
	static function ConvertURL($url)
	{
		//	APP Root
		if( preg_match('|^app:/|i',$url) ){
			return self::ConvertURLforApp($url);
		}
		
		//	OP Root
		if( preg_match('|^op:/|i',$url) ){
			OnePiece5::StackError("Can not convert to URL at OP-Root.");
			return null;
		}
		
		//	Document root
		if( $url{0} === '/' ){
			return $url;
		}
		
		//	Scheme less
		if( $url{0} === '/' and $url{1} === '/' ){
			return $url;
		}
		
		//	Current directory
		if( $url{0} === '.' and $url{1} === '/' ){
			return $url;
		}
		
		//	Parent directory
		if( $url{0} === '.' and $url{1} === '.' ){
			return $url;
		}
		
		//	Protocol schema
		if( preg_match('|^[a-z0-9]+://|i',$url) ){
			return $url;
		}
		
		//	Convert
		$path = self::ConvertPath($url);
		
		//	Ganarate app-root preg pattern.
		$app_root = $_SERVER['APP_ROOT'];
		$app_root = preg_quote($app_root,'/');
		$pattern  = "/^($app_root)/";
		
		//	Check root
		if(!preg_match($pattern,$path,$match)){
			//	unmatch metaphor.
			return $url;
		}
		
		//	Remove document root part.
		$patt = preg_quote($match[1],'|');
		$url  = preg_replace("|^$patt|",'',$path);
		
		//	Added base directory from document root path.
		$url = $_SERVER['REWRITE_BASE'] . $url;
		
		return $url;
	}
	
	/**
	 * Intger is convert to binary.
	 * 
	 * @param integer $int
	 * @param string  $bit
	 */
	static function toBinary($int, $bit=null)
	{
		if(!$bit){
			$bit = self::is64bit() ? 64: 32;
		}
		$bin = decbin($int);
		return str_pad($bin, $bit, 0, STR_PAD_LEFT);
	}
	
	/**
	 * Checking of 64bit memories.
	 *
	 * @see http://stackoverflow.com/questions/2353473/can-php-tell-if-the-server-os-it-64-bit
	 * @return boolean
	 */
	static function is64bit()
	{
		return intval("9223372036854775807") == 9223372036854775807 ? true: false;
	}
	
	/**
	 * Intger is convert to binary.
	 * 
	 * @param  integer $int
	 * @param  string  $bit
	 * @return string
	 */
	static function Dec2Bin($int, $bit=null)
	{
		//	2015-04-20
		OnePiece5::StackError("Abolish this method.");
		return toBinary($int, $bit);
	}
	
	/**
	 * Detect character code from string.
	 * 
	 * @see http://dwm.me/archives/3562
	 * @param  string $str
	 * @return string|NULL
	 */
	static function GetCharset($str)
	{
		$sets[] = 'UTF-8';
		$sets[] = 'SJIS';
		$sets[] = 'SJIS-WIN';
		$sets[] = 'EUC-JP';
		$sets[] = 'EUCJP-win';
		$sets[] = 'CP51932';
		$sets[] = 'ISO-2022-JP';
		$sets[] = 'ISO-2022-JP-MS';
		$sets[] = 'ASCII';
		$sets[] = 'JIS';
		foreach($sets as $charset){
			if( mb_convert_encoding($str, $charset, $charset) == $str ){
				return $charset;
			}
		}
		return null;
	}
	
	/**
	 * Will convert the string to UTF-8.
	 * 
	 * @param  string $str
	 * @param  string $from
	 * @return string
	 */
	static function toUTF8($str, $from=null)
	{
		if(!$from){
			$from = self::GetCharset($str);
		}else{
			$from = strtoupper($from);
		}
		if( $from === 'UTF-8'){
			return $str;
		}
		return mb_convert_encoding($str, 'utf-8', $from);
	}
}
