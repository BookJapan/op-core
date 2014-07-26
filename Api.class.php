<?php

class Api extends OnePiece5
{
	private $_ch = null;
	private $_cookie = null;
	private $_timeout = 10;
	private $_ckey = null;
	
	function Init()
	{
		parent::Init();
		$this->_ch = curl_init();
	}
	
	function PostXml( $url, $xml, $expire=null )
	{
		//	save cache key
		if( is_numeric($expire) ){
			$this->_ckey = md5($url.','.$xml);
		}
		
		//	init
		$ch = $this->_ch;
		curl_setopt( $ch, CURLOPT_POST, true);
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type: application/xml'));
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
		return $this->Curl( $url, $expire );
	}

	function Post( $url, $post_data, $expire=null )
	{
		//	save cache key
		if( is_numeric($expire) ){
			$this->_ckey = md5($url.','.$xml);
		}

		//	init
		$ch = $this->_ch;
		curl_setopt( $ch, CURLOPT_POST, true );
	//	curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query($post_data) );
		return $this->Curl( $url, $expire );
	}
	
	function Get( $url, $expire=-1 )
	{
		return $this->Curl( $url, $expire );
	}
	
	/**
	 * Curl
	 * 
	 * @param  string  $url
	 * @param  integer $expire specify of second. 0 is permanently.
	 * @return string|boolean
	 */
	function Curl( $url, $expire=null )
	{
		//	check cache
		if( is_numeric($expire) ){
			//	check post data's cache key
			if( $this->_ckey ){
				//	use post data's cache key
				$ckey = $this->_ckey;
				$this->_ckey = null;
			}else{
				//	create cache key
				$ckey = md5($url);
			}
			//	If hit cache
			if( $body = $this->Cache()->Get($ckey) ){
				//	return cache
				$this->mark('Hit cache!!');
				return $body;
			}
		}
		
		//	init
		$ch = $this->_ch;
		curl_setopt( $ch, CURLOPT_URL,            $url );		//	
		curl_setopt( $ch, CURLOPT_HEADER,         true );		//	
		curl_setopt( $ch, CURLOPT_TIMEOUT,        $this->_timeout );	//	
		curl_setopt( $ch, CURLOPT_COOKIE,         $this->_cookie );		//	
		curl_setopt( $ch, CURLOPT_COOKIEJAR,      'cookie' );	//	
		curl_setopt( $ch, CURLOPT_COOKIEFILE,     'tmp' );		//	
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );		//	
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );		//	Locationヘッダを追跡
	//	curl_setopt( $ch, CURLOPT_REFERER,        "" );
	//	curl_setopt( $ch, CURLOPT_USERAGENT,      "" );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );		//	supports self certificate
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );		//	supports self certificate
		
		//	fail
		if(!$result = curl_exec($ch)){
			return false;
		}
		
		//	Separate header
		list($header,$body) = explode("\r\n\r\n",$result);
		
		//	Cookie
		if( preg_match('|Set-Cookie: ([^;]+)|', $header, $match) ){
			$this->_cookie = $match[1];
		}
		
		//	save cashe
		if( isset($ckey) ){
			$this->Cache()->Set($ckey, $body, $expire);
		}
		
		return $body;
	}
}
