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
class Blowfish
{	
	private $_cipher = null;
	private $_mode   = null;
	private $_key    = null;
	private $_pad    = null;
	
	function Init()
	{
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
	
	function SetKeyFromString( $keyword=null )
	{
		if(!$keyword){
			$keyword = OnePiece5::GetEnv('admin-mail');
		}
		$this->_key = pack('H*', bin2hex($keyword));
	}
	
	function SetKeyFromHex( $hex='04B915BA43FEB5B6' )
	{
		//if( preg_match('/[^0-9a-f]+/i', $hex, $match) ){
		if(!ctype_xdigit($hex) ){ // Check the hexadecimal
			$this->StackError("Is this hex string? ({$match[0]}). Use ConvertHex($hex) method.");
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
			$this->StackError("Does not initialized secret key.");
			return false;
		}
		
		// Padding block size
		if( $pad ){
	    	$size = mcrypt_get_block_size( $cipher, $mode );
	    	$data = $this->pkcs5_pad($data, $size);
		}
		
		srand(); mt_srand() ;
		$ivs = mcrypt_get_iv_size($cipher,$mode);
		$iv  = mcrypt_create_iv( $ivs, MCRYPT_RAND ); // Windows is only MCRYPT_RAND.
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
			$this->StackError("empty each.(hex=$hex, ivt=$ivt, key=$key)");
			return '';
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
