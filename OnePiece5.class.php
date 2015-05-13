<?php /* vim: set ts=4 ft=php fenc=utf-8 ff=unix: */
/**
 *  OnePiece5.class.php
 *  
 *  By using this program, you agree to our Privacy Policy, Terms of Use and End User License Agreement.
 *
 * @version   1.0
 * @package   op-core
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2009 (C) Tomoaki Nagahara All right reserved.
 */

//	Deactivates the circular reference collector.
if( $_SERVER['REMOTE_ADDR'] === '127.0.0.1' ){
	gc_disable();
}

//	Check mbstring installed.
if(!function_exists('mb_language') ){
	print "<p>Does not install php-mbstring. (ex: sudo yum install php-mbstring)</p>".PHP_EOL;
	print __FILE__.' ('.__LINE__.')<br/>'.PHP_EOL;
	exit;
}

//	If time zone is not set, then set to UTC.
if(!ini_get('date.timezone')){
	date_default_timezone_set('UTC');
}

//	Security.
$_SERVER['PHP_SELF'] = "OP:/OnePiece5.class.php, ".__LINE__;

//	OP_ROOT
$op_root = $_SERVER['OP_ROOT'] = dirname(__FILE__).'/';

//	APP_ROOT
$app_root = $_SERVER['APP_ROOT'] = dirname($_SERVER['SCRIPT_FILENAME']).'/';

//	DOC_ROOT
$doc_root = $_SERVER['DOC_ROOT'] = $_SERVER['DOCUMENT_ROOT'].'/';

//	Register autoloader.
include('Autoloader.class.php');
spl_autoload_register('Autoloader::Autoload',true,true);

//	Init Env
Env::Bootstrap();

//	Register shutdown function
register_shutdown_function('Env::Shutdown');

//	Set error heandler
$level = Toolbox::isLocalhost() ? E_ALL | E_STRICT: error_reporting();
set_error_handler('Error::Handler',$level);

//	Set exception handler
set_exception_handler('Error::ExceptionHandler');

/**
 * OnePiece5
 * 
 * This is OnePiece-Framework's core.
 * 
 * @version   1.0
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2009 (C) Tomoaki Nagahara All right reserved.
 * @package   op-core
 */
class OnePiece5
{
	const  KEY_COOKIE_UNIQ_ID	 = 'op-uniq-id';
	const _KEY_COOKIE_UNIQ_ID_	 = self::KEY_COOKIE_UNIQ_ID;
	const _KEY_SESSION_NAME_SPACE_ = '_ONE_PIECE_';
	
	private $errors  = array();
	private $session = array();
	private $_is_init  = null;
	private $_env;
	
	function __construct()
	{
		//  For all
		$this->_InitSession();
		
		//	Do Initialized in the init-method.(for extends class)
		if( method_exists($this, 'Init') ){
			//  op-root has set the first.
			if(!$this->GetEnv('op-root')){
				$this->SetEnv('op-root',dirname(__FILE__));
			}
			$this->Init();
		}
		
		$_SERVER[Env::_NAME_SPACE_]['INIT'] += 1;
	}
	
	/**
	 * 
	 */
	function __destruct()
	{
		//  Called Init?
		if(!$this->_is_init){
			$format  = '%s has not call parent::init().';
			$message = sprintf( $format, get_class($this));
			$this->StackError( $message );
		}
	}
	
	function __call( $name, $args )
	{
		$class = get_class($this);
		Error::MagicMethodCall( $class, $name, $args );
	}
	
	static function __callStatic( $name , $args )
	{
		$class = get_class($this);
		Error::MagicMethodCallStatic( $class, $name, $args);
	}
	
	function __set( $name, $args )
	{
		$class	 = get_class($this);
		$call	 = OnePiece5::GetCallerLine();
		Error::MagicMethodSet( $class, $name, $args, $call );
	}
	
	function __get( $name )
	{
		$class = get_class($this);
		$call  = $this->GetCallerLine();
		Error::MagicMethodGet( $class, $name, $call );
	}
	
	function __isset( $name )
	{
		$class = get_class($this);
		$call = $this->GetCallerLine();
		Error::MagicMethodGet( $class, $name, $call );
	}
	
	function __unset( $name )
	{
		$class = get_class($this);
		$call = $this->GetCallerLine();
		Error::MagicMethodGet( $class, $name, $call );
	}
	
	/*
	function __sleep()
	{
		
	}
	
	function __wakeup()
	{
		
	}
	*/
	
	function __toString()
	{
		OnePiece5::Mark('![.red .bold[ CATCH MAGIC METHOD ]]');
	}
	
	function __invoke()
	{
		OnePiece5::Mark('![.red .bold[ CATCH MAGIC METHOD ]]');
	}
	
	function __set_state()
	{
		OnePiece5::Mark('![.red .bold[ CATCH MAGIC METHOD ]]');
	}
	
	function Init()
	{
		$this->_is_init = true;
		return true;
	}
	
	/**
	 * You check whether you an administrator by IP-Address.
	 */
	static function Admin()
	{
		return Env::isAdmin();
	}
	
	/**
	 * Wrapper method.
	 * Error.class.php is not load until was needed.
	 * 
	 * @param number  $no
	 * @param string  $str
	 * @param string  $file
	 * @param number  $line
	 * @param unknown $context
	 */
	static function ErrorHandler( $no, $str, $file, $line, $context )
	{
		Error::Handler( $no, $str, $file, $line, $context );
	}
	
	/**
	 * Wrapper method.
	 * Error.class.php is not load until was needed.
	 * 
	 * @param Exception $e
	 */
	static function ErrorExceptionHandler( $e )
	{
		Error::ExceptionHandler( $e );
	}
	
	/**
	 * Stack of error.
	 * 
	 * The language code will be able to join the country code.
	 * 
	 * Example:
	 * 	ja, ja-JP, en, en-US, en-UK, zh-CN, zh-TW, zh-HK
	 * 
	 * @param string $message is message.
	 * @param string $translation is source language code. 
	 */
	static function StackError( $args, $locale=null )
	{
		Error::Set( $args, $locale );
	}
	
	/**
	 * Fetch stack error.
	 * 
	 * @return array
	 */
	function FetchError()
	{
		return Error::Get();
	}
	
	/**
	 * Print error.
	 */
	function PrintError()
	{
		Error::Report();
	}
	
	/**
	 * Set env-value.
	 * 
	 * @param string $key
	 * @param string|array $var
	 */
	static function SetEnv( $key, $var )
	{
		return Env::Set($key, $var);
	}

	/**
	 * Get env-value.
	 * 
	 * @param string $key
	 */
	static function GetEnv( $key )
	{
		return Env::Get($key);
	}
	
	/**
	 * Initialized the session reference to private property.
	 */
	private function _InitSession()
	{
		/**
		 * @see http://www.glamenv-septzen.net/view/29
		 */
		session_cache_limiter('private_no_expire');
		
		//  separate session.
		$this->session = &$_SESSION[self::_KEY_SESSION_NAME_SPACE_][get_class($this)];
	}
	
	/**
	 * Set session value.
	 * 
	 * @param string $key
	 * @param string|array $var
	 */
	function SetSession( $key, $var )
	{
		if( is_null($var)){
			unset($this->session[$key]);
		}else{
			$this->session[$key] = $var;
		}
		return $var;
	}

	/**
	 * Get session value.
	 * 
	 * @param string $key
	 */
	function GetSession( $key=null )
	{
		if( is_null($key)){
			return $this->session;
		}else if( isset( $this->session[$key] ) ){
			return $this->session[$key];
		}else{
			return null;
		}
	}
	
	/**
	 * SetCookie is auto set to $_COOKIE, and value is valid all value! (string, number, array and object!!)
	 *
	 * Expire value's default is 0.
	 * 0 is 365 days. -1 is out of valid expire. 
	 *
	 * @param string $key
	 * @param mixed  $value
	 * @param number $expire
	 * @param string $path
	 * @param string $domain
	 * @param number $secure
	 * @param string $httponly
	 * @return boolean
	 */
	static function SetCookie( $key, $value, $expire=0, $path='/', $domain='', $secure=0, $httponly=true )
	{
		$key   = OnePiece5::Escape($key);
		$value = OnePiece5::Escape($value);
		
		if( is_null($expire) ){
			OnePiece5::StackError("expire does not set. (ex. 0 is 365days, -1 is out of valid expire.)");
		}
		
		if( headers_sent($file,$line) ){
			OnePiece5::StackError("Header has already been sent. File: {$file}, Line number #{$line}.");
			return false;
		}
		
		if( is_null($value) ){
			$expire = time() -10;
		}else if( $expire === 0 ){
			$expire = time() + 60*60*24*365;
		}else if( $expire < time() ){
			$expire += time();
		}
		
		$_key   = $key;
		$_key   = md5($key);
		$_value = serialize($value);
		
		if (version_compare(PHP_VERSION, '5.2.0') >= 0) {
			//	httponly=true is Only http accsess. javascript cannot access.
			//	However, it is dependent on a browser.
			$io = setcookie( $_key, $_value, $expire, $path, $domain, $secure, $httponly );
		}else{
			$io = setcookie( $_key, $_value, $expire, $path, $domain, $secure );
		}
		
		if( is_null($value) ){
			unset($_COOKIE[$_key]);
		}else if( $io ){
			$_COOKIE[$_key] = $_value;
		}else{
			OnePiece5::mark('SetCookie is failed.');
			OnePiece5::StackError("SetCookie is fail: {$key}={$value}");
		}
		
		return $io;
	}
	
	/**
	 * Get Cookie by key.
	 * 
	 * @param string $key
	 * @param string $default
	 * @return Ambigous <string, string|array|object, Config>
	 */
	static function GetCookie( $key, $default=null )
	{
		$key = md5($key);
		if( isset($_COOKIE[$key]) ){
			$value = $_COOKIE[$key];
			$value = unserialize($value);
			$value = OnePiece5::Escape($value);
		}else{
			$value = $default;
		}
		return $value;
	}
	
	/**
	 * Get call line.
	 * 
	 * @param integer $depth
	 * @param integer $num
	 * @param string  $format
	 */
	static function GetCallerLine( $depth=0, $num=1, $format=null )
	{
		//	file system encoding
		$encode_file_system = PHP_OS === 'WINNT' ? 'sjis-win': 'utf-8';
		
		//	init
		$call_line = '';
		$depth++;
		$nl = self::GetEnv('nl');
		
		//	debug_backtrace
		if( version_compare(PHP_VERSION, '5.2.5') >= 0 ){
			$back = debug_backtrace(false);
		}else{
			$back = debug_backtrace();
		}
		
		//	num
		if( $num >= count($back) or $num <= 0 ){
			$num = count($back) -1;
		}
		
		//	Not exists
		if( count($back) <= $depth ){
			return 'null';
		}
		
		// loop
		for($i=1; $i <= $num; $depth++, $i++){
			
			$func   = isset($back[$depth]['function']) ? $back[$depth]['function']: null;
			$args   = isset($back[$depth]['args'])     ? $back[$depth]['args']:     null;
			$file   = isset($back[$depth]['file'])     ? $back[$depth]['file']:     null;
			$line   = isset($back[$depth]['line'])     ? $back[$depth]['line']:     null;
			$type   = isset($back[$depth]['type'])     ? $back[$depth]['type']:     null;
			$class  = isset($back[$depth]['class'])    ? $back[$depth]['class']:    null;
			$line1m = isset($back[$depth-1]['line'])   ? $back[$depth-1]['line']:   null;
			
			$filefull = $file;
			$function = $func;

			// args controll
			if(count($args) == 0){
				$args = '';
			}else if($func == 'include'){
				// does not process.
				$path = realpath($args[0]);
				$path = self::CompressPath($path);
				$args = self::Escape( $path, $encode_file_system ); // file system's encode is not html encode
				$func = $func.'("$args")';
			}else if(count($args) == 1){
				if(!$args[0]){
					$args = '![.red[null]]';
				}else{
					if( is_object($args[0]) ){
						$var_export = get_class($args[0]);
					}else{
						$var_export = var_export($args[0],true);
					}
					$str = $var_export;
					$str = str_replace(array("\n","\r","\t"), '', $str);
					$str = str_replace("\\'", "'", $str);
					$str = str_replace(",)", ") ", $str);
					$str = str_replace(",  )", ") ", $str);
					$str = self::Escape($str);
					$args = $str;
				}
			}else{
				$vars = array();
				foreach($args as $var){
					switch(gettype($var)){
						case 'string':
							$vars[] = self::Escape($var);
							break;
							
						case 'array':
							$str = '';//serialize($var);//var_export($var,true);
							$str = str_replace(array("\n","\r","\t"), '', $str);
							$str = str_replace("\\'", "'", $str);
							$str = str_replace(",)", ") ", $str);
							$str = str_replace(",  )", ") ", $str);
							$vars[] = self::Escape($str);
							break;
							
						case 'object':
							$vars[] = get_class($var);
							break;
							
						default:
							$vars[] = gettype($var);
					}
				}
				$args = implode(', ',$vars);
				$args = str_replace(array("\n","\r"), '', $args);
			}
			
			// __call is empty case
			if(empty($file)){
				$file = __FILE__;
			}
			
			// Path is shorten
			if( self::GetEnv('Pacifista') ){
				//	Does not shorten.
				$file = trim($file,"\t");
			}else{
				$file = self::CompressPath($file);
			}
			
			// method
			if( $type ){
				$method = "$class$type$func($args)";
			}else{
				$method = null;
			}
			
			switch(strtolower($format)){
				case 'mark':
					$format = '$file ($line) ';
					break;
					
				case 'incident':
					$format = '![.bold[$class$type$func]] [$line1m]' ;
					break;
				
				case 'trace':
					$format = '![div .line [$file: $line]] ![div .method [ $class$type$function($args)]] '.$nl;
					break;
					
				case '':
				case 'null':
					if( $func ){
						if( $method ){
							$format = '$file [$line] ';
						}else if( $class ){
							$format = '$file [$line] ';
						}else{
							$format = '$file [$line] ';
						}
					}else{
						$format = '$file [$line] ';
					}
					break;
				default:
				//	$format = 'This format is undefined. ($format)';
			}
			
			$patt = array('/\$filefull/','/\$file/','/\$line1m/','/\$line/','/\$class/','/\$type/','/\$function/','/\$func/','/\$args/','/\$method/');
			$repl = array($filefull, $file, $line1m, $line, $class, $type, $function, $func, $args, $method);
			$call_line .= preg_replace( $patt, $repl, $format );
		}
		
		return $call_line;
	}
	
	/**
	 * Compress file path by metaphar.
	 * 
	 * @param  string $file_path
	 * @return string $file_path
	 */
	static function CompressPath( $path )
	{
		$path = OnePiece5::Escape($path);
		
		// TODO: file system encoding. (Does not support multi language, yet)
		$encode_file_system = PHP_OS === 'WINNT' ? 'sjis-win': 'utf-8';
		if( PHP_OS == 'WINNT' ){
			$path = str_replace( '\\', '/', $path );
		}
		
		//	get each root directory.
		$op_root  = self::GetEnv('op_root');
		$app_root = self::GetEnv('app_root');
		
		//  remove slash. (easy-to-read)
		$op_root  = $op_root  ? rtrim($op_root,  '/') : ' ';
		$app_root = $app_root ? rtrim($app_root, '/') : ' ';
		
		//	replace.
		$patt = array();
		$patt[] = "|^".preg_quote($app_root)."|";
		$patt[] = "|^".preg_quote($op_root)."|";
		$repl = array('App:','OP:');
		$path = preg_replace( $patt, $repl, $path );
		
		//  easy-to-read. (op:OnePiece.class.php & app:/template/form.phtml)
		$path = preg_replace( '|(]])/([^/]+)$|', '\\1\\2', $path );
		
		return $path;
	}
	
	/**
	 * The File name, Function or Method name, and the number of line which are performed are outputed.
	 * 
	 * @param string  $str
	 * @param boolean $mark_labels
	 */
	static function Mark( $str='', $mark_labels=false )
	{
		//	display is Admin-ip only.
		if(!self::admin()){ return; }
		
		//	disable mark
		if(!self::GetEnv('mark') === false){
			return;
		}
		
		//	check display label.
		if( $mark_labels ){
			foreach( explode(',',$mark_labels) as $mark_label ){
				Developer::SetMarkLabel( $mark_label );
			}
			if(!Developer::GetSaveMarkLabelValue($mark_label) ){
				return;
			}
		}
		
		//	php momory usage 
		$memory_usage = memory_get_usage(true) /1000;
		if( strpos($memory_usage,'.') ){
			list( $mem_int, $mem_dec ) = explode( '.', $memory_usage );
		}else{
			$mem_int = $memory_usage;
			$mem_dec = 0;
		}
		$memory = sprintf('(%s.%s KB)', number_format($mem_int), $mem_dec );
		
		//	call line
		$call_line = self::GetCallerLIne(0,1,'mark');
		
		//	message
		if( is_int($str) ){
			$str = (string)$str;
		}else
		if( is_null($str) ){
			$str = '![ .red [null]]';
		}else
		if( is_bool($str) ){
			$str = $str ? '![ .blue [true]]': '![ .red [false]]';
		}else
		if( $str and !is_string($str) ){
			$str = var_export($str,true);
			$str = str_replace( array("\r","\n"), array('\r','\n'), $str);
		}
		
		//	Check Wiki2Engine
		if(!class_exists('Wiki2Engine')){
			include_once( dirname(__FILE__) .DIRECTORY_SEPARATOR. 'Wiki2Engine.class.php');
		}
		
		//	build
		$nl = PHP_EOL;
		$str = Wiki2Engine::Wiki2($str);
		$string = "{$nl}<div class=\"OnePiece mark\" style=\"font-size:small;\">{$call_line}- {$str} <span class=\"OnePiece mark memory\">{$memory}</span></div>{$nl}";
		
		//	Get mime.
		if( $mime = Toolbox::GetMIME() ){
			list($type,$mime) = explode('/',$mime);
			//	Branch to each mime
			if( $type === 'text' ){
				switch($mime){
					case 'css':
					case 'javascript':
						$string = "/* ".strip_tags(trim($string))." */{$nl}";
						break;
					case 'plain':
						$string = strip_tags(trim($string)).$nl;
						break;
				}
			}else{
				$string = null;
			}
		}
		
		print $string;
	}
	
	/**
	 * Display at html format.
	 * 
	 * @param  string $str
	 * @param  string $tag
	 * @param  array  $attr
	 * @return string
	 */
	static function Html($str, $tag='span', $attr=null)
	{
	//	$nl    = self::GetEnv('newline');
		$str   = self::Escape($str);
		$tag   = self::Escape($tag);
		$attr  = self::Escape($attr);
		$str   = self::Wiki2($str, array('tag'=>true) );
		$style = '';
		$class = '';
		
		if(isset($attr['class'])){
			if(is_array($attr['class'])){
				$temp = implode(' ',$attr['class']);
			}else{
				$temp = $attr['class'];
			}
			$class = sprintf(' class="%s"', $temp);
		}
		
		if(isset($attr['style'])){
			foreach($attr['style'] as $key => $var){
				$styles[] = "$key:$var;";
			}
			$style = sprintf(' style="%s"', implode(' ', $styles));
		}
		
		return PHP_EOL."<{$tag}{$class}{$style}>{$str}</{$tag}>".PHP_EOL;
		return sprintf($nl.'<%s %s %s>%s</%s>'.$nl, $tag, $class, $style, $str, $tag );
	}
	
	/**
	 * Display at html format by p tag.
	 * 
	 * <pre>
	 * Example:
	 * $this->P('Message','div',array('class'=>'bold blue'));
	 * </pre>
	 * 
	 * @param string $str
	 * @param string $tag
	 * @param array  $attr
	 */
	static function P( $str, $tag='p', $attr=null)
	{
		//	In case of plain text.
		if( Toolbox::isHtml() ){
			print self::Html( $str, $tag, $attr );
		}else{
			print trim( html_entity_decode(strip_tags(self::Html( $str, $tag, $attr ))) );
		}
		print PHP_EOL;
	}
	
	/**
	 * Dump
	 * 
	 * <pre>
	 * Example:
	 * $this->D($_SESSION);
	 * </pre>
	 * 
	 * @param string|array $args
	 * @param string $use_get_flag
	 */
	static function D( $args, $mark_label=null )
	{
		// displayed is only admin-ip.
		if(!self::admin()){ return; }
		
		// displayed is Admin-ip and flag.
		if( $mark_label ){
			if(!Developer::GetSaveMarkLabelValue($mark_label)){
				return;
			}
		}
		
		//	Call line.
		$line = self::GetCallerLine();
		
		//	type
		$type = gettype($args);
		
		//	join
		$line .= " - ".$type;
		
		//	CLI
		if( self::GetEnv('cli') ){
			$mime = Toolbox::GetMIME(1);
			$flag = ($mime == 'js' or $mime == 'css') ? true: false;
			if( $flag ){ print '/*'.PHP_EOL; }
			self::p($line);
			print_r($args);
			if( $flag ){ print '*/'.PHP_EOL; }
			return;
		}
		
		if( class_exists('Dump',true) ){
			self::p($line, 'div', array('class' => array('OnePiece','small','_bold','mark'), 
			                            'style' => array('color'=>'black',
			                            				 'font-size' => '9pt',
														 'background-color'=>'white'
														)));
			if( $args instanceof Config ){
				$args = Toolbox::toArray($args);
			}
			
			// Dump.class.php include by __autoloader
			Dump::d($args);
		}else{
			$line = self::Wiki2($line,array('tag'=>true));
			print strip_tags($line);
			print serialize($args);
		}
	}
	
	/**
	 * Decode value.
	 * 
	 * @param string|array|object $args
	 * @param string $charset
	 * @return string|array|object
	 */
	static function Decode( $args, $charset=null)
	{
		//  Accelerate
		static $charset = null;
		if(!$charset){
			$charset = self::GetEnv('charset');
		}
		
		switch($type = gettype($args)){
			case 'string':
				$args = html_entity_decode($args, ENT_QUOTES, $charset);
				break;
			
			case 'array':
				foreach( $args as $key => $var ){
					$key  = self::Decode( $key, $charset );
					$var  = self::Decode( $var, $charset );
					$temp[$key] = $var;
				}
				$args = $temp;
				break;
				
			case 'object':
				foreach( $args as $key => $var ){
					$key  = self::Decode( $key, $charset );
					$var  = self::Decode( $var, $charset );
					if( $type === 'array' ){
						$temp[$key] = $var;
					}else if( $type === 'object' ){
						$temp->$key = $var;
					}
				}
				$args = $temp;
				break;
				
			default:
				break;
		}
		
		return $args;
	}
	
	/**
	 * Escape value.
	 * 
	 * @param string|array|object $args
	 * @param string $charset
	 */
	static function Escape( $args, $charset=null )
	{
		if(!$charset){
			$charset = Env::Get('charset');
		}
		
		switch($type = gettype($args)){
			case 'null':
			case 'NULL':
			case 'integer':
			case 'boolean':
			case 'double':
				break;
			case 'string':
				$args = self::_EscapeString($args,$charset);
				break;
			case 'array':
				$args = self::_EscapeArray($args,$charset);
				break;
			case 'object':
				$args = self::_EscapeObject($args,$charset);
				break;
			default:
				self::p("[".__METHOD__."] undefined type($type)");
		}
		
		return $args;
	}
	
	/**
	 * Escape string.
	 * 
	 * @param string $args
	 * @param string $charset
	 */
	static private function _EscapeString( $args, $charset )
	{
		//  Anti null byte attack
		$args = str_replace("\0", '\0', $args);
		
		//  Anti ASCII Control code.
		$args = trim( $args, "\x00..\x1F");
		
		/**
		 * htmlentities's double_encoding off funciton is PHP Version 5.2.3 latter.
		 */
		if( version_compare(PHP_VERSION, '5.2.3') >= 0 ){
			$args = htmlentities( $args, ENT_QUOTES, $charset, false );
		}else{
			$args = html_entity_decode( $args, ENT_QUOTES, $charset );
			$args = htmlentities( $args, ENT_QUOTES, $charset );
		}
		
		return $args;
	}
	
	/**
	 * Escape array.
	 * 
	 * @param array  $args
	 * @param string $charset
	 */
	static private function _EscapeArray( $args, $charset )
	{
		$temp = array();
		foreach ( $args as $key => $var ){
			$key = self::_EscapeString( $key, $charset );
			$var = self::Escape( $var, $charset );
			$temp[$key] = $var;
		}
		return $temp;
	}
	
	/**
	 * Escape object.
	 * 
	 * @param array  $args
	 * @param string $charset
	 */
	static private function _EscapeObject( $args, $charset )
	{
		$temp = new Config();
		foreach ( $args as $key => $var ){
			$key = self::_EscapeString( $key, $charset );
			$var = self::Escape( $var, $charset );
			$temp->$key = $var;
		}
		return $temp;
	}
	
	function SetHeader( $str, $replace=null, $code=null )
	{
		//	2015-04-06
		self::StackError("Will abolished.");
		
		$cgi = $this->GetEnv('cgi'); // FastCGI
		
		switch($code){
			case '500':
				if($cgi){
					$str = 'Status: 500 Internal Server Error';
				}else{
					$str = 'HTTP/1.1 500 Internal Server Error';
				}
				break;
			case (preg_match('/^[0-9]+$/',$code) ? true: false):
				
				break;
		}
	
		if(headers_sent()){
			$io = false;
			$this->StackError("already header sent.");
		}else{
			$io = true;
			$str = str_replace( array("\n","\r"), '', $str );
			header( $str, $replace, $code );
		}
		
		return $io;
	}

	/**
	 * Set http header.
	 * 
	 * @param string $str
	 * @param string $replace
	 * @param string $code
	 * @return boolean
	 */
	function Header( $str, $replace=null, $code=null )
	{
		if( headers_sent() ){
			$io = false;
			$this->StackError("already header sent.");
		}else{
			$io = true;
			$str = str_replace( array("\n","\r"), '', $str );
			header( $str, $replace, $code );
		}
	
		return $io;
	}
	
	/**
	 * Forward local location.(not external URL)
	 *
	 * @param string  $url  transfer url.
	 * @param boolean $exit default is true.
	 * @return void|boolean
	 */
	function Location( $url, $exit=true )
	{
		//	Document root path
		$url = $this->ConvertUrl($url,false);
	
		//	Check infinity loop.
		if( $_SERVER['REQUEST_METHOD'] == 'POST' ){
			//	Does not for infinity.
		}else{
			$temp = explode('?',$_SERVER['REQUEST_URI']);
			if( $io = rtrim($url,'/') == rtrim($temp[0],'/') ){
				$this->mark("![.red[Location is Infinite loop. ($url)]]");
				return false;
			}
		}
	
		$io = $this->Header("Location: " . $url);
		if( $io ){
			$location['message'] = 'Do Location!!' . date('Y-m-d H:i:s');
			$location['post']	 = $_POST;
			$location['get']	 = $_GET;
			$location['referer'] = $_SERVER['REQUEST_URI'];
			$this->SetSession( 'Location', $location );
			if($exit){
				$this->__destruct();
				exit(0);
			}
		}
		return $io;
	}
	
	/**
	 * Get template
	 * 
	 * @param  string $file file name or path.(current-dir or template-dir)
	 * @param  array|Config $args  
	 * @return string
	 */
	function GetTemplate( $file, $args=null )
	{
		// ob_start is stackable
		if( ob_start() ){
			
			try{
				$this->template( $file, $args );
			}catch(Exception $e){
				//	
			}
			
			if( isset($e) ){
				if( method_exists($e,"isSelftest") ){
					if( $e->isSelftest() ){
						$this->StackError($e);
					}
				}else{
					$this->StackError($e);
				}
			}
			
			$temp = ob_get_contents();
			$io   = ob_end_clean();
		}else{
			$this->StackError("ob_start failed.");
		}
				
		return $temp;
	}
	
	/**
	 * Pirnt tempalte
	 * 
	 * @param  string $file file name or path.(current-dir or template-dir)
	 * @param  array|Config $args
	 * @return string|boolean Success is empty string return.
	 */
	function Template( $file_path, $data=null )
	{
		if(!is_string($file_path)){
			$this->StackError("Passed arguments is not string. (".gettype($file_path).")");
			return false;
		}
		
		//	Convert meta modifier.
		$file = $this->ConvertPath($file_path);
		
		//  for developper's debug
		$this->mark($file,'template');
		
		//  access is deny, above current directory
		if( $this->GetEnv('allowDoubleDot') ){
			//  OK
		}else if( preg_match('|\.\./|',$file) ){ 
			$this->StackError("Does not allow parent directory.($file)");
			return false;
		}
		
		//	necessary to convert the path?
		if( file_exists($file) ){
			//  absolute
			$path = $file;
		}else if( file_exists($path = self::ConvertPath($file)) ){
			//  abstract
		}else if( $dir = Env::Get('template-dir') ){
			// the path is converted.
			$dir  = self::ConvertPath($dir);
			$path = rtrim($dir,'/').'/'.$file;
		}else{
			$path = $file;
		}
		
		// 2nd check
		if(!file_exists($path)){
			$message = $this->i18n()->Bulk("This template file does not exist.");
			$this->StackError("$message path=$path");
			return false;
		}
		
		// extract array
		if( is_array($data) and count($data) ){
			if(isset($data[0])){
				$this->Mark('Missing? $data is array. (not assoc) / ex. $this->Template("index.phtml",array("test"=>"success")');
			}else{
				extract($data);
			}
		}else if(is_object($data)){
			// object
		}
		
		// read file
		$io = include($path);
		
		return $io ? '': false;
	}
	
	/**
	 * Convert to browser url from meta-path. (base is document root.)
	 * 
	 * @param  string  $path
	 * @param  boolean $domain is Deprecated.
	 * @return string
	 */
	static function ConvertURL( $meta, $domain=false )
	{
		if( $domain ){
			//	Added about 2014-Q4.
			OnePiece5::StackError("\domain\ option is obsolete. Please use \Toolbox::GetDomain\ method.");
		}
		return Toolbox::ConvertURL($meta);
	}
	
	/**
	 * Convert server-side full-path from meta-path.
	 * 
	 * @param  string $path
	 * @return string
	 */
	static function ConvertPath( $meta )
	{
		return Toolbox::ConvertPath($meta);
	}
	
	/**
	 * Returns a singleton of the model.
	 * 
	 * <pre>
	 * $this->Model('Login')->GetLoginId();
	 * </pre>
	 * 
	 * @param  string $name
	 * @throws OpException
	 * @return Model_Model
	 */
	static function Model($name)
	{
		try{
			if(!$name){
				throw new OpException("Model name is empty.",'en');
			}

			//	hogeHoge -> HogeHoge	//	hogeHoge -> Hogehoge
			//	$name = ucfirst($name);		//	$name = ucfirst(strtolower($name));
			
			if( empty($_SERVER[__CLASS__]['model'][$name]) ){

				$model_name = $name;
				$class_name = "Model_{$name}";
				$file_name  = "{$name}.model.php";
				
				self::_ModelNameCheck($name);
				
				if( class_exists($class_name,false) ){
					//  OK
				}else if( file_exists(getcwd()."/{$file_name}") ){
					//  OK
					include(getcwd()."/{$file_name}");
				}else if( self::_ModelLoadFromApp($file_name) ){
					//  OK
				}else if( self::_ModelLoadFromCore($file_name) ){
					//  OK
				}else{
					throw new OpException("This file not found. \($file_name)\\",'en');
				}
				
				if( class_exists($class_name,false) ){
					$_SERVER[__CLASS__]['model'][$name] = new $class_name();
				}else{
					throw new OpException("This file could not be loaded. \($class_name)\\",'en');
				}
			}
		}catch( Exception $e ){
			$file = $e->getFile();
			$line = $e->getLine();
			$text = $e->getMessage();
			self::StackError("$text\n ![.gray[$file, $line]]",'en');
			return new OnePiece5();
		}
		
		return $_SERVER[__CLASS__]['model'][$name];
	}
	
	static private function _ModelNameCheck($name)
	{
		//  Notice
		if( strpos( $name, '_') !== false ){
			$message = 'Underscore(_) is reserved. For the feature functions. (maybe, namespace)';
			$message = self::i18n()->Bulk($message);
			$this->StackError("$message");
		}
	}
	
	/**
	 * File include from app's model directory.
	 */
	static private function _ModelLoadFromApp($file_name)
	{
		//	App's model directory.
		if(!$model_dir = self::GetEnv('model-dir')){
			return false;
		}
		
		//	Convert path.
		$path = self::ConvertPath($model_dir);
		
		//	Check model directory.
		if(!file_exists($path)){
			throw new OpException("This directory has not exists. \($path)\\",'en');
		}
		
		//	Added file name.
		$path .= $file_name;
		
		//	include
		if( $io = file_exists($path) ){
			$io = include_once($path);
		}
		
		return $io;
	}
	
	/**
	 * File include from op-core's model directory.
	 */
	static private function _ModelLoadFromCore($file_name)
	{
		$path = self::ConvertPath("op:/Model/$file_name");
		
		if( $io = file_exists($path) ){
			$io = include_once($path);
		}
		
		return $io;
	}
	
	/**
	 * PDO5 Object
	 * 
	 * @var PDO5
	 */
	private $_pdo = null;
	
	/**
	 * Get PDO5 object
	 * 
	 * @param  $name class name
	 * @return PDO5
	 */
	function PDO()
	{
		if(!$this->_pdo){
			$op_root = $this->GetEnv('op-root');
			$op_root = rtrim($op_root,'/').'/';
			$path = $op_root.'PDO/PDO5.class.php';
			
			if(!file_exists($path)){
				$this->StackError("Does not exists file. ($path)");
				return false;
			}
			
			if(!include_once($path) ){
				$this->StackError("Does not include file. ($path)");
				return false;
			}
			
			//	Instance
			$this->_pdo = new PDO5();
		}
		
		return $this->_pdo;
	}
	
	/**
	 * @var MySQL
	 */
	private $mysql;
	
	/**
	 * Return MySQL Object
	 * 
	 * @param  array|string configuration-array or configuration-file-path
	 * @return MySQL $mysql
	 */
	function MySQL($args=null)
	{
		if( empty($this->mysql) ){
			include_once('SQL/MySQL.class.php');
			$this->mysql = new MySQL( $args, $this );
		}
		return $this->mysql;
	}
	
	/**
	 * So-called, factory of singleton.
	 * 
	 * I recently learned that this is called a singleton in general.
	 * Anyone come up with this, It's not special technique.
	 * 
	 * @param  string $name
	 * @return OnePiece5
	 */
	static function Singleton($name)
	{
		if( isset( $_SERVER[__CLASS__]['singleton'][$name] ) ){
			return $_SERVER[__CLASS__]['singleton'][$name];
		}
		
		if( $_SERVER[__CLASS__]['singleton'][$name] = new $name() ){
			return $_SERVER[__CLASS__]['singleton'][$name];
		}
		
		return new OnePiece5();
	}
	
	/**
	 * Abstract Form object.
	 * 
	 * @return Form5
	 */
	function Form()
	{
		return self::Singleton('Form5');
	}
	
	/**
	 * i18n is translate object.
	 * 
	 * @return i18n
	 */
	static function i18n()
	{
		return self::Singleton('i18n');
	}
	
	/**
	 * Cache is presents the memcached interface.
	 *
	 * @return Cache
	 */
	function Cache()
	{
		return $this->Singleton('Cache');
	}
	
	/**
	 * Wizard
	 * 
	 * @return Wizard
	 */
	function Wizard()
	{
		return $this->Singleton('Wizard');
	}
	
	/**
	 * Doctor
	 * 
	 * @return Doctor
	 */
	function Doctor()
	{
		return $this->Singleton('Doctor');
	}
	
	/**
	 * Wiki2
	 * 
	 * @param  string $string
	 * @param  array  $options
	 * @return string
	 */
	static function Wiki2( $string, $options=null )
	{
		//  Check
		if(is_null($string)){
			return '';
		}else if(is_numeric($string)){
			return $string;
		}else if(!is_string($string)){
			self::mark( 'Does not string - '.self::GetCallerLine() );
			self::StackError("Does not string.");
		}
		
		if( class_exists('Wiki2Engine',true) ){
			return nl2br(trim(Wiki2Engine::Wiki2( $string, $options ))) . PHP_EOL;
		}else{
			return nl2br( self::Escape(trim($string)) ) . PHP_EOL;
		}
	}
	
	/**
	 * EMail
	 * 
	 * @see http://onepiece-framework.com/reference/email
	 * @return EMail
	 */
	function EMail()
	{
		return $this->Singleton('EMail');
	}
}
