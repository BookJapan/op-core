<?php /* vim: ts=4:sw=4:tw=80 */
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

//	OP_ROOT
$op_root = $_SERVER['OP_ROOT'] = dirname(__FILE__).'/';

//	APP_ROOT
$app_root = $_SERVER['APP_ROOT'] = dirname($_SERVER['SCRIPT_FILENAME']).'/';

//	DOC_ROOT
$doc_root = $_SERVER['DOC_ROOT'] = $_SERVER['DOCUMENT_ROOT'].'/';

//	Register autoloader.
include('Autoloader.class.php');
spl_autoload_register('Autoloader::Autoload',true,true);

//	Init Error
Error::Init();

//	Init Env
Env::Init();

/**
 * @see http://jp.php.net/manual/ja/function.register-shutdown-function.php
 */
if(!function_exists('OnePieceShutdown')){
	function OnePieceShutdown()
	{
		//	Error
		Error::Report();
		
		//	Check
		if(!OnePiece5::Admin()){
			return;
		}
		
		/**
		 * @see http://jp.php.net/manual/ja/features.connection-handling.php
		 */
		$aborted = connection_aborted();
		$status  = connection_status();
		
		/* @see http://www.php.net/manual/ja/errorfunc.constants.php */
		if( function_exists('error_get_last') and $error = error_get_last()){
			Error::LastError($error);
		}
		
		// Session reset
		if( Toolbox::isLocalhost() and Toolbox::isHtml() ){
			$rand = rand( 0, 1000);
			if( 1 == $rand ){
				$_SESSION = array();
				$message = OnePiece5::i18n()->Bulk('\OnePiece5\ did initialize the \SESSION\.');
				print "<script>alert('$message');</script>";
			}
		}
		
		//	mime
		switch( $mime = Toolbox::GetMIME(true) ){
			case 'plain':
				if( Env::Get('cli') ){
					print PHP_EOL . ' -- OnePiece is shutdown -- ' . PHP_EOL;
				}
				break;
				
			case 'css':
				print PHP_EOL . '/* OnePiece is shutdown. */' . PHP_EOL;
				break;
				
			case 'javascript':
				break;
				
			case 'json':
				break;

			case 'csv':
				break;
				
			case 'html':
			default:
				Developer::PrintStyleSheet();
				Developer::PrintGetFlagList();
				print PHP_EOL.'<OnePiece mime="'.$mime.'"/>'.PHP_EOL;
				break;
		}
	}
	register_shutdown_function('OnePieceShutdown');
}

if(!function_exists('OnePieceErrorHandler')){
	function OnePieceErrorHandler( $no, $str, $file, $line, $context)
	{
		static $oproot;
		if(empty($oproot)){
			$oproot  = dirname(__FILE__) . '/';
		}
		$env = isset($_SERVER['OnePiece5']['env']) ? $_SERVER['OnePiece5']['env']: array();
		
		/* @see http://www.php.net/manual/ja/errorfunc.constants.php */
		switch($no){
			case E_WARNING: // 2
				$er = 'E_WARNING';
				break;
			case E_NOTICE:  // 8
				$er = 'E_NOTICE';
				break;
			case E_STRICT:  // 2048
				$er = 'E_STRICT';
				break;
			case E_USER_NOTICE: // 1024
				$er = 'E_USER_NOTICE';
				break;
			default:
				$er = 'ERROR: '.$no;
		}
		
		//  Output error message.
		$format = '%s [%s] %s: %s';
		if(empty($env['cgi'])){
			$format = '<div>'.$format.'</div>';
		}
		
		//  check ini setting
		if( ini_get( 'display_errors') ){
			printf( $format.PHP_EOL, $file, $line, $er, $str );
		}
		
		return true;
	}
	
	if( isset($_SERVER['HTTP_HOST']) ){
		$level = $_SERVER['HTTP_HOST'] === 'localhost' ? E_ALL | E_STRICT: error_reporting();
		set_error_handler('OnePieceErrorHandler',$level);
	}else{
		//	Pacifista
	}
}

if(!function_exists('OnePieceExceptionHandler')){
	function OnePieceExceptionHandler($e)
	{
		//  TODO: 
		print "<h1>Catch the Exception.</h1>";
		
		$op = new OnePiece5();
		$op->StackError( $e->getMessage() );
		printf('<div style="background-color:black; color:white;">[%s] %s<br/>%s : %s</div>', get_class($e), $e->GetMessage(), $e->GetFile(), $e->GetLine() );
	}
	set_exception_handler('OnePieceExceptionHandler');
}

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
		
		$this->PrintError();
	}
	
	/**
	 * 
	 * @param string $name
	 * @param array  $args
	 */
	function __call( $name, $args )
	{
		//  Toolbox
		//call_user_func_array(array($this->toolbox, $name), $args);
		
		//  If Toolbox method.
		if( method_exists('Toolbox', $name) and false ){
			return Toolbox::$name(
				isset($args[0]) ? $args[0]: null,
				isset($args[1]) ? $args[1]: null,
				isset($args[2]) ? $args[2]: null,
				isset($args[3]) ? $args[3]: null,
				isset($args[4]) ? $args[4]: null,
				isset($args[5]) ? $args[5]: null
			);
		}
		
		//  error reporting
		$join = array();
		foreach( $args as $temp ){
			switch(gettype($temp)){
				case 'string':
					$join[] = '"'.$temp.'"';
					break;
					
				case 'array':
					$join[] = var_export($temp,true);
					break;
					
				default:
					$join[] = gettype($temp);
					break;
			}
		}
		$argument = join(', ',$join);
		$message = sprintf('Does not exists this function: %s(%s)', $name, $argument );
		self::StackError($message);
	}
	
	static function __callStatic( $name , $arguments )
	{
		if( isset($this) ){
			//  PHP 5.3.0 later
			$func    = __FUNCTION__;
			$caller  = $this->GetCallerLine();
			$message = "MAGIC METHOD ($func): $name, ".serialize($arguments);
			
			$this->StackError( __FUNCTION__ );
			$this->mark(PHP_VERSION);
		}else{
			OnePiece5::StackError(__FUNCTION__);
			/*
			OnePiece5::Mark("$name");
			if(!empty($arguments)){
				OnePiece5::D($arguments);
			}
			*/
		}
	}
	
	function __set( $name, $value )
	{
//		$value   = is_string($value) or is_numeric($value) ? $value: serialize($value);
		$func    = __FUNCTION__;
		$caller  = $this->GetCallerLine();
		$message = "MAGIC METHOD ($func): `$name` is not accessible property. (call=$caller, value=$value)";
		
		$this->StackError($message);
		$this->mark("![.red .bold[$message]]");
	}
	
	function __get( $name )
	{
		$func    = __FUNCTION__;
		$caller  = $this->GetCallerLine();
		$message = "MAGIC METHOD ($func): `$name` is not accessible property. (call=$caller)";
		
		$this->StackError($message);
		$this->mark("![.red .bold[$message]]");
	}
	
	function __isset( $name )
	{
		$func    = __FUNCTION__;
		$caller  = $this->GetCallerLine();
		$message = "MAGIC METHOD ($func): `$name` is not accessible property. (call=$caller)";
		
		$this->StackError($message);
		$this->mark("![.red .bold[$message]]");
	}
	
	function __unset( $name )
	{
		$func    = __FUNCTION__;
		$caller  = $this->GetCallerLine();
		$message = "MAGIC METHOD ($func): `$name` is not accessible property. (call=$caller)";
		
		$this->StackError($message);
		$this->mark("![.red .bold[$message]]");
	}
	
	/*
	function __sleep()
	{
		serialize() called __sleep method.
	}
	
	function __wakeup()
	{
		
	}
	*/
	
	function __toString()
	{
		$this->mark('![.red .bold[ CATCH MAGIC METHOD ]]');
	}
	
	function __invoke()
	{
		$this->mark('![.red .bold[ CATCH MAGIC METHOD ]]');
	}
	
	function __set_state()
	{
		$this->mark('![.red .bold[ CATCH MAGIC METHOD ]]');
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
		if( isset($_SERVER['OP_IS_ADMIN']) ){
			if(!is_null($_SERVER['OP_IS_ADMIN'])){
				return  $_SERVER['OP_IS_ADMIN'];
			}
		}
		
		$server_addr = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR']: '127.0.0.1';
		$remote_addr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR']: null;
		$remote_addr = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR']: $remote_addr;
		
		//	
		if( $server_addr === $remote_addr ){
			$io = true;
		}else{
			$io = Env::Get(Env::_ADMIN_IP_ADDR_) === $remote_addr ? true: false;
		}
		
		//	
		$_SERVER['OP_IS_ADMIN'] = $io;
		
		//	
		return $io;
	}
	
	/**
	 * Error stacking
	 * 
	 * @param string $message is message.
	 * @param string $translation is language code (En, Ja, Fr).
	 * @param string $class is label use to print.
	 */
	static function StackError( $args, $translation=null )
	{
		Error::Set( $args, $translation );
	}
	
	function FetchError()
	{
		if( isset($_SERVER['OnePiece5']['errors']) ){
			return array_shift($_SERVER['OnePiece5']['errors']);
		}else{
			return null;
		}
	}
	
	/**
	 * Error print.
	 */
	function PrintError()
	{
		// init
		$nl		 = "\r\n";//self::GetEnv('nl');
		$cli	 = self::GetEnv('cli');
		$mime	 = self::GetEnv('mime');
		$charset = self::GetEnv('charset');
		$class	 = 'OnePiece5';
		
		//	Check CLI mode
		switch( strtolower($mime) ){
			case 'text/css':
				$cli = true;
				break;
		//	default:
		//		$cli = false;
		}
		
		if(isset($_SERVER[$class]['errors'])){
			$errors = $_SERVER[$class]['errors'];
			unset($_SERVER[$class]['errors']);
		}else{
			return true;
		}
		
		//	Require OnePiece Style-Sheet. (print from Toolbox.class.php)
		$this->SetEnv('isRequireStyleSheet',true);
		
		// stack trace show/hide script  
		$javascript = <<< __EOL__
		<script>
		function op_error_trace( t, id ){
			if( t.checked ){
				document.getElementById(id).style.display = 'none';
			}else{
				document.getElementById(id).style.display = 'block';
			}
		}
		</script>
__EOL__;

		// stack trace style
		$style = 'display:none;';

		// create print
		$print  = '';
		$print .= "<div class=\"$class small \">";
		
		//  wiki2 options
		$o = array('tag'=>true);
		
		for( $i=0, $count = count($errors); $i<$count; $i++ ){
			
			$error = $errors[$i];
			$incident = $error['incident'];
			$message  = $error['message'];
			$trace    = $error['trace'];
			
			// stack trace show/hide control input
			$trace_id  = 'op-trace-div-'.$i;
			$input_id  = 'op-trace-input-'.$i;
			$input = sprintf('<input type="checkbox" id="%s" checked="checked" onChange="op_error_trace( this, \'%s\')" />', $input_id, $trace_id);

			$print .= sprintf('<div class="red">%s <label for="%s">%s - %s</label></div>'.$nl, $input, $input_id, self::wiki2($incident,$o), self::wiki2($message,$o));
			$print .= sprintf('<div class="trace" style="%s" id="%s">%s</div>'.$nl, $style, $trace_id, self::wiki2($trace,$o));
		}
		$print .= '</div>';
		
		// Finish
		//if( /*$cli*/ /*self::GetEnv('Pacifista')*/ ){
		//	print strip_tags( html_entity_decode( $print, ENT_QUOTES, $charset ) );
		//}else
		 
		if( self::Admin() and Toolbox::isHtml() ){

			//	Notify
			if( Toolbox::isHtml() ){
				print $javascript . $nl;
				print $print;
			}else{
				//	Not HTML
				print html_entity_decode(strip_tags( $print, null ),ENT_QUOTES,$charset);
			}
			
		}else{
			
			//	Notify at email.
			$ua   = isset($_SERVER['HTTP_USER_AGENT'])	 ? $_SERVER['HTTP_USER_AGENT']: null;
			$ip   = isset($_SERVER['REMOTE_ADDR'])		 ? $_SERVER['REMOTE_ADDR']: 	null;
			$href = isset($_SERVER['HTTP_REFERER'])		 ? $_SERVER['HTTP_REFERER']: 	null;
			$host = $ip ? gethostbyaddr($ip): null;
			$date = date('Y-m-d H:i:s');
			$url  = Toolbox::GetURL();
			
			//  The same mail is not delivered repeatedly.
			$key = 'mail-notify-' . md5($errors[0]['trace']);
			if( $num = $this->GetSession($key) ){
				$this->SetSession($key, $num +1 );
				return;
			}else{
				$this->SetSession($key, 1 );
			}
			
			//  Subject
			$incident = strip_tags($this->wiki2($errors[0]['incident']));
			$incident = str_replace('&gt;',   '>', $incident);
			$incident = str_replace('&quot;', '' , $incident);
			$subject  = sprintf('[%s] PrintError: %s', __CLASS__, $incident );
			
			//  page
			$page_info  = "Page Info: $nl";
			$page_info .= "  Date: $date" . $nl;
			$page_info .= "  URL: $url" . $nl;
			$page_info .= "  REFERER: $href" . $nl . $nl;
			
			//  user
			$user_info  = "User Info: $nl";
			$user_info .= "  IP: $host($ip)" . $nl;
			$user_info .= "  UA: $ua" . $nl . $nl;
			
			//  message
			$message = $page_info . $user_info . "Errors: $nl ". strip_tags($print);
			//$message = str_replace( array('%20'), array(' '), $message);
			$message = str_replace( array('%20','%3C','%3E'), array(' ','<','>'), $message);
			
			$mail['to'] = $this->GetEnv('Admin-mail');
			$mail['subject'] = $subject;
			$mail['message'] = html_entity_decode( $message, ENT_QUOTES, 'utf-8');
			self::Mail($mail);
		}
		
		return true;
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
	
	private function _InitSession()
	{
		/**
		 * @see http://www.glamenv-septzen.net/view/29
		 */
		session_cache_limiter('private_no_expire');

		//  separate session.
		$this->session = &$_SESSION[__CLASS__][get_class($this)];
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
		
		if( headers_sent() ){
			OnePiece5::StackError("already header sent.");
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
					$format = '$file [$line] ';
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
	 * 
	 * @param  string $file_path
	 * @return string $file_path
	 */
	static function CompressPath( $path )
	{
		// TODO: file system encoding. (Does not support multi language, yet)
		$encode_file_system = PHP_OS === 'WINNT' ? 'sjis-win': 'utf-8';
		if( PHP_OS == 'WINNT' ){
			$path = str_replace( '\\', '/', $path );
		}
		
		$op_root	 = self::GetEnv('op_root');
		$app_root	 = self::GetEnv('app_root');
		$doc_root	 = self::GetEnv('doc_root');
	//	$ctrl_root	 = self::GetEnv('ctrl_root');
		
		//  remove slash (easy-to-read)
		$op_root	 = $op_root   ? rtrim($op_root,  '/') : ' ';
		$app_root	 = $app_root  ? rtrim($app_root, '/') : ' ';
		$doc_root	 = $doc_root  ? rtrim($doc_root, '/') : ' ';
	//	$ctrl_root	 = $ctrl_root ? rtrim($ctrl_root,'/') : ' ';
		
		$patt = array();
		$patt[] = "|^".preg_quote($app_root)."|";
		$patt[] = "|^".preg_quote($doc_root)."|";
		$patt[] = "|^".preg_quote($op_root)."|";
		$repl = array('App:','Doc:','OP:');
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
		$memory_usage = memory_get_usage(true) /1000 /1000;
		if( strpos($memory_usage,'.') ){
			list( $mem_int, $mem_dec ) = explode( '.', $memory_usage );
		}else{
			$mem_int = $memory_usage;
			$mem_dec = 0;
		}
		$memory = sprintf('(%s.%s MB)', number_format($mem_int), $mem_dec );
		
		//	call line
		$call_line = self::GetCallerLIne(0,1,'mark');
		
		//	message
		if( is_int($str) ){
			var_dump((string)$str);
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
		$string = "{$nl}<div class=\"OnePiece mark\">{$call_line}- {$str} <span class=\"OnePiece mark memory\">{$memory}</span></div>{$nl}";
				
		//	Case of plain text.
		if(!Toolbox::isHtml()){
			$string = strip_tags($string);
			if( Toolbox::GetMIME() === 'text/css' ){
				$string = "/* ". trim($string) ." */$nl";
			}
		}
		
		print $string;
	}
	
	/**
	 * 
	 * @param string $str
	 * @param string $tag
	 * @param array  $attr
	 * @return string
	 */
	static function Html($str, $tag='span', $attr=null)
	{
		$nl    = self::GetEnv('newline');
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
			$class = sprintf('class="%s"', $temp);
		}
		
		if(isset($attr['style'])){
			foreach($attr['style'] as $key => $var){
				$styles[] = "$key:$var;";
			}
			$style = sprintf('style="%s"', implode(' ', $styles));
		}
		
		return sprintf($nl.'<%s %s %s>%s</%s>'.$nl, $tag, $class, $style, $str, $tag );
	}
	
	/**
	 * 
	 * @param string $str
	 * @param string $tag
	 * @param array $attr
	 */
	static function P( $str='OnePiece!', $tag='p', $attr=null)
	{
		if( Toolbox::isHtml() ){
			print self::Html( $str, $tag, $attr );
		}else{
			print trim(strip_tags(self::Html( $str, $tag, $attr )));
		}
		print PHP_EOL;
	}
	
	/**
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
		
		//	CLI
		if( self::GetEnv('cli') ){
			self::p($line);
			var_dump($args);
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
	
	static function Decode( $args, $charset=null)
	{
		//  Accelerate
		static $charset = null;
		if(!$charset){
			$charset = self::GetEnv('charset');
		}
		
		switch($type = gettype($args)){
			
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
				$args = html_entity_decode( $args, ENT_QUOTES, $charset );
				break;
		}
		
		return $args;
	}
	
	/**
	 * 
	 * @param string|array $args
	 * @param string $charset
	 */
	static function Escape( $args, $charset='utf-8' )
	{
		switch($type = gettype($args)){
			case 'null':
			case 'NULL':
			case 'integer':
			case 'boolean':
			case 'double':
				break;
			case 'string':
				$args = self::EscapeString($args,$charset);
				break;
			case 'array':
				$args = self::EscapeArray($args,$charset);
				break;
			case 'object':
				$args = self::EscapeObject($args,$charset);
				break;
			default:
				self::p("[".__METHOD__."] undefined type($type)");
		}
		
		return $args;
	}
	
	/**
	 * 
	 * @param string $args
	 * @param string $charset
	 */
	static function EscapeString( &$args, $charset='utf-8' )
	{
		//  Anti null byte attack
		$args = str_replace("\0", '\0', $args);
		
		//  Anti ASCII Control code.
		if( Toolbox::isLocalhost() ){
			$args = trim( $args, "\x00..\x1F");
		}
		
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
	 * 
	 * @param array  $args
	 * @param string $charset
	 */
	static function EscapeArray( &$args, $charset='utf-8' )
	{
		$temp = array();
		foreach ( $args as $key => $var ){
			$key = self::Escape( $key, $charset );
			$var = self::Escape( $var, $charset );
			$temp[$key] = $var;
		}
		return $temp;
	}
	
	/**
	 * 
	 * @param array  $args
	 * @param string $charset
	 */
	static function EscapeObject( &$args, $charset='utf-8' )
	{
		$temp = new Config();
		foreach ( $args as $key => $var ){
			$key = self::EscapeString( $key, $charset );
			$var = self::Escape( $var, $charset );
			$temp->$key = $var;
		}
		return $temp;
	}
	
	function SetHeader( $str, $replace=null, $code=null )
	{
	//	$cgi = $this->GetEnv('cgi'); // FastCGI
		
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
	 * 
	 * @param string $label
	 */
	function time($label=null){
		static $offset = null;
		
		if( true ){
			list($usec, $sec) = explode(" ", microtime());
			
			$sec = (int)$sec;
			
			if(!$offset){
				$offset  = ((float)$usec + (float)$sec);
				$elapsed = 0.0;
				$lap     = 0.0;
			}else{
				$count   = count($this->laptime)-1;
				$elapsed = ((float)$usec + (float)$sec) - $offset;
				$lap     = $elapsed - $this->laptime[$count]['elapsed'];
			}
		}else{
			/**
			 * I don't know. Mac OS X microtime is bugg???
			 */
			if(!$offset){
				$offset  = microtime(true); // Windows and Linux is OK.
				$elapsed = 0.0;
				$lap     = 0.0;
			}else{
				$count   = count($this->laptime) -1;
				$elapsed = microtime(true) - $offset;
				$lap     = $elapsed - $this->laptime[$count]['elapsed'];
			}
		}
		
		$this->laptime[] = array(
				'label'   => $label,
				'lap'     => $lap,
				'elapsed' => $elapsed
				);
		
		return "$elapsed ($lap)";
	}
	
	/*
	function PrintTime(){
		if($this->GetEnv('cli')){ return; }
		if(!$this->laptime){ return; }
		if( $this->laptime ){
			self::d($this->laptime);
		}
	}
	*/
	
	/**
	 * Send mail method
	 * 
	 * @param  array|Config $config Mail config. (Config is convert to array)
	 * @return boolean
	 */
	function Mail( $args )
	{
		// optimize
	//	$lang = $this->GetEnv('lang');
		$lang = 'uni'; // Unicode only
		$char = $this->GetEnv('charset');
		
		if( $lang and $char ){
			// Save original.
			$save_lang = mb_language();
			$save_char = mb_internal_encoding();
			
			// Set language use mail function.
			mb_language($lang);
			mb_internal_encoding($char);
		}
		
		//  Convert object to array.
		if( is_object($args) ){
			$args = Toolbox::toArray($args);
		}
		
		//  init
		$headers = array();
		$mime	 = isset($args['mime'])    ? $args['mime']    : 'text/plain';
		$from	 = isset($args['from'])    ? $args['from']    : self::GetEnv('admin-mail');
		$to      = isset($args['to'])      ? $args['to']      : null;
		$title   = isset($args['title'])   ? $args['title']   : 'No subject';
		$subject = isset($args['subject']) ? $args['subject'] : $title;
		$body    = isset($args['body'])    ? $args['body']    : null;
		$message = isset($args['message']) ? $args['message'] : $body;
		
		//  Sender name
		$from_name = isset($args['from_name']) ? $args['from_name'] : null;
		$to_name   = isset($args['to_name'])   ? $args['to_name']   : null;

		//  Check
		if( empty($from) or empty($to) or empty($message) ){
			$this->StackError("Empty! from=$from, to=$to, message=$message");
			return false;
		}
		
		//  Subject
		$subject = mb_encode_mimeheader($subject);
		
		//  To
		if( $to_name ){
			$to_name = mb_encode_mimeheader($from_name);
			$headers[]  = "To: $to_name <$to>";
		}
		
		//  From
	//	if( is_string($from) ){
			if( $from_name ){
				$from_name = mb_encode_mimeheader($from_name);
				$headers[]  = "From: $from_name <$from>";
			}else{
				$headers[]  = "From: $from";
			}
	//	}
		
		// Cc
		if( isset($args['cc']) ){
			if( is_string($args['cc']) ){
				$headers[] = "Cc: " . $args['cc'];
			}else if(is_array($args['cc'])){
				$this->mark('Does not implements yet.');
			}
		}
		
		// Bcc
		if( isset($args['bcc']) ){
			if( is_string($args['bcc']) ){
				$headers[]  = "Bcc: " . $args['bcc'];
			}else if( is_array($args['bcc']) ){
				$this->mark('Does not implements yet.');
			}
		}
		
		// X-Mailer
		if( $this->admin() ){
			$headers[] = "X-Mailer: OnePiece-Framework";
		}
		
		//	encording format
		if( $char ){
			$headers[] = "Content-Type: $mime; charset=$char";
		}else{
			//	$headers[] = "Content-Transfer-Encoding: base64";
		}
		
		$add_header = implode("\n", $headers);
		$add_params = null;
		
		// SMTP server response: 503 5.0.0 Need RCPT (recipient) 
		$add_params = '-f '.$from;
	
		// @todo: I should support multi-byte-language
		if(!$io = mail($to, $subject, $message, $add_header, $add_params ) ){
			$this->mark("![.red[send mail is failed.]]");
		}
		
		// recovery
		if( $save_lang and $save_char ){
			mb_language($save_lang);
			mb_internal_encoding($save_char);
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
			$this->StackError("The template file does not exist.(file=$file, path=$path)");
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
	 * @param string $path
	 * @return string
	 */
	static function ConvertURL( $meta, $domain=false )
	{
		if( $domain ){
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
	
	function _setDeveloper( $name, $ip )
	{
		$developer = self::GetEnv('developer');
		$developer[$name] = $ip;
		self::SetEnv('developer',$developer);
	}
	
	function _isDeveloper()
	{
		$developer = self::GetEnv('developer');
		if(!$developer){
			$developer = array();
		}
		return in_array( $_SERVER['REMOTE_ADDR'], $developer);
	}
	
	/**
	 * 
	 * @param  string $name
	 * @throws OpModelException
	 * @throws OpException
	 * @throws OpWzException
	 * @return Model_Model
	 */
	static function Model($name)
	{
		try{
			//  name check
			if(!$name){
				$msg = "Model name is empty.";
				throw new OpException($msg);
			}
			
			//	hogeHoge -> HogeHoge	//	hogeHoge -> Hogehoge
			$name = ucfirst($name);		//	$name = ucfirst(strtolower($name));
			
			//  Notice
			if( strpos( $name, '_') !== false ){
				$message = 'Underscore(_) is reserved. For the feature functions. (maybe, namespace)';
				$english = self::i18n()->En($message,'En');
				$translate = self::i18n()->En($message);
				$this->mark("$translate ($english)");
			}
			
			//  already instanced?
			if( isset( $_SERVER[__CLASS__]['model'][$name] ) ){
			//	$this->mark("Singleton!! ($name)");
				return $_SERVER[__CLASS__]['model'][$name];
			}
			
			//  include Model_model
			if(!class_exists( 'Model_Model', false ) ){
				$path = self::ConvertPath('op:/Model/Model.model.php');
				if(!$io = include_once($path)){
					$msg = "Failed to include the Model_model. ($path)";
					throw new OpException($msg);
				}
			}
			
			//  include from app's model dir
			$model_dir = self::GetEnv('model-dir');
			$path  = self::ConvertPath("{$model_dir}{$name}.model.php");
			if( $io = file_exists($path) ){
				$io = include_once($path);
			}
			
			//  include from op-core's model dir
			if(!$io){
				$path = self::ConvertPath("op:/Model/{$name}.model.php");
				if( $io = file_exists($path) ){
					$io = include_once($path);
				}
			}
			
			//  include check 
			if(!$io){
				$msg = "Failed to include the $name. ($path)";
				throw new OpException($msg);
			}
			
			//  instance of model
			$model_name = 'Model_'.$name;//.'_model';
			if(!$_SERVER[__CLASS__]['model'][$name] = new $model_name ){
				$msg = "Failed to include the $model_name. ($path)";
				throw new OpException($msg);
			}
			
			//  Instance is success.
			return $_SERVER[__CLASS__]['model'][$name];
			
		}catch( Exception $e ){
			$file = $e->getFile();
			$line = $e->getLine();
			self::StackError( $e->getMessage() . "($file, $line)" );
			return new OnePiece5();
		}
	}
	
	function Module($name)
	{
		try{
			//  name check
			if(!$name){
				$msg = "Module name is empty.";
				throw new OpModelException($msg);
			}
				
			//  already instanced?
			if( isset( $_SERVER[__CLASS__]['module'][$name] ) ){
				return $_SERVER[__CLASS__]['module'][$name];
			}
				
			//  include Model_model
			if(!class_exists( 'Model_Model', false ) ){
				$path = self::ConvertPath('op:/Model/Model.model.php');
				if(!$io = include_once($path)){
					$msg = "Failed to include the Model_model. ($path)";
					throw new OpException($msg);
				}
			}
			
			//  op-core
			$path = self::ConvertPath("op:/Module/{$name}/{$name}.module.php");
			if( $io = file_exists($path) ){
				$io = include_once($path);
			}
				
			//  user-dir
			if(!$io ){
				if( $module_dir = $this->GetEnv('module-dir') ){
					$module_dir = rtrim( $module_dir, '/' );
					$path  = self::ConvertPath("{$module_dir}/{$name}/{$name}.module.php");
					if( $io = file_exists($path) ){						
						$io = include_once($path);
					}else{
						$msg = "Does not include $path.";
						throw new OpModelException($msg);
					}
				}else{
				//	$this->d($module_dir);
					$msg = "Does not set module-dir.";
					throw new OpModelException($msg);
				}
			}
				
			//  Could be include?
			if(!$io){
				$msg = "$name class does not been included.($path)";
				throw new OpModelException($msg);
			}
			
			//  Create module name.
			$module_name = 'Module_'.$name;

			//  instance of module
			if(!$_SERVER[__CLASS__]['module'][$name] = new $module_name() ){
				$msg = "Failed to include the $module_name. ($path)";
				throw new OpModelException($msg);
			}
			
			//  Instance is success.
			return $_SERVER[__CLASS__]['module'][$name];
			
		}catch( Exception $e ){
			$this->mark( $e->getMessage() );
			$this->StackError( $e->getMessage() );
			return new OnePiece5();
		}
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
			return nl2br(trim($string)) . PHP_EOL;
		}
	}
	
	/**
	 * Checked dead or alive.
	 * 
	 * @param boolen $args
	 */
	function Vivre( $register )
	{
		$line = OnePiece5::GetCallerLine();
		OnePiece5::Mark("This method is deprecated. ($line)",'vivre');
		return true;
		
		if( $register ){
			//	register
			if($this->GetEnv('vivre')){
				//	Double registration.
				$this->mark("Vivre check is double booking");
			}else if( isset($_SESSION[__CLASS__]['vivre']) ){
				//	unset されていないということは途中でエラーになっている。
				if( $this->admin() ){
					$this->mark("VIVRE!!");
				}else{
					
					$host  = $_SERVER['HTTP_HOST']; // SERVER_NAME, SERVER_ADDR
					$addr  = $_SERVER['SERVER_ADDR'];
					$xhost = isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST']: $host; // HTTP_X_FORWARDED_SERVER
					$uri   = $_SERVER['REQUEST_URI'];
					
					$ip = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR']: $_SERVER['REMOTE_ADDR'];
					$domain = gethostbyaddr($ip);
					
					$ua = $_SERVER['HTTP_USER_AGENT'];
					
					$args = array();
					$args['to']		 = $this->GetEnv('admin-mail');
					$args['subject'] = '[OnePiece] VIVRE ALERT';
					$args['body']	.= "HOST = $host ($addr) \n";
					$args['body']	.= "REQUEST_URI = {$xhost}{$uri} \n";
					$args['body']	.= "VISITOR = $ip($domain) \n";
					$args['body']	.= "USER AGENT = $ua \n";
					$this->Mail($args);
				}
			}else{
				$_SESSION[__CLASS__]['vivre'] = 1;
			}
			
			//	Anti double registration.
			$this->SetEnv('vivre',1);
		}else{
			//  release
			unset($_SESSION[__CLASS__]['vivre']);
		}
	}
}

class OpException extends Exception
{
	private $_wizard = null;
	private $_isSelftest = null;
	
	function isSelftest($var=null)
	{
		if( $var ){
			$this->_isSelftest = $var;
		}
		return $this->_isSelftest;
	}
	
	function SetWizard()
	{
		OnePiece5::SetEnv('wizard',true);
	}
	
	function GetWizard()
	{
		return OnePiece5::GetEnv('wizard');
	}
}

/**
 * Env
 * 
 * Creation: 2014-01-22
 * Release:  2014-09-17
 */
class Env
{
	const _NAME_SPACE_		 = 'ONEPIECE_5_ENV';
	
	const _ADMIN_IP_ADDR_	 = 'ADMIN_IP';
	const _ADMIN_EMAIL_ADDR_ = 'ADMIN_MAIL';
	
	const _ROOT_OP_		 = 'OP_ROOT';
	const _ROOT_APP_	 = 'APP_ROOT';
	const _ROOT_DOC_	 = 'DOCUMENT_ROOT';
	
	const _SERVER_IS_LOCALHOST_	 = 'OP_IS_LOCALHOST';
	const _SERVER_IS_ADMIN_		 = 'OP_IS_ADMIN';
	
	static private function _Convert( $key, $var=null )
	{
		switch($key){
			case 'NL':
				$key = 'NEW_LINE';
				break;
				
			case 'HREF':
				$key = 'HTTP_REFERER';
				break;
				
			case 'LANG':
				$key = 'LANGUAGE';
				break;
				
			case 'FQDN':
			case 'DOMAIN':
				$key = 'HTTP_HOST';
				break;
				
			default:
				if( preg_match('/(ROOT|DIR)$/',$key) ){
					$is_path = true;
				}
				
				if( $key = preg_replace('/-/', '_', $key) ){
					//	throw
				}
		}
			
		//	$var is a case of path.
		if( $var and !empty($is_path) ){
			
			/*  Convert to unix path separator from Windows path separator.
			 * 
			 * Example
			 * C:¥www¥htdocs¥ -> C:/www/htdocs/
			 */
			$var = str_replace('\\', '/', $var);
			
			/* Add a slash at the end of the path.
			 * 
			 * Example
			 * /var/www/html -> /var/www/html/
			 */
			$var = rtrim( $var, '/').'/';
		}
		
		return array( $key, $var );
	}
	
	static function Init()
	{
		self::Set('mime','text/html');
		self::Set('charset','utf-8');
		
	//	self::_init_include_path();
		self::_init_cli();
		self::_init_admin();
		self::_init_session();
		self::_init_locale();
	}
	
	/*
	private static function _init_include_path()
	{
		//	init op_root
		$op_root = $_SERVER['OP_ROOT'];
		
		//	Added "op-root" to include_path.
		$include_path = ini_get('include_path');
		
		if(!strpos( $include_path, PATH_SEPARATOR.$op_root ) ){
			$include_path = rtrim( $include_path, PATH_SEPARATOR );
			$include_path .= PATH_SEPARATOR . $op_root;
			ini_set('include_path',$include_path);
		}
	}
	*/
	
	private static function _init_cli()
	{
		//	Check if CLI.
		if( isset($_SERVER['SHELL']) ){
			Env::Set('cli',true);
			Env::Set('mime','text/plain');
		}
		
		//	Check if admin.
		if( isset($_SERVER['PS1']) ){
			$_SERVER[self::_SERVER_IS_ADMIN_] = true;
		}
	}
	
	private static function _init_admin()
	{
		if( Env::Get('cli') ){
			return;
		}
		
		//	Check if localhost.
		$remote_addr = $_SERVER['REMOTE_ADDR'];
		if( $remote_addr === '127.0.0.1' or $remote_addr === '::1'){
			$is_localhost = true;
		}else{
			$is_localhost = false;
		}
		
		//	Set to $_SERVER
		$_SERVER[self::_SERVER_IS_LOCALHOST_]	 = $is_localhost;
		$_SERVER[self::_SERVER_IS_ADMIN_]		 = $is_localhost ? true: null;
	}
	
	private static function _init_session()
	{
		//  start to session.
		if(!session_id()){
			if( headers_sent($file,$line) ){
			//	if( Env::Get('mime') === 'text/html' ){
				$this->StackError("Header has already been sent. Check $file, line no. $line.");
			//	}
			}else{
				session_start();
			}
		}
	}

	/**
	 * locale setting.
	 *
	 * @see http://jp.php.net/manual/ja/class.locale.php
	 * @param string $locale ja_JP.UTF-8
	 */
	private static function _init_locale()
	{
		/**
		 * Windows
		 * 	Japanese_Japan.932 = sjis
		 * 	Japanese_Japan.20932 = euc-jp
		 *
		 * PostgreSQL for Windows
		 * 	Japanese_Japan.932 = utf-8 // auto convert
		 *
		 * http://lets.postgresql.jp/documents/technical/text-processing/2/
		 */
		
		//	Get last time locale.
		if(!$locale = OnePiece5::GetCookie('locale') ){
			$locale = 'ja_JP.utf-8';
		}
		
		//	Set locale
		Env::SetLocale($locale);
	}
	
	static function GetLocale()
	{
		return Env::Get('locale');
	}
	
	static function SetLocale( $locale )
	{
		//	Save to cookie.
		OnePiece5::SetCookie('locale', $locale);
		
		//	parse
		if( preg_match('|([a-z]+)[-_]([a-z]+)\.([-_a-z0-9]+)|i', $locale, $match) or true){
			$lang = strtolower($match[1]);
			$area = strtoupper($match[2]);
			$code = strtoupper($match[3]);
		}else{
			OnePiece5::StackError("Did not match locale format. ($locale, Ex. ja_JP.utf-8) ");
		}
		
		// Windows is unsupport utf-8
		/*
		if( PHP_OS == 'WINNT' and $lang == 'ja' ){
			// Shift_JIS
			setlocale( LC_ALL, 'Japanese_Japan.932');
		}else if(!setlocale( LC_ALL, $locale )){
			// @see http://jp.php.net/manual/ja/function.setlocale.php
			OnePiece5::StackError("Illigal locale: $locale");
			return false;
		}
		*/
		
		//	Set each value
		Env::Set('locale', $locale);
		Env::Set('lang',   $lang);
		Env::Set('area',   $area);
		Env::Set('charset',$code);
		
		//	Get locale relation value.
		list( $codes, $timezone ) = Env::GetLocaleValue();
		
		//	Set PHP's environment value
		mb_language($lang);
		mb_internal_encoding($code);
		mb_detect_order($codes);
		//mb_http_input();
		//mb_http_output()
		ini_set('date.timezone',$timezone);
	}
	
	static function GetLocaleValue()
	{
		$lang = Env::Get('lang');
		$area = Env::Get('area');
		
		//	detect order value
		if( $lang == 'ja'){
			$codes[] = 'eucjp-win';
			$codes[] = 'sjis-win';
			$codes[] = 'UTF-8';
			$codes[] = 'ASCII';
			$codes[] = 'JIS';
		}
		
		/**
		 * timezone list
		 * @see http://jp2.php.net/manual/ja/timezones.php
		 */
		switch( $area ){
			case 'JP':
				$timezone = 'Asia/Tokyo';
				break;
		}
		
		return array( $codes, $timezone );
	}
	
	static function GetAdminMailAddress()
	{
		if(!empty($_SERVER[self::_NAME_SPACE_][self::_ADMIN_EMAIL_ADDR_]) ){
			$mail_addr = $_SERVER[self::_NAME_SPACE_][self::_ADMIN_EMAIL_ADDR_];
		}else if(!empty($_SERVER['SERVER_ADMIN']) ){
			$mail_addr = $_SERVER['SERVER_ADMIN'];
		}else{
			$mail_addr = null;
		}
		return $mail_addr;
	}
	
	static function SetAdminMailAddress($mail_addr)
	{
		$_SERVER[self::_NAME_SPACE_][self::_ADMIN_EMAIL_ADDR_] = $mail_addr;
	}
	
	static function Get( $key )
	{
		//	Case
		$key = strtoupper($key);
		
		//	Convert
		list( $key, $var ) = self::_Convert( $key );
		
		//	Admin's E-Mail
		if( $key === self::_ADMIN_EMAIL_ADDR_ ){
			return self::GetAdminMailAddress();
		}
		
		//	
		if( isset($_SERVER[self::_NAME_SPACE_][$key]) ){
			$var = $_SERVER[self::_NAME_SPACE_][$key];
		}else if( isset($_SERVER[$key]) ){
			$var = $_SERVER[$key];
		}else{
			$var = null;
		}
		
		//	
		return $var;
	}
	
	static function Set( $key, $var )
	{
		//	Reset admin flag. 
		if( $key === 'admin-ip' ){
			$_SERVER['OP_IS_ADMIN'] = null;
		}
		
		//	Case
		$key = strtoupper($key);
		
		//	Convert
		list( $key, $var ) = self::_Convert( $key, $var );
		
		//	Admin's E-Mail
		if( $key === self::_ADMIN_EMAIL_ADDR_ ){
			self::SetAdminMailAddress($var);
			return;
		}
		
		//	Set
		$_SERVER[self::_NAME_SPACE_][$key] = $var;
	}
}

/**
 * Checked dead or alive.
 * 
 * @author tomoaki.nagahara@gmail.com
 */
class Vivre
{
	const _NAMESPACE_	 = Env::_NAME_SPACE_;
	const _KEY_NAME_	 = 'VIVRE';
	
	static function Handling()
	{
		//	Check session
		if(!session_id()){
			OnePiece5::Mark("This system not working php's session.");
		}
		
		//	Generate key
		$key = $_SERVER['REQUEST_URI'];
		$key = md5($key);
		
		if( isset($_SESSION[self::_NAMESPACE_][self::_KEY_NAME_][$key])){
			unset($_SESSION[self::_NAMESPACE_][self::_KEY_NAME_][$key]);
			self::Warning();
		}
		
		$_SESSION[self::_NAMESPACE_][self::_KEY_NAME_][$key] = true;
	}
	
	static function Relaese()
	{
		unset($_SESSION[self::_NAMESPACE_][self::_KEY_NAME_]);
	}
	
	static function Warning($message=null)
	{
		//	local info
		$nl		 = "\r\n";
		
		$name	 = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME']: null;
		$addr	 = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR']: null;
		
		$host	 = isset($_SERVER['HTTP_HOST'])   ? $_SERVER['HTTP_HOST']:   null;
		$port	 = isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT']: null;
		$xhost	 = isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST']: null; // HTTP_X_FORWARDED_SERVER
		$scheme	 = $port !== '443' ? 'http': 'https';
		
		$uri	 = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI']: null;
		$doc	 = isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT']: null;
		$app	 = isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME']: null;
		$gmdate	 = gmdate('Y-m-d H:i:s');
		$date	 = date('Y-m-d H:i:s');
		
		//	visitors info
		$ip		 = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR']: $_SERVER['REMOTE_ADDR'];
		$domain	 = gethostbyaddr($ip);
		$ua		 = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT']: null;
		$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER']: null;
		
		//	build mail info
		$to = Env::GetAdminMailAddress();
		$subject  = '[ONEPIECE] VIVRE ALERT';
		$message .= $message ? $nl.$nl: null;
		$message .= "Server: $name $nl";
		$message .= "Host: $host ($addr) $nl";
		$message .= "Request URI: {$scheme}://{$host}:{$port}{$uri} $nl";
		$message .= "Referer: $referer $nl";
		$message .= "$nl";
		$message .= "App: $app $nl";
		$message .= "Doc: $doc $nl";
		$message .= "Date: $date $nl";
		$message .= "GMT: $gmdate $nl";
		$message .= "$nl";
		$message .= "Visitor: $domain ($ip) $nl";
		$message .= "User Agent: $ua $nl";
		$message .= "OP_UNIQ_ID: ". OnePiece5::GetCookie(OnePiece5::KEY_COOKIE_UNIQ_ID);
		
		$add_header = null; //implode("\n", $headers);
		$add_params = null;
		
		// SMTP server response: 503 5.0.0 Need RCPT (recipient)
		$add_params = '-f '.$to;
		
		//	send mail
		$io = mail( $to, $subject, $message, $add_header, $add_params );
		$result = $io ? 'succsessful': 'failed';
		OnePiece5::Mark("Sendmail is $result by vivre.");
		if(!$io){
			$temp['to'] = $to;
			$temp['subject'] = $subject;
			$temp['message'] = $message;
			$temp['headers'] = $add_header;
			$temp['params']  = $add_params;
			OnePiece5::d($temp);
		}
	}
}
