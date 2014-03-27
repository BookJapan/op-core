<?php

class Model_Blowfish /* extends Model_Model */
{
	/**
	 * @var Blowfish
	 */
	private $_blowfish = null;
	
	function Encrypt( $data, $password=null )
	{
		if(!$this->_blowfish){
			$this->_blowfish = new Blowfish();
		}
		return $this->_blowfish->Encrypt( $data, $password );
	}
	
	function Decrypt( $string, $password=null )
	{
		if(!$this->_blowfish){
			$this->_blowfish = new Blowfish();
		}
		return $this->_blowfish->Decrypt( $string, $password );
	}
	
	function GetEncryptKeyword()
	{
		if(!$this->_blowfish){
			$this->_blowfish = new Blowfish();
		}
		return $this->_blowfish->GetEncryptKeyword();
	}
}
