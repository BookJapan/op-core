<?php

class Api extends OnePiece5
{
	private $_cookie = null;
	private $_timeout = 10;
	
	function Curl( $url )
	{
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_HEADER, true );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_COOKIE, $this->_cookie );
		curl_setopt( $ch, CURLOPT_TIMEOUT, $this->_timeout );
		
		if(!$result = curl_exec($ch)){	
			return false;
		}
		
		//	
		list($header,$body) = explode("\r\n\r\n",$result);
		
		//	Cookieの自動更新
		if( preg_match('|Set-Cookie: ([^;]+)|', $header, $match) ){
			$this->_cookie = $match[1];
		}
		
		return $body;
	}
}
