<?php

class Api extends OnePiece5
{
	private $_cookie = null;
	private $_timeout = 10;
	
	/**
	 * Curl
	 * 
	 * @param  string  $url
	 * @param  integer $expire specify of second. 0 is permanently.
	 * @return string|boolean
	 */
	function Curl( $url, $expire=-1 )
	{
		//	check cache
		if( $expire >= 0 ){
			$ckey = md5($url);
			if( $body = $this->Cache()->Get($ckey) ){
				return $body;
			}
		}
		
		//	init
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_HEADER, true );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_COOKIE, $this->_cookie );
		curl_setopt( $ch, CURLOPT_TIMEOUT, $this->_timeout );
		
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
		
		//	
		if( $expire >= 0 ){
			$this->Cache()->Set($ckey, $body, $expire);
		}
		
		return $body;
	}
}
