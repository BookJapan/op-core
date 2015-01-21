<?php
# vim: ts=4:sw=4:tw=80
/**
 * Error.class.php
 * 
 * Creation: 2014-11-29
 * 
 * @version   1.0
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2014 (C) Tomoaki Nagahara All right reserved.
 * @package   op-core
 */

/**
 * Error
 * 
 * Creation: 2014-02-18
 * 
 * @version   1.0
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2014 (C) Tomoaki Nagahara All right reserved.
 * @package   op-core
 */
class Error
{
	const _NAME_SPACE_ = '_STACK_ERROR_';
	
	static function Set( $e, $translation=null )
	{
		if( $e instanceof Exception ){
			$message   = $e->getMessage();
			$backtrace = $e->getTrace();
			$traceStr  = $e->getTraceAsString();
			$file      = $e->getFile();
			$line      = $e->getLine();
			$prev      = $e->getPrevious();
			$code      = $e->getCode();
		}else{
			//	is message
			$message = $e;
			
			//	save debug backtrace
			$v = PHP_VERSION;
			if( version_compare($v,'5.4.0') >= 0 ){
				$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
			}else if( version_compare($v,'5.3.6') >= 0 ){
				$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
			}else if( true ){
				OnePiece5::Mark('This PHP is an old version (under 5.3.6). Was not possible to remove the argument.');
				$backtrace = null;
			}else if(version_compare($v,'5.2.5') >= 0 ){
				$backtrace = debug_backtrace(false);
			}else if(version_compare($v,'5.1.1') >= 0 ){
				$backtrace = debug_backtrace();
			}else{
				$backtrace = debug_backtrace();
			}
			
			//	serialize backtrace
			$traceStr = serialize($backtrace);
		}
		
		//	creat check key (duplicate check)
		$key = md5($traceStr);
		
		//	already exists?
		if( isset($_SESSION[self::_NAME_SPACE_][$key]) ){
			return true;
		}
		
		//	save
		$error['message'] = $message;
		$error['backtrace'] = $backtrace;
		$error['timestamp'] = date('Y-m-d H:i:s');
		$error['translation'] = $translation;
		
		//	save to session
		$_SESSION[self::_NAME_SPACE_][$key] = $error;
	}
	
	static function Get()
	{
		if( isset($_SESSION[self::_NAME_SPACE_]) ){
			return array_shift($_SESSION[self::_NAME_SPACE_]);
		}else{
			return null;
		}
	}
	
	static function Report()
	{
		//	Check exists error.
		if( empty($_SESSION[self::_NAME_SPACE_]) ){
			return;
		}
		
		//	Check admin and mime.
		if( OnePiece5::Admin() and Toolbox::isHTML() and true ){
			$io = self::_toDisplay();
		}else{
			$io = self::_toMail();
		}
		
		//	Remove error report.
		if($io){
			unset($_SESSION[self::_NAME_SPACE_]);
		}
	}
	
	static private function _getMailSubject()
	{
		foreach($_SESSION[self::_NAME_SPACE_] as $key => $backtraces){
			//	Generate error message.
			$message = $backtraces['message'];
			return $message;
		}
	}
	
	static private function _formatBacktrace( $index, $backtrace, $color=null )
	{
		$file	 = isset($backtrace['file'])	 ? $backtrace['file']:	 null;
		$line	 = isset($backtrace['line'])	 ? $backtrace['line']:	 null;
		$func	 = isset($backtrace['function']) ? $backtrace['function']: null;
		$class	 = isset($backtrace['class'])	 ? $backtrace['class']:	 null;
		$type	 = isset($backtrace['type'])	 ? $backtrace['type']:	 null;
		$args	 = isset($backtrace['args'])	 ? $backtrace['args']:	 null;
		
		$file	 = OnePiece5::CompressPath($file);
		
		if( $index === 0 ){
			$index   = '';
			$tail	 = $args[0]; // error message
		}else{
			$args	 = $args ? self::_Serialize($args): null;
			$method	 = $type ? $class.$type.$func: $func;
			$tail	 = "$method($args)";
		}
		
		$info = "![tr $color [ ![td .w1em [ $index ]] ![td .w10em .nobr[ $file ]] ![td .right[ $line ]] ![td[ $tail ]] ]]";
		
		return $info.PHP_EOL;
	}
	
	static private function _getBacktrace()
	{
		$i = 0;
		$return = '![table .small [';
		foreach( $_SESSION[self::_NAME_SPACE_] as $error ){
			$i++;
			$from = $error['translation'];
			$message = $error['message'];
			$backtraces = $error['backtrace'];
			
			//	i18n
			if( $from ){
				$message = OnePiece5::i18n()->Bulk( $message, $from );
			}
			
			//	Sequence no.
			$return .= "![tr[ ![th colspan:4 .left .red [ Error #{$i} $message ]] ]]".PHP_EOL;
			
			if( $count = count($backtraces) ){				
				foreach( $backtraces as $index => $backtrace ){
				//	$color = $index === 0 ? '.red':null;
					$color = null;
					$return .= self::_formatBacktrace( $count-$index, $backtrace, $color );
				}
			}
		}
		$return .= ']]'.PHP_EOL;
		return Wiki2Engine::Wiki2($return);
	}
	
	static private function _toDisplay()
	{
		if( Toolbox::isHtml() ){
			print self::_getBacktrace();
		}else{
			print PHP_EOL.PHP_EOL;
			print html_entity_decode(strip_tags(self::_getBacktrace()),ENT_QUOTES);
		}
		return true;
	}
	
	static private function _toMail()
	{
		$from = $_SERVER['SERVER_ADMIN'];
		$from_name = 'op-core/Error';
		
		$to = Env::GetAdminMailAddress();
		$subject = '[Error] '.self::_getMailSubject();
		$message = '';
		$headers = '';
		$parameters = "-f $from";
	//	$boundary = "--".uniqid(rand(),1)."--";
		
		//	get html message
		$html = self::_getMailMessage();
	//	$text = strip_tags($html);
		
		//	create multipart message
		/*
		$message .= "$boundary\r\n";
		$message .= "Content-Type: text/plain; charset=UTF-8\r\n";
		$message .= "Content-Disposition: inline\r\n";
		$message .= "Content-Transfer-Encoding: quoted-printable\r\n";
		$message .= "\r\n";
		$message .= quoted_printable_decode($text)."\r\n";
		$message .= "\r\n";
		$message .= "$boundary\r\n";
		$message .= "Content-Type: text/html; charset=UTF-8\r\n";
		$message .= "Content-Disposition: inline\r\n";
		$message .= "Content-Transfer-Encoding: quoted-printable\r\n";
		$message .= "\r\n";
		$message .= quoted_printable_decode($html)."\r\n";
		$message .= "$boundary\r\n";
		*/

		//	Character
		$lang = 'uni'; // Unicode only
		$char = Env::Get('charset');
		
		//	Change encoding.
		if( $lang and $char ){
			// Save original.
			$save_lang = mb_language();
			$save_char = mb_internal_encoding();
							
			// Set language use mail function.
			mb_language($lang);
			mb_internal_encoding($char);
		}

		//	header
	//	$headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
	//	$headers .= "Content-Transfer-Encoding: binary\r\n";
	//	$headers .= "MIME-Version: 1.0\r\n";
		$headers .= "Content-type: text/html; charset=UTF-8\r\n";
		$headers .= "From: ".mb_encode_mimeheader($from_name)."<$from>\r\n";
		
		//	subject
		$subject = mb_encode_mimeheader($subject);
		
		//	message
		$message = $html;
		
		//	Send mail.
		if(!$io = mail($to, $subject, $message, $headers, $parameters)){
			OnePiece5::P('Failed to send the error mail.');
		}

		// Recovery encoding.
		if( $save_lang and $save_char ){
			mb_language($save_lang);
			mb_internal_encoding($save_char);
		}
		
		return $io;
	}
	
	static private function _getMailMessage()
	{
		$key = 'Timestamp';
		$var = date('Y-m-d H:i:s');
		$tr[] = "![tr[ ![th[$key]] ![td[$var]] ]]".PHP_EOL;
		
		$key = 'UserAgent';
		$var = $_SERVER['HTTP_USER_AGENT'];
		$tr[] = "![tr[ ![th[$key]] ![td[$var]] ]]".PHP_EOL;
		
		$key = 'Host';
		$var = $_SERVER['HTTP_HOST'];
		$tr[] = "![tr[ ![th[$key]] ![td[$var]] ]]".PHP_EOL;
		
		$key = 'URL';
		$var = Toolbox::GetURL(array('port'=>1,'query'=>1)); // urldecode( $_SERVER['REQUEST_URI'] );
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
	
	static private function _Serialize( $args )
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
				case 'boolean':
					$var = $var ? 'true':'false';
					break;
				case 'array':
					$var = self::_SerializeArray($arg);
					break;
				case 'object':
					$var = get_class($arg);
					break;
				default:
					$var = "$type $arg";
			}
			$serial .= "$var, ";
		}
		$serial = trim($serial,', ');
		return $serial;
	}
	
	static private function _SerializeArray($args)
	{
		$serial = 'array(';
		foreach($args as $key => $var){
			switch($type = gettype($var)){
				case 'string':
					$var = "'$var'";
					break;
				case 'object':
					$var = 'object('.__METHOD__.')';
					break;
				default:
			}
			$serial .= "$key => $var, ";
		}
		$serial = trim($serial,', ');
		$serial .= ')'; 
		return $serial;
	}
	
	function LastError( $error )
	{
		switch($error['type']){
			case E_ERROR:	// 1
				$type = 'E_FATAL';
				break;
					
			case E_WARNING: // 2
				$type = 'E_WARNING';
				break;
					
			case E_PARSE:	// 4
				$type = 'E_PARSE';
				break;
					
			case E_NOTICE:  // 8
				$type = 'E_NOTICE';
				break;
					
			case E_STRICT:  // 2048
				$type = 'E_STRICT';
				break;
					
			case E_USER_NOTICE: // 1024
				$type = 'E_USER_NOTICE';
				break;
					
			default:
				$type = $error['type'];
		}
		
		$message = "{$error['file']} (#{$error['line']}) {$type}: {$error['message']}";
		if( OnePiece5::Admin() ){
			OnePiece5::StackError($message);
		}
	}
}
