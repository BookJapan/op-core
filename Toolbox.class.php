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
		$this->mark("$func is not implements.");
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
				
			default:
				$this->StackError('An unexpected error: empty method type.');
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
				$this->mark('not yet implements');
				break;
				
			default:
				$this->StackError('An unexpected error: empty method type.');
				return false;
		}
		
		$request[$key] = OnePiece5::Escape($value);
	}
	
	function Module( $name, $args=null )
	{
		$path  = self::ConvertPath($this->GetEnv('module-dir'));
		$path .= '/' . $name . '/' . $name.'.module.php';
		
		if( file_exists($path) ){
			include_once($path);
		}else{
			$this->StackError("does not file exists. ($name.module.php)");
			return null;
		}
		
		$module_name = 'Module_' . $name;
		
		return new $module_name($args);
	}
	
	static function Copy($object)
	{
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
	 *
	 * ex. 接続しているリモートがサーバーのアドレスと同一ネットワーク内かチェックする
	 * self::CIDR( $_SERVER['SERVER_ADDR'], $_SERVER['REMOTE_ADDR'], 27 )
	 *
	 * @param  string  $ip1
	 * @param  string  $ip2
	 * @param  integer $prefix
	 * @return boolean
	 */
	function CIDR( $ip1, $ip2, $prefix ){
		//	maskする分をビットシフトして戻すと、末尾が等しくなる
		$mask = 32 - $prefix;
		$ip1 = ip2long($ip1) >> $mask << $mask;
		$ip2 = ip2long($ip2) >> $mask << $mask;
		return $ip1 === $ip2 ? true: false;
	}
	
	function ConvertConfigFromPath( $args )
	{
		
		return $config;
	}
	
	function ConvertConfigToArray( $args )
	{
		$type = gettype($args);
		
		switch($type){
			case 'string':
				$path = Toolbox::ConvertPath($args);
				if( file_exists($path) ){
					include($path);
				}else{
					$this->StackError("File does not exist. ($path)");
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
	
	static function GetURL( $conf=array() )
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
			$scheme = $_SERVER['SERVER_PORT'] !== '443' ? 'http://': 'https://';
		}else{
			$scheme = null;
		}
	
		if( $path ){
			$path = $_SERVER['REQUEST_URI'];
		}else{
			$path = null;
		}
	
		if( $query ){
			$query = $_SERVER['QUERY_STRING'] ? '?'.$_SERVER['QUERY_STRING']: null;
		}else{
			$query = null;
		}
		
		return $cache[$ckey] = $scheme.$domain.$path.$query;
	}
	
	static function GetDomain( $conf=array() )
	{
		$conf['scheme'] = isset($conf['scheme']) ? $conf['scheme']: false;
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
		//	Header has already been sent.
		$_is_send = headers_sent($file,$line);
		
		//	Get headers list.
		foreach( $list = headers_list() as $header ){
			list( $key, $var ) = explode(':',$header);
			if( strtolower($key) === 'content-type' ){
				list($mime,$charset) = explode(';',trim($var).';'); // ; is anti notice
			}
		}
		
		if( empty($mime) ){
			$mime = OnePiece5::GetEnv('mime');
		}
		
		if( empty($mime) ){
			$mime = 'text/html';
		}
		
		if( $only_sub_type ){
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
		if(!preg_match('|^([a-z]+):/[^/]|i',$meta,$match)){
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
	
	static function ConvertPath($path)
	{
		$path = self::ConvertMeta($path);
		return $path;
	}
	
	static function ConvertURL($url)
	{
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
		if( preg_match('|^[a-z0-9]+?://|i',$url) ){
			return $url;
		}
		
		//	Convert
		$path = self::ConvertPath($url);
		$app_root = $_SERVER['APP_ROOT'];
		$app_root = preg_quote($app_root,'|');
		$pattern  = "|^$app_root|";
		if(!preg_match($pattern,$path)){
			//	unmatch metaphor word
			return false;
		}
		
		//	Join rewrite base.
		$rewrite_base = isset($_SERVER['REWRITE_BASE']) ? $_SERVER['REWRITE_BASE']: null;
		$url = $rewrite_base . preg_replace($pattern,'',$path);
		
		return $url;
	}
}
