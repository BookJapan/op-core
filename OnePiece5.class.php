<?php
# vim: tabstop=4
/**
 *  OnePiece5.class.php
 *
 * @version   1.0
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2009 (C) Tomoaki Nagahara All right reserved.
 * @package   op-core
 */

if(!function_exists('mb_language') ){
	print "<p>Does not install php-mbstring.</p>".PHP_EOL;
	print __FILE__.' ('.__LINE__.')<br/>'.PHP_EOL;
	exit;
}

/**
 * Added "op-root" to include_path.
 */
if( ! isset($_SERVER['OnePiece5']) ){
	$_SERVER['OnePiece5'] = array();
	
	// Added op-root to include_path.
	$op_root = dirname(__FILE__);
	$include_path = ini_get('include_path');
	$include_path = trim( $include_path, PATH_SEPARATOR );
	$include_path .= PATH_SEPARATOR . $op_root;
	ini_set('include_path',$include_path);		
}

/**
 * check if localhost.
 */
if($_SERVER['REMOTE_ADDR'] === '127.0.0.1' or $_SERVER['REMOTE_ADDR'] === '::1'){
	$_SERVER['OP_IS_LOCALHOST'] = true;
}else{
	$_SERVER['OP_IS_LOCALHOST'] = false;
}

/**
 * check if administrator.
 */ 
if( isset($_SERVER['ADMIN_IP']) and $_SERVER['REMOTE_ADDR'] === $_SERVER['ADMIN_IP']){
	$_SERVER['OP_IS_ADMIN'] = true;
	$_SERVER['OP_IS_DEVELOPER'] = null;
}else{
	$_SERVER['OP_IS_ADMIN'] = null;
	$_SERVER['OP_IS_DEVELOPER'] = null;	
}

/**
 * TODO: We (will|should) support to spl_autoload_register
 * @see http://www.php.net/manual/ja/function.spl-autoload-register.php
 */
if( function_exists('__autoload') ){
//	print "<p>__autoload is already exists.</p>";
}else{
//	print __FILE__.", ".__LINE__;
	function __autoload($class_name)
	{
	//	print $class_name.'<br/>';
		
		//  init
		$sub_dir  = null;
		$op_root  = OnePiece5::GetEnv('OP-Root');
		$app_root = OnePiece5::GetEnv('App-Root');
		
		//	case of model
		if( preg_match('/(Model|Config)_([a-z0-9]+)/i', $class_name, $match) ){
		//	var_dump($app_root);
			$is_model = true;
			$file_name = $match[2].'.model.php';
		}

		//  file name
		switch($class_name){
			case isset($is_model):
				break;
				
			case 'Memcache':
			case 'Memcached':
				return;
								
			case 'DML':
			case 'DML5':
			case 'DDL':
			case 'DDL5':
			case 'DCL':
			case 'DCL5':
				$sub_dir = 'PDO';
				
			default:
				$file_name = $class_name . '.class.php';
		}
		
		/**
		 *  Setup of the file reading directory.
		 */
		$dirs = array();
	//	$dirs = explode( PATH_SEPARATOR, ini_get('include_path') );
		if( isset($is_model) ){
			if( $dir = OnePiece5::GetEnv('model-dir') ){
				$dirs[] = OnePiece5::ConvertPath($dir);
			}
			$dirs[] = $op_root.'model/';
		}
		$dirs[] = '.';
		$dirs[] = $app_root;
		$dirs[] = $op_root;
		
		//	PDO or Model
		if( $sub_dir ){
			$dirs[] = $op_root.$sub_dir;
		}
		
		//	default
		$dirs = array_merge($dirs,explode( PATH_SEPARATOR, ini_get('include_path') ));
		
		//	Delete duplicate directory
		$dirs = array_unique($dirs);
		
		// check
	//	print $class_name . '<br/>' . PHP_EOL;
		foreach( $dirs as $dir ){
			$file_path = rtrim($dir,DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file_name;
			
		//	print $file_path . '<br/>' . PHP_EOL;
		//	$_SERVER['hoge'][] = $file_path;
			
			if( file_exists($file_path) ){
				include_once($file_path);
				break;
			}
		}
		
		// check
		if (!class_exists($class_name, false)) {
		//	$call = array_shift(  );
		//	print Dump::d(debug_backtrace());
			
			/*
			print Dump::d($class_name);
			print Dump::d($match);
			print Dump::d($file_name);
			print Dump::d($dirs);
			*/
			
			trigger_error("Unable to auto load class: $class_name", E_USER_NOTICE);
		}
	}
}

/**
 * @see http://jp.php.net/manual/ja/function.register-shutdown-function.php
 */
if(!function_exists('OnePieceShutdown')){
	function OnePieceShutdown()
	{
		if(!OnePiece5::Admin()){
			return;
		}
		
		//	check duplicate
		static $init;
		if(!is_null($init)){
			return;
		}
		$init = true;
		
		/**
		 * @see http://jp.php.net/manual/ja/features.connection-handling.php
		 */
		$aborted = connection_aborted();
		$status  = connection_status();

		/* @see http://www.php.net/manual/ja/errorfunc.constants.php */
		if( function_exists('error_get_last') and $error = error_get_last()){
			switch($error['type']){
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
					$er = $error['type'];
			}
			
			if( $er !== 'E_STRICT' ){
				print __FUNCTION__ . ': ' . __LINE__ . '<br>' . PHP_EOL;
				printf('%s [%s] Error(%s): %s', $error['file'], $error['line'], $er, $error['message']);
			}
		}
		
		// Session reset
		if( getenv('REMOTE_ADDR') == '127.0.0.1' ){
			$rand = rand( 0, 1000);
			if( 1 == $rand ){
				$_SESSION = array();
				print OnePiece5::Wiki2('![.red[Session is clear]]');
			}
		}
		
		//	mime
		//$mime = OnePiece5::GetEnv('mime');
		$mime = Toolbox::GetMIME();
		if( strpos( $mime, '/') ){
			list( $main_type, $sub_type ) = explode('/',$mime);
		}else{
			$sub_type = $mime;
		}
		
		switch( strtolower($sub_type) ){
			case 'plain':
			//	print PHP_EOL . ' -- OnePiece is shutdown -- ' . PHP_EOL;
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
//	const OP_UNIQ_ID = 'op-uniq-id';
	const KEY_COOKIE_UNIQ_ID = 'op-uniq-id';
	
	private $errors  = array();
	private $session = array();
	private $isInit  = null;
	private $_env;
	
	function __construct( /*$args=array()*/ )
	{
		//  For all
		$this->InitSession();
		
		//	Check CLI
		if( isset($_SERVER['SHELL']) ){
		//	$this->SetEnv('cli',true);
			$args['cli'] = true; // BEST!
			
			/*
			$_SERVER['SSH_CLIENT']
			$_SERVER['SSH_CONNECTION']
			*/
		}
		
		//	Do Initialized in the init-method.(for extends class)
		if( method_exists($this, 'Init') ){
			//  op-root has set the first.
			if(!$this->GetEnv('op-root')){
				$this->SetEnv('op-root',dirname(__FILE__));
			}
			$this->Init();
		}
		
		//	For all extends class
		/*
		foreach( $args as $key => $value ){
			//	Overwrite init env value
			$this->SetEnv( $key, $value );
		}
		*/
		
		//------------------------------------------------------//
		
		//  Check already init.
		if( $this->GetEnv('init') ){
			return;
		}
		$this->SetEnv('init',true);

		//------------------------------------------------------//
		
		//	Overwrite header
		/**
		 * @see http://www.ipa.go.jp/security/awareness/vendor/programmingv2/contents/405.html
		 * @see http://msdn.microsoft.com/en-us/library/ms533020%28VS.85%29.aspx#Use_Cache-Control_Extensions
		 */
	//	header('X-Powered-By: OnePiece/1.0',true);
	//	Case of not set timezone
	//	header('Expires: '.date('D, d M Y H:i:s ', strtotime('+1 second',time() + date('Z'))).'GMT',true);
		
		// Error control
		$save_level = error_reporting();
		error_reporting( E_ALL );
		ini_set('display_errors',1);
		
		if(!$this->GetEnv('cli') ){
			//  unique id
		//	if(!$this->GetCookie( self::KEY_COOKIE_UNIQ_ID )){
			if(empty($_COOKIE[self::KEY_COOKIE_UNIQ_ID])){
				$this->SetCookie( self::KEY_COOKIE_UNIQ_ID, md5(microtime() + $_SERVER['REMOTE_ADDR']), 0);
			}
		}
		
		//  init
		$this->_InitEnv( /*$args*/ );
		$this->_InitLocale($this->GetEnv('locale'));
		
		//  mark_label
		if( isset($_GET['mark_label']) ){
			$mark_label = $_GET['mark_label'];
			$mark_value = $_GET['mark_label_value'];
			Developer::SaveMarkLabelValue($mark_label,$mark_value);
		}
		
		//  recovery (display_errors)
		if( $this->admin() ){
		//	ini_set('display_errors',1); // call is duplicate 
		}else{
			ini_set('display_errors',0);
		}
		
		//  recovery (error_reporting)
		error_reporting( $save_level );
	}

	/**
	 * 
	 */
	function __destruct()
	{
		//  Called Init?
		if(!$this->isInit){
			$format  = $this->i18n()->get('%s has not call "parent::init();".');
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
	
	public static function __callStatic( $name , $arguments )
	{
		//  PHP 5.3.0 later
		$func    = __FUNCTION__;
		$caller  = $this->GetCallerLine();
		$message = "MAGIC METHOD ($func): $name, ".serialize($arguments);
		
		$this->StackError( __FUNCTION__ );
		$this->mark(PHP_VERSION);
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
		$this->isInit = true;
		
		//  No use self.
		if( $this instanceof i18n ){
			return true;
		}
		
		//  Create i18n configuration file path.
		$path = $this->ConvertPath('op:/i18n/'.get_class($this).'.i18n.php');
		
		//  Include configuration file.
		if( file_exists($path) ){
			$this->i18n()->SetByFile($path);
		}
		
		return true;
	}
	
	/**
	 * You check whether you an administrator by IP-Address.
	 */
	static function Admin()
	{
		if(!is_null($_SERVER['OP_IS_ADMIN'])){
			return  $_SERVER['OP_IS_ADMIN'];
		}
		
		$server_addr = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR']: '127.0.0.1';
		$remote_addr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR']: null;
		$remote_addr = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR']: $remote_addr;
		
		//	Identity
		if( $server_addr == $remote_addr ){
			$io = true;
		}else
		
		//	External access
		if( self::GetEnv('admin-ip') == $remote_addr ){
			$io = true;
		}else{
			$io = false;
		}
		
		$_SERVER['OP_IS_ADMIN'] = $io;
		
		return $io;
	}
	
	/**
	 * Error stacking
	 * 
	 * @param string $message is message.
	 * @param string $class is label use to print.
	 */
	static function StackError( $args )
	{
		$encoding = mb_internal_encoding();
		
		//  TODO: To model
		if( $args instanceof Exception ){
			$e = $args;
			$message  = $e->getMessage();
			$traceArr = $e->getTrace();
			$traceStr = $e->getTraceAsString();
			$file     = $e->getFile();
			$line     = $e->getLine();
			$prev     = $e->getPrevious();
			$code     = $e->getCode();
			
			$file     = self::CompressPath($file);
			$catch    = self::GetCallerLine();
			$incident = "$file [$line]";
			
			$file = $traceArr[0]['file'];
			$line = $traceArr[0]['line'];
			$func = $traceArr[0]['function'];
			$class= isset($traceArr[0]['class']) ? $traceArr[0]['class'] : null;
			$type = isset($traceArr[0]['type'])  ? $traceArr[0]['type']  : null;
			$args = isset($traceArr[0]['type'])  ? var_export( $traceArr[0]['args'], true) : null;
			$args = str_replace(array("\r","\n",'array ()'), array('','',''), $args);
			
			$file = self::CompressPath($file);
			$trace = "$file [$line] {$class}{$type}{$func}($args)";
		}else{
			$incident = self::GetCallerLine( 1, 1, 'incident');
			$message  = self::Escape( $args, $encoding );
			$trace    = self::GetCallerLine( 0, -1, 'trace');
		}
		
		$error['incident'] = $incident;
		$error['message']  = $message;
		$error['trace']	   = $trace;
		
		$_SERVER['OnePiece5']['errors'][] = $error;
	}
	
	/**
	 * Error print.
	 */
	function PrintError()
	{
		// init
		$nl		 = self::GetEnv('nl');
		$cli	 = self::GetEnv('cli');
		$mime	 = self::GetEnv('mime');
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
		
		// text/plane
		if( false ){
			$print = strip_tags( $print, null );
		}
		
		// Finish
		if( $cli /*self::GetEnv('Pacifista')*/ ){
			print strip_tags( html_entity_decode( $print, ENT_QUOTES, $this->GetEnv('charset') ) );
		}else if( !self::Admin() ){
			$ua   = $this->GetEnv('UserAgent');
			$ip   = $this->GetEnv('RemoteIp');
			$href = $this->GEtEnv('href');
			$host = $ip ? gethostbyaddr($ip): null;
			$date = date('Y-m-d H:i:s');
			$url  = $this->GetURL('url');
			
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
		}else{
			print $javascript . $nl;
			print $print;
		}
		
		return true;
	}
	
	/*
	function StackLog( $string, $tag=null )
	{
		$class    = self::GetCallerLine( 1, 1, '$class'); // get_called_class
		$incident = self::GetCallerLine( 1, 1, 'incident') . ' ';
		$method   = self::GetCallerLine( 1, 1, '$method');
		$str = "$incident $method / $string";
		$log['tag'] = "$class, $tag";
		$log['str'] = $str;
		$_SERVER[__CLASS__]['logs'][] = $log;
	}
	
	function PrintLog( $args=null )
	{
		if(!self::Admin()){
			print "<!-- not admin. -->";
			return;
		}
		
		//	Require OnePiece Style-Sheet. (print from Toolbox.class.php)
		$this->SetEnv('isRequireStyleSheet',true);
		
		// init
		$br = '<br/>';
		$nl = self::GetEnv('newline');
		$tags = explode(',',$args);
		
		if( $logs = @$_SERVER[__CLASS__]['logs'] ){
			print '<div class="OnePiece small" style="">' . $nl;
			foreach($logs as $log){
				$tag = $log['tag'];
				$str = self::wiki2($log['str']);
				foreach($tags as $temp){
					$temp = trim($temp);
					if( preg_match("/$temp/",$tag) ){
						print $str . $br . $nl;
						break;
					}
				}
			}
			print '</div>' . $nl;
		}
	}
	*/
	
	/**
	 * locale setting.
	 * 
	 * @see http://jp.php.net/manual/ja/class.locale.php
	 * @param string $locale lang_territory.codeset@modifier
	 * @return void
	 */
	private function _InitLocale( $locale=null ){
		
		// @todo We will support to de_DE@euro
		
		/**
		 * Windows 
		 * 	Japanese_Japan.932 = sjis
		 * 	Japanese_Japan.20932 = euc-jp
		 * 
		 * PostgreSQL for Windows
		 * 	Japanese_Japan.932 = utf-8 // auto convert
		 * 	
		 * http://lets.postgresql.jp/documents/technical/text-processing/2/
		 * 
		 */
		
		if(!$locale){
			$locale = 'ja_JP.utf-8';
		}
		
		if( preg_match('|([a-z]+)[-_]?([a-z]+)?\.?([-_a-z0-9]+)?|i', $locale, $match) or true){
			$lang = isset($match[1]) ? $match[1]: 'ja';
			$area = isset($match[2]) ? $match[2]: 'JP';
			$code = isset($match[3]) ? $match[3]: 'utf-8';
		}
		
		// Windows is unsupport utf-8
		if( PHP_OS == 'WINNT' and $lang == 'ja' ){
			// Shift_JIS
			setlocale( LC_ALL, 'Japanese_Japan.932');
		}else if(!setlocale( LC_ALL, $locale )){
			/* @see http://jp.php.net/manual/ja/function.setlocale.php */
			$this->StackError("Illigal locale: $locale");
			return false;
		}
		
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
		if( $area === 'JP'){
			$timezone = 'Asia/Tokyo';
		}
		
		mb_language($lang);
		mb_internal_encoding($code);
		mb_detect_order($codes);
		//mb_http_input();
		//mb_http_output()
		ini_set('date.timezone',$timezone);
		//date_default_timezone_set($timezone);
		
		$this->SetEnv('locale', $locale);
		$this->SetEnv('lang',   $lang);
		$this->SetEnv('area',   $area);
		$this->SetEnv('charset',$code);
	}
	
	/**
	 * Getter and Setter uses.
	 * 
	 * @param string $key
	 * @param string|array $var
	 */
	static private function _Env( $key, $var=null, $ope )
	{
		// convert key name
		$key = strtolower($key);
		switch( $key ){
			case 'nl':
				$key = 'newline';
				break;
				
			case 'href':
				$key = 'HTTP_REFERER';
				break;
				
			case 'lang':
				$key = 'language';
				break;
				
			case 'fqdn':
			case 'domain':
				$key = 'HTTP_HOST';
				break;
				
			default:
				/* Convert to unix path separator from Windows path separator.
				 * C:¥www¥htdocs -> C:/www/htdocs
				 */
				if( preg_match( '/^([a-z0-9]+)[-_]?(root|dir|mail)$/',$key, $match ) ){
					$key = $match[1].'_'.$match[2];
					if( $match[2] === 'mail' ){
						//  mail
					}else if( $ope === 'set' ){
						$var = str_replace( '\\', '/', $var);
						$var = rtrim($var,'/') . '/';
					}
				}
				break;
		}
		
		// get key's value
		switch($key){
			case isset($_SERVER[strtoupper($key)]):
				$var = $_SERVER[strtoupper($key)];
				break;
				
			default:
				if( $ope == 'set' ){
					
					$_SERVER[__CLASS__]['env'][$key] = $var;
					
				}else if( $ope == 'get' ){
					if( isset( $_SERVER[__CLASS__]['env'][$key])){
						$var = $_SERVER[__CLASS__]['env'][$key];
					}
				}else{
					self::StackError('Operand is empty.');
				}
				break;
		}
		
		if( empty($var) ){
			switch($key){
				case 'encrypt-key':
					$var = OnePiece5::GetEnv('admin-mail');
					break;
			}
		}
		
		return $var;
	}
	
	private function _InitEnv()
	{
		/* Added to .htaccess or httpd.conf the sample below.
		 * SetEnv ADMIN_IP 192.168.1.1
		 */
		if( isset($_SERVER['ADMIN_ADDR']) ){
			$_SERVER['ADMIN_IP'] = $_SERVER['ADMIN_ADDR']; 
		}
		$admin_ip = isset($_SERVER['ADMIN_IP']) ? $_SERVER['ADMIN_IP']: '127.0.0.1';
		
		// server admin(mail address)
		if( isset($_SERVER['SERVER_ADMIN']) and
			preg_match('|[-_a-z0-9\.\+]+@[-_a-z0-9\.]+|i', $_SERVER['SERVER_ADMIN']) ){
			$admin_mail = $_SERVER['SERVER_ADMIN'];
		}else{
			$admin_mail = 'noreply@onepiece-framework.com';
		}
		
		//  any root
		$op_root   = dirname(__FILE__);
		$doc_root  = $_SERVER['DOCUMENT_ROOT'];
		$app_root  = dirname($_SERVER['SCRIPT_FILENAME']);
		$site_root = realpath($_SERVER['DOCUMENT_ROOT'].'/../');
		
		// Windows
		if( PHP_OS == 'WINNT' ){
			$op_root   = str_replace( '\\', '/', $op_root   );
			$doc_root  = str_replace( '\\', '/', $doc_root  );
			$app_root  = str_replace( '\\', '/', $app_root  );
			$site_root = str_replace( '\\', '/', $site_root );
		}
		
		$this->SetEnv('class',      __CLASS__    );
		$this->SetEnv('doc_root',   $doc_root    );
		$this->SetEnv('app_root',   $app_root    );
		$this->SetEnv('site_root',  $site_root   );
		$this->SetEnv('admin-ip',   $admin_ip    );
		$this->SetEnv('admin-mail', $admin_mail  );
		$this->SetEnv('new_line',    PHP_EOL      );
	}
	
	/**
	 * Set env-value.
	 * 
	 * @param string $key
	 * @param string|array $var
	 */
	static function SetEnv( $key, $var )
	{
		if($_SERVER['OP_IS_LOCALHOST']){
			return Env::Set($key, $var);
		}
		
		return self::_Env( $key, $var, 'set' );
	}

	/**
	 * Get env-value.
	 * 
	 * @param string $key
	 */
	static function GetEnv( $key )
	{
		if($_SERVER['OP_IS_LOCALHOST']){
			return Env::Get($key);
		}
		
		switch(strtolower($key)){
			case 'url':
				if(empty($this)){
					print OnePiece5::GetCallerLine();
				}
				$this->mark('Use GetURL method. (ex. Toolbox::GetURL($config))');
				$result = null;
				break;
				
			default:
				$result = self::_Env( $key, null, 'get' );
		}
		
		return self::Escape($result);
	}
	
	function InitSession()
	{
		//  start to session.
		if(!session_id()){
			if( headers_sent($file,$line) ){
				$this->StackError("Header has already been sent. Check $file, line no. $line.");
			}else{
				session_start();
			}
		}
		
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
	function GetSession( $key )
	{
		if( isset( $this->session[$key] ) ){
			return $this->session[$key];
		}else{
			return null;
		}
	}

	/**
	 * SetCookie is auto set to $_COOKIE, and value is valid all value! (string, number, array and object!!)
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
	function SetCookie( $key, $value, $expire=0, $path='/', $domain='', $secure=0, $httponly=true )
	{
		$key   = $this->Escape($key);
		$value = $this->Escape($value);
		
		if( is_null($expire) ){
			$this->StackError("expire does not set. (ex. 0 is 365days, -1 is out of valid expire.)");
		}
		
		if( headers_sent() ){
			$this->StackError("already header sent.");
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
		//$_key   = md5($key);
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
			$this->mark('set cookie is error');
			$this->StackError('SetCookie is fail: key$key'.$value);
		}
		
		return $io;
	}
	
	function GetCookie($key)
	{
		if( isset($_COOKIE[$key]) ){
			$value = $_COOKIE[$key];
			$value = unserialize($value);
			$value = $this->Escape($value);
		}else{
			$value = null;
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
		// file system encoding
		$encode_file_system = PHP_OS === 'WINNT' ? 'sjis-win': 'utf-8';
		
		//  init
		$call_line = '';
		$depth++;
		$nl = self::GetEnv('nl');
		//  debug_backtrace
		if( version_compare(PHP_VERSION, '5.2.5') >= 0 ){
			$back = debug_backtrace(false);
		}else{
			$back = debug_backtrace();
		}
		
		// num
		if( $num >= count($back) or $num <= 0 ){
			$num = count($back) -1;
		}
		
		// loop
		for($i=1; $i <= $num; $depth++, $i++){
			
			$func  = $back[$depth]['function'];
			$args  = $back[$depth]['args'];
			$file  = isset($back[$depth]['file'])  ? $back[$depth]['file']:  '';
			$line  = isset($back[$depth]['line'])  ? $back[$depth]['line']:  '';
			$type  = isset($back[$depth]['type'])  ? $back[$depth]['type']:  '';
			$class = isset($back[$depth]['class']) ? $back[$depth]['class']: '';
			
			$line1m = isset($back[$depth-1]['line']) ? $back[$depth-1]['line']: '';
			
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
							$str = var_export($var,true);
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
							$format = '$file ($class$type$func) [$line] ';
						}else{
							$format = '$file ($func) [$line] ';
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
		
		$patt = array("|^$app_root|","|^$doc_root|","|^$op_root|");
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
	function Mark( $str='', $mark_labels=false )
	{
		// displayed is only Admin-ip.
		if(!self::admin()){ return; }
		
		// displayed is Admin-ip and flag.
		if( $mark_labels ){
			foreach( explode(',',$mark_labels) as $mark_label ){
				Developer::SetMarkLabel( $mark_label );
			}
			if(!Developer::GetSaveMarkLabelValue($mark_label) ){
				return;
			}
		}
		
		// php momory usage 
		$memory_usage = memory_get_usage(true) /1000;
		list( $mem_int, $mem_dec ) = explode( '.', $memory_usage );
		$memory = sprintf('![ .gray [(%s.![.smaller[%s]] KB)]]', number_format($mem_int), $mem_dec );
		
		//  call line
		$call_line = self::GetCallerLIne();
		
		//  message
		if( is_null($str) ){
			$str = '![ .red [null]]';
		}else
		if( is_bool($str) ){
			$str = $str ? '![ .blue [true]]': '![ .red [false]]';
		}else
		if( $str and !is_string($str) ){
			$str = var_export($str,true);
			$str = str_replace( array("\r","\n"), array('',''), $str);
		}
		
		$nl = self::GetEnv('nl');
		$attr['class'] = array('OnePiece','mark');
		$attr['style'] = array('font-size'=>'9pt','background-color'=>'white');
		$string = self::Html("$nl$call_line - $str $memory$nl",'div',$attr);
		if( self::GetEnv('cli') ){
			$string = strip_tags($string);
			if( self::GetEnv('css') ){
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
		if( self::GetEnv('cli') ){
			print trim(strip_tags(self::Html( $str, $tag, $attr ))).PHP_EOL;
		}else{
			print self::Html( $str, $tag, $attr );
		}
	}
	
	/**
	 * 
	 * @param string|array $args
	 * @param string $use_get_flag
	 */
	function D( $args, $mark_label=null )
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
		if( $this->GetEnv('cli') ){
			$this->p($line);
			var_dump($args);
			return;
		}
		
		if( class_exists('Dump',true) ){
			self::p($line, 'div', array('class' => array('OnePiece','small','bold','mark'), 
			                            'style' => array('color'=>'black',
			                            				 'font-size' => '9pt',
														 'background-color'=>'white'
														)));
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
				$args = self::EscapeString($args);
				break;
			case 'array':
				$args = self::EscapeArray($args);
				break;
			case 'object':
				$args = self::EscapeObject($args);
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
		//  $args = trim( $args, "\x00..\x1F");
		
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
		$temp = new stdClass();
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
			$headers[] = "Content-Type: text/plain; charset=$char";
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
	function Template( $file, $data=null )
	{
	//	print $this->GetCallerLine() . PHP_EOL;
	//	print "$file" . PHP_EOL;
		
		if(!is_string($file)){
			$this->StackError("Passed arguments is not string. (".gettype($file).")");
			return false;
		}
		
		//  for developper's debug
	//	$this->mark( $this->GetCallerLine(1) );
		$this->mark($file,'template');
		
		//  access is deny, above current directory
		if( $this->GetEnv('allowDoubleDot') ){
			//  OK
		}else if( preg_match('|^\.\./|',$file) ){ 
			$this->StackError("Does not allow parent directory.($file)");
			return false;
		}
		
		//	necessary to convert the path?
		if( file_exists($file) ){
			//  absolute
			$path = $file;
		}else if( file_exists($path = self::ConvertPath($file)) ){
			//  abstract
		}else if( $dir = $this->GetEnv('template-dir') ){
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
	 * Convert browser url. (base is document root.)
	 * 
	 * @param string $path
	 * @return string
	 */
	function ConvertURL( $args, $domain=false )
	{
		//	Check if abstract path.
		if( preg_match('|^([a-z][a-z0-9]+):/(.*)|i',$args,$match) ){
			$modifier = $match[1];
			$path = $match[2];
			switch( $modifier ){
				case 'http':
				case 'https':
					return $args;
					
				case 'dot':
					$route = self::GetEnv('route');
					$tmp_root = rtrim( $route['path'], '/' ) . '/'; 
					break;
					
				default:
					$tmp_root = self::GetEnv( $modifier . '_root' );
			}
			
			//  Windows
			if( PHP_OS == 'WINNT' ){
				$tmp_root = str_replace( '\\', '/', $tmp_root );
			}
			
			//  create absolute path. 
			$absolute = $tmp_root . $path;
			
		}else{
			
			//	replace document root.
			$args = preg_replace( '|^'.rtrim(self::GetEnv('doc-root'),'/').'|', '', $args );
			$args = str_replace('\\','/',$args);
			
			return $args;
		}
		
		//  create relative path from document root.
		$doc_root = self::GetEnv('doc-root');
		
		//	If path is alias.
		if( $alias_root = self::GetEnv('alias-root') ){
			
			//	
			$base_url = str_replace( $doc_root, '/', $alias_root);
			
			//	
			switch(strtolower($modifier)){
				case 'app':
					$url = $base_url . $path;
					break;
				case 'layout':
					$app_root    = self::GetEnv('app-root');
					$layout_root = self::GetEnv('layout-root');
					$url = $base_url . str_replace( $app_root, '', $layout_root) . $path;
					break;
				default:
					$this->mark($modifier);
			}
		}else{
			//	replace
			$patt = array(); 
			$patt[] = "|^$doc_root|i";
			$url = preg_replace($patt,'',$absolute);
		}
		
		//	Added domain
		if( $domain ){
			if( is_bool($domain) ){
				$domain = Toolbox::GetDomain(array('scheme'=>true));
			}
		}
		
		return rtrim($domain,'/').'/'.ltrim($url,'/');
	}
	
	/**
	 * Convert server-side full-path.
	 * 
	 * @param  string $path
	 * @return string
	 */
	static function ConvertPath( $path )
	{
		$orig = $path;
		
		if( preg_match('|^/|i',$path) ){
			//	Root directory (Unix)
		}else
		if( preg_match('|^[a-z]:|i',$path) ){
			//	Drive letter (Windows)
		}else
		if( preg_match('/^(op|site):\//',$path,$match) ){
			//  Does not relate document-root.
			$label = $match[1].'-root';
			if( $root = OnePiece5::GetEnv($label) ){
				$path = str_replace( $match[0], $root, $path );
			}else{
				self::StackError("$label is not set.");
			}
		}else
		if( preg_match('|^([-_a-zA-Z0-9]+):/|',$path,$match) ){
			$label = $match[1].'-root';
			if( $root = self::GetEnv($label) ){
				$path = str_replace( $match[0], $root, $path );
			}else{
				self::StackError("$label is not set.");
			}
		}
				
		return $path;
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
	function Model($name)
	{
		try{
			//  name check
			if(!$name){
				$msg = "Model name is empty.";
				throw new OpModelException($msg);
			}
			
			//	hogeHoge -> HogeHoge	//	hogeHoge -> Hogehoge
			$name = ucfirst($name);		//	$name = ucfirst(strtolower($name));
			
			//  Notice
			if( strpos( $name, '_') ){
				$this->mark('Underscore(_) is reserved. For the feature functions. (maybe, namespace)');
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
			$model_dir = $this->GetEnv('model-dir');
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
			$this->StackError( $e->getMessage() . "($file, $line)" );
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
	 * Separate for each instance.
	 * 
	 * @var PDO5
	 */
	private $pdo;
	
	/**
	 * Get PDO5 object
	 * 
	 * @param  $name class name
	 * @return PDO5
	 */
	function PDO( $name=null )
	{
		if( empty($this->pdo) ){
			if( is_null($name) ){				
				$name = 'PDO5';
				$op_root = $this->GetEnv('op-root');
				$path = $op_root.'PDO/PDO5.class.php';
				if( $io = file_exists($path) ){					
					include_once($path);
				}else{
					$this->StackError("Does not exists file. ($path)");
					return false;
				}
			}
			if(!$this->pdo = new $name()){
				$this->StackError("Can not create object. ($name)");
				return false;
			}
		}
		
		return $this->pdo;
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
	 * Abstract Form object.
	 * 
	 * @param  string $name Class name
	 * @return Form5
	 */
	function Form( $name='Form5' )
	{
		$obj = &$_SERVER[__CLASS__][__METHOD__];
		
		if( empty($obj) ){
			if(!$obj = new $name()){
				$obj = OnePiece5();
			}
		}
		
		return $obj;
	}
	
	/**
	 * i18n is translate object.
	 * 
	 * @param  string $name Object name
	 * @return i18n
	 */
	function i18n( $name='i18n' )
	{
		static $obj;

		if( empty($obj) ){
			if(!$obj = new $name()){
				$obj = OnePiece5();
			}
		}
		
		return $obj;
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
		}else if(!is_string($string)){
		//	self::d($string);
		//	var_dump($string);
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
	 * Cache is presents the [memcache/memcached/other] interface.
	 * 
	 * @param  string $name
	 * @throws Exception
	 * @return Cache
	 */
	function Cache($name='Cache')
	{
		if(empty($_SERVER[__CLASS__]['singleton'][$name])){
			if(!class_exists($name)){				
				if(!include("$name.class.php") ){
					throw new OpException("Include is failed. ($name)");
				}
			}
			if(!$_SERVER[__CLASS__]['singleton'][$name] = new $name() ){
				throw new OpException("Instance object is failed. ($name)");
			}
		}
		return $_SERVER[__CLASS__]['singleton'][$name];
	}
	
	/**
	 * 
	 * 
	 * @param boolen $args
	 */
	function Vivre( $register )
	{
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
					$xhost = $_SERVER['HTTP_X_FORWARDED_HOST']; // HTTP_X_FORWARDED_SERVER
					$uri   = $_SERVER['REQUEST_URI'];
					
					$ip = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR']: $_SERVER['REMOTE_ADDR'];
					$domain = gethostbyaddr($ip);
					
					$ua = $_SERVER['HTTP_USER_AGENT'];
					
					$args = array();
					$args['to']		 = $this->GetEnv('admin-mail');
					$args['subject'] = '[OnePiece] VIVRE ALERT';
					$args['body']	.= "HOST = $host \n";
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
	
	/**
	 * @return Wizard
	 */
	function Wizard()
	{
		if( !isset($_SERVER[__CLASS__]['singleton']['Wizard']) ){
			$_SERVER[__CLASS__]['singleton']['Wizard'] = new Wizard();
		}
		return $_SERVER[__CLASS__]['singleton']['Wizard'];
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

class Env
{
	const _NAME_SPACE_		 = 'ONE_PIECE_5';
	const _ADMIN_IP_ADDR_	 = 'admin_ip';
	const _ADMIN_EMAIL_ADDR_ = 'admin_mail';
	
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
				if(
					/* Match from
					 * OP_ROOT  or OP-ROOT
					 * APP_ROOT or APP-ROOT
					 * DOC_ROOT or DOC-ROOT
					 */
					strlen($key) > 6 &&
				   ($key[strlen($key)-5] === '_' ||
					$key[strlen($key)-5] === '-')&& 
					$key[strlen($key)-4] === 'R' &&
					$key[strlen($key)-3] === 'O' &&
					$key[strlen($key)-2] === 'O' &&
					$key[strlen($key)-1] === 'T' 
				){
					/* Convert to 
					 * OP-ROOT  -> OP_ROOT
					 * APP-ROOT -> APP_ROOT
					 * DOC-ROOT -> DOC_ROOT
					 */
					$is_path = true;
					$key[strlen($key)-5] = '_';
				}else
				if(
					strlen($key) > 5 &&
				   ($key[strlen($key)-4] === '_' ||
					$key[strlen($key)-4] === '-')&&
					$key[strlen($key)-3] === 'D' &&
					$key[strlen($key)-2] === 'I' &&
					$key[strlen($key)-1] === 'R'
				){
					$is_path = true;
					$key[strlen($key)-4] = '_';
				}
		}
			
		//	If var of path.
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
			/*
			var_dump($key);
			var_dump($var);
			print '<hr/>';
			*/
		}
		
		return array( $key, $var );
	}
	
	static function Get( $key )
	{
		$key = strtoupper($key);
		
		list( $key, $var ) = self::_Convert( $key );
		
		if( isset($_SERVER[self::_NAME_SPACE_][$key]) ){
			$var = $_SERVER[self::_NAME_SPACE_][$key];
		}else if( isset($_SERVER[$key]) ){
			$var = $_SERVER[$key];
		}else{
			$var = null;
		}
		
		return $var;
	}
	
	static function Set( $key, $var )
	{
		$key = strtoupper($key);
		if( $key === 'DEVELOPER' ){
			return self::SetDeveloper($var);
		}
		list( $key, $var ) = self::_Convert( $key, $var );
		$_SERVER[self::_NAME_SPACE_][$key] = $var;
	}
	
	static function SetDeveloper( $var )
	{
		$remote_addr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR']: null;
		$remote_addr = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR']: $remote_addr;
		$_SERVER['OP_IS_ADMIN'] = $remote_addr === $var ? true: false;
	}
}

/**
 * Error controller
 * 
 * 2014-02-18
 */
class Error
{
	const _NAME_SPACE_ = '_STACK_ERROR_';
	
	function Set( $message )
	{
		//	save debug backtrace
		if( version_compare(PHP_VERSION, '5.2.5') >= 0 ){
			$backtrace = debug_backtrace(false);
		}else{
			$backtrace = debug_backtrace();
		}
		
		//	creat check key (duplicate check)
		$key = md5(serialize($backtrace));
		
		//	already exists?
		if( isset($_SESSION[self::_NAME_SPACE_][$key]) ){
			return true;
		}
		
		//	save
		$error['message'] = $message;
		$error['backtrace'] = $backtrace;
		$error['timestamp'] = date('Y-m-d H:i:s');
		
		//	save to session
		$_SESSION[self::_NAME_SPACE_][$key] = $error;
	}
	
	function Report( $admin )
	{
		if(empty($_SESSION[self::_NAME_SPACE_])){
			print "<p>Error is not occur.</p>";
			return;
		}
		
		if( $admin ){
			$io = self::_toDisplay();
		}else{
			$io = self::_toMail();
		}
		
		if($io){
			unset($_SESSION[self::_NAME_SPACE_]);
		}
	}
	
	private function _getMailSubject()
	{
		foreach($_SESSION[self::_NAME_SPACE_] as $key => $backtraces){
		//	dump::d($backtraces['backtrace'][0]);
			return  strip_tags(self::_formatBacktrace( 0, $backtraces['backtrace'][0] ));
		}
	}
	
	private function _formatBacktrace( $index, $backtrace )
	{
		$file	 = isset($backtrace['file'])	 ? $backtrace['file']:	 null;
		$line	 = isset($backtrace['line'])	 ? $backtrace['line']:	 null;
		$func	 = isset($backtrace['function']) ? $backtrace['function']: null;
		$class	 = isset($backtrace['class'])	 ? $backtrace['class']:	 null;
		$type	 = isset($backtrace['type'])	 ? $backtrace['type']:	 null;
		$args	 = isset($backtrace['args'])	 ? $backtrace['args']:	 null;
		
		$file	 = OnePiece5::CompressPath($file);
		
		if( $index === 0 ){
			$info = "![div .small .red [$file [$line] {$args[0]}]]";
		}else{
			$args	 = $args ? self::_Serialize($args): null;
			$method	 = $type ? $class.$type.$func: $func;
			$info = "![div .small [$file [$line] $method($args)]]";
		}
		
		return Wiki2Engine::Wiki2(htmlentities($info)).PHP_EOL;
	}
	
	private function _getBacktrace()
	{
		$return = '';
		foreach( $_SESSION[self::_NAME_SPACE_] as $error ){
			$message = $error['message'];
			$backtraces = $error['backtrace'];
			foreach( $backtraces as $index => $backtrace ){
				//	dump::d($backtrace);
				$return .= self::_formatBacktrace( $index, $backtrace );
			}
		}
		return $return;
	}
	
	private function _toDisplay()
	{
		print self::_getBacktrace();
		return true;
	}
	
	private function _toMail()
	{
	//	dump::d($_SERVER);
		
		$from = $_SERVER['SERVER_ADMIN'];
		$from_name = 'OnePiece-Framework/Error';
		
		$to = Env::Get(Env::_ADMIN_EMAIL_ADDR_);
		$subject = '[Error] '.self::_getMailSubject();
		$message = '';
		$headers = '';
		$parameters = "-f $from";
		$boundary = "--".uniqid(rand(),1);
		
		//	get html message
		$html = self::_getMailMessage();
		$text = strip_tags($html);
		
		//	create multipart header
		$headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
		$headers .= "Content-Transfer-Encoding: binary\r\n";
		$headers .= "MIME-Version: 1.0\r\n";
		$headers .= "Content-type: text/html; charset=UTF-8\r\n";
		$headers .= "From: ".mb_encode_mimeheader($from_name)."<$from>\r\n";
		
		//	create multipart message
		$message .= "--$boundary\r\n";
		$message .= "Content-Type: text/plain; charset=UTF-8\r\n";
		$message .= "Content-Disposition: inline\r\n";
		$message .= "Content-Transfer-Encoding: quoted-printable\r\n";
		$message .= "\r\n";
		$message .= quoted_printable_decode($text)."\r\n";
		$message .= "\r\n";
		$message .= "--$boundary\r\n";
		$message .= "Content-Type: text/html; charset=UTF-8\r\n";
		$message .= "Content-Disposition: inline\r\n";
		$message .= "Content-Transfer-Encoding: quoted-printable\r\n";
		$message .= "\r\n";
		$message .= quoted_printable_decode($html)."\r\n";
		$message .= "--$boundary\r\n";
		
	//	dump::d($to);
	//	dump::d($subject);
	//	print nl2br($text);
	//	print $html;
		
		if(!$io = mail($to, $subject, $message, $headers, $parameters)){
			print '<p style="color:white;background-color:black;">failed to send the error mail.</p>';
		}else{
			print "<p>Sendmail is successful.</p>";
		}
		return $io;
	}
	
	private function _getMailMessage()
	{
		$key = 'Timestamp';
		$var = date('Y-m-d H:i:s');
		$tr[] = "![tr[ ![th[$key]] ![td[$var]] ]]".PHP_EOL;
		
		$key = 'UserAgent';
		$var = $_SERVER['HTTP_USER_AGENT'];
		$tr[] = "![tr[ ![th[$key]] ![td[$var]] ]]".PHP_EOL;
		
		$key = 'URL';
		$var = $_SERVER['HTTP_HOST']. urldecode( $_SERVER['REQUEST_URI'] );
		$tr[] = "![tr[ ![th[$key]] ![td[$var]] ]]".PHP_EOL;
		
		$key = 'Referer';
		$var = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER']: null;
		$tr[] = "![tr[ ![th[$key]] ![td[$var]] ]]".PHP_EOL;
		
		$table = '![table['.join('',$tr).']]'.PHP_EOL;
		
		$message  = Wiki2Engine::Wiki2($table);
		$message .= '<hr/>'.PHP_EOL;
		$message .= self::_getBacktrace();
		return $message;
	}
	
	private function _Serialize( $args )
	{
		$serial = '';
		foreach( $args as $arg ){
			switch($type = gettype($arg)){
				case 'NULL':
					$var = 'null';
					break;
				case 'string':
					$var = "'$arg'";
					break;
				case 'array':
					$var = self::_SerializeArray($arg);
					break;
				default:
					$var = "$type $arg";
			}
			$serial .= "$var, ";
		}
		//$serial = preg_replace('/, $/', '', $serial);
		$serial = trim($serial,', ');
		return $serial;
	}
	
	private function _SerializeArray($args)
	{
		$serial = 'array(';
		foreach($args as $key => $var){
			switch($type = gettype($var)){
				case 'string':
					$var = "'$var'";
					break;
				default:
			}
			$serial .= "$key => $var, ";
		}
		$serial = trim($serial,', ');
		$serial .= ')'; 
		return $serial;
	}
}
