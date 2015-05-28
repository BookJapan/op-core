<?php
/**
 * Env.class.php
 * 
 * @creation  2015-04-19
 * @version   1.0
 * @package   op-core
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2014 (C) Tomoaki Nagahara All right reserved.
 */

/**
 * Env
 * 
 * @creation  2014-01-22
 * @version   1.0
 * @package   op-core
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2014 (C) Tomoaki Nagahara All right reserved.
 */
class Env extends OnePiece5
{
	const _NAME_SPACE_		 = 'ONEPIECE_5_ENV';
	
	const _ADMIN_IP_ADDR_	 = 'ADMIN_IP';
	const _ADMIN_EMAIL_ADDR_ = 'ADMIN_MAIL';
	
	const _ROOT_OP_		 = 'OP_ROOT';
	const _ROOT_APP_	 = 'APP_ROOT';
	const _ROOT_DOC_	 = 'DOCUMENT_ROOT';
	
	const _SERVER_IS_LOCALHOST_	 = 'OP_IS_LOCALHOST';
	const _SERVER_IS_ADMIN_		 = 'OP_IS_ADMIN';
	
	const _KEY_LOCALE_ = 'locale';
	
	static private function _Convert( $key, $var=null )
	{
		$key = strtoupper($key);
		
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
	
	static function Bootstrap()
	{
		self::Set('mime','text/html');
		self::Set('charset','utf-8');
		
		self::_init_error();
	//	self::_init_cli();
		self::_init_admin();
		self::_init_session();
	//	self::_init_cookie();
		self::_init_locale();
		self::_init_mark_label();
	}
	
	private static function _init_error()
	{
		// Error control
		$save_level = error_reporting();
		error_reporting( E_ALL );
		ini_set('display_errors',1);
		
		//	If not an administrator.
		if(!self::isAdmin()){
			//  recovery (display_errors)
			ini_set('display_errors',0);
			//  recovery (error_reporting)
			error_reporting( $save_level );
		}
	}
	
	/*
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
	*/
	
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
		
		//	Check if admin
		if( $is_localhost ){
			$is_admin = true;
		}else if( isset($_SERVER[self::_ADMIN_IP_ADDR_]) ){
			$is_admin = $_SERVER[self::_ADMIN_IP_ADDR_] === $remote_addr ? true: false;
		}else{
			$is_admin = false;
		}
		
		//	Set to $_SERVER
		$_SERVER[self::_SERVER_IS_LOCALHOST_]	 = $is_localhost;
		$_SERVER[self::_SERVER_IS_ADMIN_]		 = $is_admin;
	}
	
	private static function _init_session()
	{
		if( self::isCLI() ){
			return;
		}
		
		//  start to session.
		if(!session_id()){
			if( headers_sent($file,$line) ){
				OnePiece5::StackError("Header has already been sent. File: {$file}, Line number #{$line}.");
			}else{
				session_start();
			}
		}
	}
	
	private static function _init_cookie()
	{
		$uniq_id = md5(microtime() + $_SERVER['REMOTE_ADDR']);
		$expire  = 60*60*24*365*10;
		OnePiece5::SetCookie(OnePiece5::_KEY_COOKIE_UNIQ_ID_, $uniq_id, $expire);
		return $uniq_id;
	}
	
	private static function _init_mark_label()
	{
		//  mark_label
		if( OnePiece5::Admin() and isset($_GET['mark_label']) ){
			$mark_label = $_GET['mark_label'];
			$mark_value = $_GET['mark_label_value'];
			Developer::SaveMarkLabelValue($mark_label,$mark_value);
			list($uri) = explode('?',$_SERVER['REQUEST_URI'].'?');
		//	header("Location: $uri");
		//	exit;
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
		if(!$locale = OnePiece5::GetCookie(self::_KEY_LOCALE_) ){
			$locale = 'ja_JP.utf-8';
		}
		
		//	Set locale
		Env::SetLocale($locale);
	}
	
	static function GetLocale()
	{
		return Env::Get(self::_KEY_LOCALE_);
	}
	
	static function SetLocale( $locale )
	{
		//	Save to cookie.
		OnePiece5::SetCookie(self::_KEY_LOCALE_, $locale);
		
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
		$_SERVER[self::_NAME_SPACE_][self::_KEY_LOCALE_] = $locale;
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
		
		//	set timezone
		ini_set('date.timezone',$timezone);
	}
	
	static function GetLocaleValue()
	{
		$lang = Env::Get('lang');
		$area = Env::Get('area');
		
		//	detect order value
		switch($lang){
			case 'en':
				$codes[] = 'UTF-8';
				$codes[] = 'ASCII';
				break;
				
			case 'ja':
				$codes[] = 'eucjp-win';
				$codes[] = 'sjis-win';
				$codes[] = 'UTF-8';
				$codes[] = 'ASCII';
				$codes[] = 'JIS';
				break;
			default:
			$this->StackError("Does not define this language code. ($lang)",'en');
		}
		
		/**
		 * timezone list
		 * @see http://jp2.php.net/manual/ja/timezones.php
		 */
		switch($area){
			case 'US':
				$timezone = 'America/Chicago';
				break;
				
			case 'JP':
				$timezone = 'Asia/Tokyo';
				break;
			default:
			$this->StackError("Does not define this country code. ($lang)",'en');
		}
		
		return array( $codes, $timezone );
	}
	
	static function isAdmin()
	{
		return isset($_SERVER[self::_SERVER_IS_ADMIN_]) ? $_SERVER[self::_SERVER_IS_ADMIN_]: null;
	}

	static function isCLI()
	{
		return false;
	}
	
	static function SetAdminIpAddress($var)
	{
		$_SERVER[self::_NAME_SPACE_][self::_ADMIN_IP_ADDR_] = $var;
		if( $_SERVER[self::_SERVER_IS_LOCALHOST_] ){
			$io = true;
		}else{
			$io = $_SERVER['REMOTE_ADDR'] === $var ? true: false;
		}
		$_SERVER[self::_SERVER_IS_ADMIN_] = $io;
		
		self::_init_error();
		self::_init_mark_label();
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
	
	static function UniqID()
	{
		if(!$uniq_id = OnePiece5::GetCookie(OnePiece5::_KEY_COOKIE_UNIQ_ID_)){
			$uniq_id = self::_init_cookie();
		}
		return $uniq_id;
	}
	
	static function Get( $key )
	{
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
		//	Convert
		list( $key, $var ) = self::_Convert( $key, $var );
		
		//	Reset admin flag.
		if( $key === self::_ADMIN_IP_ADDR_ ){
			return self::SetAdminIpAddress($var);
		}
		
		//	Admin's E-Mail
		if( $key === self::_ADMIN_EMAIL_ADDR_ ){
			return self::SetAdminMailAddress($var);
		}
		
		//	Set locale.
		if( $key === strtoupper(self::_KEY_LOCALE_) ){
			return self::SetLocale($var);
		}
		
		//	Set
		$_SERVER[self::_NAME_SPACE_][$key] = $var;
	}
	
	/**
	 * @see http://jp.php.net/manual/ja/function.register-shutdown-function.php
	 */
	static function Shutdown()
	{
		if( $error = error_get_last()){
			Error::LastError($error);
		}
		
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
		
		// Session reset
		if( Toolbox::isLocalhost() and Toolbox::isHtml() ){
			$rand = rand( 0, 1000);
			if( 1 == $rand ){
				$_SESSION = array();
				$message = OnePiece5::i18n()->Bulk('\OnePiece5\ did initialize the \SESSION\.');
				print "<script>alert('$message');</script>";
			}
		}
		
		//	Output shutdown label
		switch( $mime = Toolbox::GetMIME(true) ){
			case 'json':
				//	json
				$label = null;
				break;
				
			case 'plain':
				if( Env::Get('cli') ){
					$label = ' -- OnePiece is shutdown -- ';
				}else{
					//	json
					$label = null;
				}
				break;
				
			case 'css':
			case 'javascript':
			case 'csv':
				$label = ' /* OnePiece is shutdown */ ';
				break;
				
			case 'html':
			default:
				$label = "<OnePiece />";
				
				//	Developer
				if( OnePiece5::Admin() ){
					Developer::PrintGetFlagList();
				}
				break;
		}
		print PHP_EOL.$label.PHP_EOL;
	}
}
