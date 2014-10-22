<?php

class Api extends OnePiece5
{
	private $_ch		 = null;
	private $_cookie	 = null;
	private $_timeout	 = 180;
	private $_ckey		 = null;
	private $_is_cache	 = null;
	
	function Init()
	{
		parent::Init();
		$this->_ch = curl_init();
	}
	
	function isCache()
	{
		return $this->_is_cache;
	}

	/**
	 * Post at xml
	 *
	 * @param  string $url
	 * @param  array  $post_data
	 * @param  string $expire
	 * @return string|boolean
	 */
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
	
	/**
	 * Post
	 * 
	 * @param  string $url
	 * @param  array  $post_data
	 * @param  string $expire
	 * @return string|boolean
	 */
	function Post( $url, $post_data, $expire=null )
	{
		//	save cache key
		if( is_numeric($expire) ){
			$this->_ckey = md5($url.','.$xml);
		}

		//	init
		$ch = $this->_ch;
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query($post_data) );
		return $this->Curl( $url, $expire );
	}
	
	/**
	 * Get
	 * 
	 * @param  string $url
	 * @param  string $expire
	 * @return string|boolean
	 */
	function Get( $url, $expire=null )
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
	//	if( is_numeric($expire) ){
		if(!is_null($expire)){
			//	check post data's cache key
			if( $this->_ckey ){
				//	use post data's cache key
				$ckey = $this->_ckey;
				$this->_ckey = null;
			}else{
				//	create cache key
				$ckey = md5($url);
			}
			
			if( $expire === false ){
				//	Delete cache if value is false.
				$this->Cache()->Delete($ckey);
			}else if( is_numeric($expire) ){
				//	If hit cache
				if( $body = $this->Cache()->Get($ckey) ){
					//	return cache
					$this->mark('Hit cache!!',__CLASS__);
					$this->_is_cache = true;
					return $body;
				}
			}else{
				$expire = 1;
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
			$this->mark("curl fail.",__CLASS__);
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
