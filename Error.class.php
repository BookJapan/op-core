<?php
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
	
	static private function _Set( $name, $message, $backtrace=null, $translation=false )
	{
		if(!$backtrace){
			$backtrace = debug_backtrace();
		}
		
		//	key
		$key = md5("$name, $message");
		
		//	save
		$error['name']		 = $name;
		$error['message']	 = $message;
		$error['backtrace']	 = $backtrace;
		$error['timestamp']	 = date('Y-m-d H:i:s');
		$error['translation']= $translation;
		
		//	save to session
		$_SESSION[self::_NAME_SPACE_][$key] = $error;
	}
	
	static function Set( $e, $translation=null )
	{
		$name = null;
		
		if( $e instanceof Exception ){
			$message   = $e->getMessage();
			$backtrace = $e->getTrace();
		//	$traceStr  = $e->getTraceAsString();
			$file      = $e->getFile();
			$line      = $e->getLine();
			$prev      = $e->getPrevious();
			$code      = $e->getCode();
			if( method_exists($e,'getLang') ){
				$translation = $e->getLang();
			}
		}else{
			//	is message
			$message = $e;
			
			//	
			$backtrace = debug_backtrace();
		}
		
		self::_Set( $name, $message, $backtrace, $translation );
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
		
		//	Check display is html.
		if(!$is_html = Toolbox::isHTML() and !$is_cli = Env::Get('cli') ){
			return;
		}
		
		//	Check admin and mime.
		if( OnePiece5::Admin() ){
			$io = self::_toDisplay();
		}else{
			$io = self::_toMail();
		}
		
		//	Remove error report.
		if( $io ){
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
	
	static private function _formatBacktrace( $index, $backtrace, $name )
	{
		static $find;
		$file	 = isset($backtrace['file'])	 ? $backtrace['file']:	 null;
		$line	 = isset($backtrace['line'])	 ? $backtrace['line']:	 null;
		$func	 = isset($backtrace['function']) ? $backtrace['function']: null;
		$class	 = isset($backtrace['class'])	 ? $backtrace['class']:	 null;
		$type	 = isset($backtrace['type'])	 ? $backtrace['type']:	 null;
		$args	 = isset($backtrace['args'])	 ? $backtrace['args']:	 null;
		
		$file	 = OnePiece5::CompressPath($file);
		
		if( $name === $func or $func === '__get' or $func === 'StackError' ){
			$find = true;
			$style = 'bg-yellow bold';
		}else if( $find ){
			$style = 'gray';
		}else{
			$style = 'gray';
		}
		
		if( $index === 0 ){
			$index   = '';
			$tail	 = $args[0]; // error message
		}else{
			$method	 = $type ? $class.$type.$func: $func;
			$args	 = $args ? self::ConvertStringFromArguments($args, $is_dump): null;
			$tail	 = "$method($args)";
			if(!empty($is_dump) ){
				$did = md5(microtime());
				$tail .= "<span class='dkey more' did='{$did}'>more...</span>";
				$tail .= "<div id='{$did}' style='display:none;'>".Dump::GetDump($backtrace['args']).'</div>';
			}
		}
		
		$info = "<tr style='font-size:small;' class='{$style}'><td>{$index}</td><td>{$file}</td><td style='text-align:right;'>{$line}</td><td>{$tail}</td></tr>".PHP_EOL;
		
		return $info.PHP_EOL;
	}
	
	static private function _getBacktrace()
	{
		$i = 0;
		$return = '<table>';
		foreach( $_SESSION[self::_NAME_SPACE_] as $error ){
			$i++;
			$name		 = $error['name'];
			$message	 = $error['message'];
			$backtraces	 = $error['backtrace'];
			$from		 = $error['translation'];
			
			//	i18n
			if( $from ){
				$temp = explode(PHP_EOL, $message.PHP_EOL);
				$message = OnePiece5::i18n()->Bulk($temp[0], $from );
				if( isset($temp[1]) ){
					$message .= trim($temp[1],'\\');
				}
			}
			
			$message = OnePiece5::Escape($message);
			
			//	Sequence no.
			$return .= "<tr><td colspan=4 class='' style='padding:0.5em 1em; color:red; font-weight: bold; font-size:small;'>Error #{$i} $message</td></tr>".PHP_EOL;
			
			if( $count = count($backtraces) ){
				foreach( $backtraces as $index => $backtrace ){
					$return .= self::_formatBacktrace( $count-$index, $backtrace, $name );
				}
			}
		}
		$return .= '</table>'.PHP_EOL;
		return $return;
	}
	
	static private function _toDisplay()
	{
		if( Toolbox::isHtml() ){
			print self::_getBacktrace();
			self::_stackErrorJs();
			Dump::PrintAttach();
		}else{
			/*
			print PHP_EOL.PHP_EOL;
			print html_entity_decode(strip_tags(self::_getBacktrace()),ENT_QUOTES);
			*/
		}
		return true;
	}
	
	static private function _stackErrorJs()
	{
		print "<script>".PHP_EOL;
		print file_get_contents(Toolbox::ConvertPath('op:/Template/StackError.js')).PHP_EOL;
		print "</script>".PHP_EOL;
	}
	
	static private function _toMail()
	{
		$from_addr = get_current_user().'@'.$_SERVER['SERVER_ADDR'];
		$from_name = 'op-core/Error';
		
		$to = Env::GetAdminMailAddress();
		$subject = '[Error] '.self::_getMailSubject();
		$message = '';
		$headers = '';
		$parameters = "-f $from_addr";
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
		$headers .= "From: ".mb_encode_mimeheader($from_name)."<$from_addr>\r\n";
		
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
	
	static function ConvertStringFromErrorNumber( $number )
	{
		/* @see http://www.php.net/manual/ja/errorfunc.constants.php */
		switch( $number ){
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
				$type = $number;
		}
		
		return $type;
	}
	
	static function ConvertStringFromArguments( $args, &$is_dump )
	{
		$is_dump = false;
		$join = array();
		foreach( $args as $temp ){
			switch( $type = strtolower(gettype($temp)) ){
				case 'boolean':
					$join[] = $temp ? 'true': 'false';
					break;
					
				case 'integer':
				case 'double':
					$join[] = $temp;
					break;
					
				case 'string':
					$join[] = "'".OnePiece5::Escape($temp)."'";
					break;
					
				case 'object':
					$is_dump = true;
					$class_name = get_class($temp);
					$join[] = $class_name;
					break;
					
				case 'array':
					$is_dump = true;
					$join[] = 'array';
					break;
					
				default:
					$join[] = $type;
					break;
			}
		}
		return join(', ',$join);
	}
	
	static function MagicMethodCall( $class, $name, $args )
	{
		//  If Toolbox method.
		if( method_exists('Toolbox', $name) and false ){
			OnePiece5::Mark("Please use Toolbox::$name");
			return Toolbox::$name(
				isset($args[0]) ? $args[0]: null,
				isset($args[1]) ? $args[1]: null,
				isset($args[2]) ? $args[2]: null,
				isset($args[3]) ? $args[3]: null,
				isset($args[4]) ? $args[4]: null,
				isset($args[5]) ? $args[5]: null
			);
		}
		
		$message = "This method does not exists in class.".PHP_EOL."\ - {$class}::{$name}\\";
		self::_Set( $name, $message, null, 'en' );
	}
	
	static function MagicMethodCallStatic( $class, $name, $args )
	{
		//	Call static is PHP 5.3.0 later
		self::MagicMethodCall( $class, $name, $args );
	}
	
	static function MagicMethodSet( $class, $name, $args, $call )
	{
		$bulk = OnePiece5::i18n()->Bulk("\{$class}::{$name}\ is not accessible property.","en");
		$message = "$bulk ({$call}, value={$args})";
		OnePiece5::StackError($message);
	}
	
	static function MagicMethodGet( $class, $name, $call )
	{
		$bulk = OnePiece5::i18n()->Bulk("\{$class}::{$name}\ is not accessible property.","en");
		$message = "$bulk ({$call})";
		OnePiece5::StackError($message);
	}
	
	static function LastError( $e )
	{
		$file	 = $e['file'];
		$line	 = $e['line'];
		$type	 = $e['type'];
		$message = $e['message'];
		
		$type = self::ConvertStringFromErrorNumber($type);
		
		OnePiece5::StackError("$file [$line] $type: $message");
	}
	
	/**
	 * This method has been transferred from OnePiece5::ErrorHandler.
	 * 
	 * @param  integer $type
	 * @param  string  $str
	 * @param  string  $file
	 * @param  integer $line
	 * @param  unknown $context
	 * @return boolean
	 */
	static function Handler( $type, $str, $file, $line, $context )
	{
		$type = self::ConvertStringFromErrorNumber($type);
		
		//  Output error message.
		$format = '%s [%s] %s: %s';
		if(empty($env['cgi'])){
			$format = '<div>'.$format.'</div>';
		}
		
		//  check ini setting
		if( ini_get( 'display_errors') ){
			printf( $format.PHP_EOL, $file, $line, $type, $str );
		}
	
		return true;
	}
	
	/**
	 * This method has been transferred from OnePiece5::ErrorExceptionHandler.
	 * 
	 * @param OpException $e
	 */
	static function ExceptionHandler( $e )
	{
		$class	 = get_class($e);
		$file	 = $e->GetFile();
		$line	 = $e->GetLine();
		$message = $e->GetMessage();
		$lang	 = null;
		
		//	get language of message's original language.
		if( method_exists($e,'getLang') ){
			$lang = $e->getLang();
		}
		
		//	to translate
		if( $lang ){
			$message = OnePiece5::i18n()->Bulk($message);
		}
		
		//	join
		$error = "$class: $file [$line] $message";
		OnePiece5::StackError($error,$lang);
	}
}
