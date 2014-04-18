<?php
/**
 * The Toolbox for present OnePiece-Framework.
 *
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 *
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
			case 'object':
				return self::toArrayFromObject($args);
		}
		
		return array($args);
	}
	
	static function toArrayFromObject( $obj )
	{
		$arr = array();
		foreach( (array)$obj as $key => $var ){
			if( is_object($var) ){
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
		switch($type = gettype($args)){
			case 'object':
				return $args;
				
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
	 * @return boolean|string|array
	 */
	static function GetRequest( $key=null, $method=null )
	{
		if(!$method){
			$method = $_SERVER['REQUEST_METHOD'];
		}
		
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
		//	init
		$scheme	 = isset($conf['scheme']) ? $conf['scheme']: true;
		$domain	 = isset($conf['domain']) ? $conf['domain']: true;
		$path	 = isset($conf['path'])   ? $conf['path']:   true;
		$query	 = isset($conf['query'])  ? $conf['query']:  false;
	
		if( $domain ){
			$domain = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR']: $_SERVER['HTTP_HOST'];
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
	
		return $scheme.$domain.$path.$query;
	}
	
	static function GetDomain( $conf=array() )
	{
		$conf['scheme'] = isset($conf['scheme']) ? $conf['scheme']: false;
		$conf['path']   = isset($conf['path'])   ? $conf['path']  : false;
		return self::GetURL($conf);
	}
	
	static function SetMIME($mime)
	{
		OnePiece5::SetEnv('mime',$mime);
	}
	
	static function GetMIME($only_sub_type=null)
	{
		if( headers_sent($file,$line) ){			
			foreach( $list = headers_list() as $header ){				
				list( $key, $var ) = explode(':',$header);
				if( strtolower($key) === 'content-type' ){					
					list($mime,$charset) = explode(';',trim($var).';'); // ; is anti notice
				}
			}
		}else{			
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
	
	static function isHtml()
	{
		$mime = self::GetMIME();
		return $mime === 'text/html' ? true: false;
	}
}
