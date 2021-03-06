<?php
/**
 * Blowfish encrypt/decrypt
 * 
 * Other language
 * http://www.schneier.com/blowfish-download.html
 * 
 * @version   1.0
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2006 (C) Tomoaki Nagahara All right reserved.
 */

if(!defined('MCRYPT_BLOWFISH')){
	print __FILE__.', '.__LINE__.'<br/>';
	return;
}

class Blowfish extends OnePiece5
{	
	private $_cipher = null;
	private $_mode   = null;
	private $_key    = null;
	private $_pad    = null;
	
	function Init()
	{
		parent::Init();
		
		if( empty($this->_cipher) ){
			$this->SetCipher();
		}
		
		if( empty($this->_mode) ){
			$this->SetMode();
		}
		
		if( empty($this->_key) ){
			$this->SetKey();
		}
		
		if( empty($this->_pad) ){
			$this->SetPad();
		}
		
		return array( $this->_cipher, $this->_mode, $this->_key, $this->_pad );
	}
	
	function SetCipher($cipher='BLOWFISH')
	{
		switch( strtoupper($cipher) ){
			default:
			case 'BLOWFISH':
				$this->_cipher = MCRYPT_BLOWFISH;
				break;
		}
	}
	
	function SetMode($mode='CBC')
	{
		switch( strtoupper($mode) ){
			case 'ECB':
				$this->_mode = MCRYPT_MODE_ECB;
				break;
				
			default:
			case 'CBC':
				$this->_mode = MCRYPT_MODE_CBC;
				break;
		}
	}
	
	function SetKeyFromFile( $path=null )
	{
		if(!$path ){
			$path = __FILE__;
		}
		$data = file_get_contents($path);
		$this->_key = pack('H*', $data);
	}
	
	function GetEncryptKeyword()
	{
		if(!$keyword = Env::Get('encrypt-keyword')){
			$keyword = Env::GetAdminMailAddress();
		}
		return $keyword;
	}
	
	function SetKeyFromString( $keyword=null )
	{
		if(!$keyword){
			$keyword = $this->GetEncryptKeyword();
		}
		$this->_key = pack('H*', bin2hex($keyword));
	}
	
	function SetKeyFromHex( $hex='04B915BA43FEB5B6' )
	{
		// Check the hexadecimal
		if(!ctype_xdigit($hex) ){
			$this->StackError("Is this hex string? ($hex). Use ConvertHex($hex) method.");
			return false;
		}
		$this->_key = pack('H*', $hex);
	}
	
	function SetKey($key=null)
	{
		if( is_null($key) ){
			$this->SetKeyFromString();
		}else if( ctype_xdigit($key) ){
			$this->SetKeyFromHex($key);
		}else if( file_exists($key) ){
			$this->SetKeyFromPath($key);
		}else if( is_string($key) ){
			$this->SetKeyFromString($key);
		}else{
			$this->SetKeyFromString();
		}
	}
	
	function GetKey()
	{
		return bin2hex($this->_key);
	}
	
	function SetPad( $pad=false )
	{
		$this->_pad = $pad ? true: false;
	}
	
	/**
	 * 
	 * @param  string  $data
	 * @return boolean|string
	 */
	function Encrypt( $data, $keyword=null )
	{
		list( $cipher, $mode, $key, $pad ) = $this->init();
		
		if(!$key and $keyword){
			$key = $keyword;
		}
		
		if( !$key ){
			$this->mark("Empty encrypt keyword.");
			return false;
		}
		
		// Padding block size
		if( $pad ){
	    	$size = mcrypt_get_block_size( $cipher, $mode );
	    	$data = $this->pkcs5_pad($data, $size);
		}
		
		//	Init rand
		srand();
		mt_srand();
		
		// Windows is only use MCRYPT_RAND.
		if( strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ){
			$RAND = MCRYPT_RAND;
		}else{
			$RAND = MCRYPT_DEV_URANDOM;
		}
		
		$ivs = mcrypt_get_iv_size($cipher,$mode);
		$iv  = mcrypt_create_iv( $ivs, $RAND );
		$bin = mcrypt_encrypt( $cipher, $key, $data, $mode, $iv );
		$hex = bin2hex($bin);
		
	    return bin2hex($iv).bin2hex($bin);
	}
	
	/**
	 * 
	 * @param  string $str
	 * @return string|Ambigous <string, boolean>
	 */
	function Decrypt( $str, $keyword=null )
	{
		if( $keyword ){
			$this->SetKey($keyword);
		}
		
		list( $cipher, $mode, $key, $pad ) = $this->init();
		
		//	required "IV"
		//list( $ivt, $hex ) = explode( '.', $str );
		
		//  head 16byte is initial vector
		$ivt = substr( $str, 0, 16 );
		$hex = substr( $str, 16 );
		
		//	check
		if( !$hex or !$ivt or !$key ){
			$this->mark("empty each.(hex=$hex, ivt=$ivt, key=$key)");
			return null;
		}
		
		//  unpack
		$bin = pack('H*', $hex);
		$iv  = pack('H*', $ivt);
	    $dec = mcrypt_decrypt( $cipher, $key, $bin, $mode, $iv );
	    
	    //  remove padding
	    if( $pad ){
	    	$size = mcrypt_get_block_size( $cipher, $mode );
			$data = $this->pkcs5_unpad($dec, $size);
		}else{
			$data = rtrim($dec, "\0");
		}
	    
	    return $data;
	}
		
	function pkcs5_pad ($text, $blocksize)
	{
	    $pad = $blocksize - (strlen($text) % $blocksize);
	    return $text . str_repeat(chr($pad), $pad);
	}

	function pkcs5_unpad($text)
	{
	    $pad = ord($text{strlen($text)-1});
	    if ($pad > strlen($text)) return false;
	    if (strspn($text, chr($pad), strlen($text) - $pad) != $pad) return false;
	    return substr($text, 0, -1 * $pad);
	}
}
