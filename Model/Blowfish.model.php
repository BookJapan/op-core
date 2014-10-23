<?php
/**
 * Blowfish.model.php
 * 
 * @author tomoaki.nagahara@gmail.com
 */
/**
 * Model_Blowfish
 * 
 * @author tomoaki.nagahara@gmail.com
 */
class Model_Blowfish extends OnePiece5
{
	/**
	 * @var Blowfish
	 */
	private $_blowfish = null;
	
	function Init()
	{
		parent::Init();
		$this->_blowfish = new Blowfish();
	}
	
	function Encrypt( $data, $password=null )
	{
		if(!$password){
			$password = $this->GetEncryptKeyword();
		}
		return $this->_blowfish->Encrypt( $data, $password );
	}
	
	function Decrypt( $string, $password=null )
	{
		return $this->_blowfish->Decrypt( $string, $password );
	}
	
	function GetEncryptKeyword()
	{
		return $this->_blowfish->GetEncryptKeyword();
	}
}
