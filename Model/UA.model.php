<?php

class Model_UA extends Model_Model
{
	//	OS
	const _BSD_			 = 'BSD';
	const _LINUX_		 = 'LINUX';
	const _WINDOWS_		 = 'WINDOWS';
	const _MAC_			 = 'MAC';
	const _IPAD_		 = 'IPAD';
	const _IPHONE_		 = 'IPHONE';
	const _ANDROID_		 = 'ANDROID';
	
	//	BROWSER
	const _IE_			 = 'IE';
	const _CHROME_		 = 'CHROME';
	const _FIREFOX_		 = 'FIREFOX';
	
	//	MOBILE CARRIER
	const _KDDI_		 = 'KDDI';
	const _DOCOMO_		 = 'DOCOMO';
	const _SOFTBANK_	 = 'SOFTBANK';
	
	//	WEB SERVER
	const _APACHE_		 = 'APACHE';
	const _NGINX_		 = 'NGINX';
	
	function GetISP()
	{
		
	}
	
	function GetOS()
	{
		//	Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:26.0) Gecko/20100101 Firefox/26.0
		$ua = $_SERVER['HTTP_USER_AGENT'];
		
		if( preg_match('/Mac OS X/i',$ua) ){
			$var = self::_MAC_OS_X_;
		}
		
		return $var;
	}
	
	function GetBrowser()
	{
		//	Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:26.0) Gecko/20100101 Firefox/26.0
		$ua = $_SERVER['HTTP_USER_AGENT'];
		
		if( preg_match('/Firefox/i') ){
			$var = self::_FIREFOX_;
		}
		
		return $var;
	}
	
	function GetWebServer($_is_version=false)
	{
		list( $software, $version ) = explode('/',$_SERVER['SERVER_SOFTWARE']);
		return $_is_version ? "$software/$version": $software;
	}
}
