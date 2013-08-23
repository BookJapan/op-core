<?php

class Model_Blowfish extends Model_Model
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
		return $this->_blowfish->Encrypt( $data, $password );
	}
	
	function Decrypt( $string, $password=null )
	{
		return $this->_blowfish->Decrypt( $string, $password );
	}
}
