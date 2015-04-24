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
	const _SAFARI_		 = 'SAFARI';
	
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
		$ua = $_SERVER['HTTP_USER_AGENT'];
		
		if( preg_match('/Mac OS X/i',$ua) ){
			$var = self::_MAC_OS_X_;
		}
		
		return $var;
	}
	
	function GetBrowser()
	{
		//	Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:26.0) Gecko/20100101 Firefox/26.0
		//	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2272.89 Safari/537.36
		//	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8_5) AppleWebKit/600.3.18 (KHTML, like Gecko) Version/6.2.3 Safari/537.85.12
		
		if( isset($_SERVER['HTTP_USER_AGENT']) ){
			$ua = $_SERVER['HTTP_USER_AGENT'];
		}else{
			return 'Empty';
		}
		
		if( preg_match('/Firefox/i',$ua) ){
			$var = self::_FIREFOX_;
		}else
		if( preg_match('/Chrome/i',$ua) ){
			$var = self::_CHROME_;
		}else
		if( preg_match('/Safari/i',$ua) ){
			$var = self::_CHROME_;
		}else{
			$var = "unknown ($ua)";
		}
		
		return $var;
	}
	
	function GetWebServer($_is_version=false)
	{
		list( $software, $version ) = explode('/',$_SERVER['SERVER_SOFTWARE']);
		return $_is_version ? "$software/$version": $software;
	}
}
