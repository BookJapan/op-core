<?php
/**
 * Selftest.class.php
 * 
 * Creation: 2014-09-16
 * 
 * @version   1.0
 * @package   op-core
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2014 (C) Tomoaki Nagahara All right reserved.
 */

/**
 * Selftest
 * 
 * Creation: 2014-09-16
 *
 * @version   1.0
 * @package   op-core
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2014 (C) Tomoaki Nagahara All right reserved.
 */
class Selftest extends OnePiece5
{
	/**
	 * 
	 */
	const _KEY_SELFTEST_ = 'SELFTEST_CONFIG';
	
	function Init()
	{
		parent::Init();
		if(!$this->Admin()){
			$this->StackError("Not admin call.");
		}
	}
	
	function SetSelftestConfig( $key, Config $config )
	{
		$var = $this->GetSession(self::_KEY_SELFTEST_);
		$var[$key] = $config;
		$this->SetSession(self::_KEY_SELFTEST_, $var);
	}
	
	function GetSelftestConfig()
	{
		return $this->GetSession(self::_KEY_SELFTEST_);
	}
	
	function ClearSelftestConfig()
	{
		$this->SetSession(self::_KEY_SELFTEST_, null);
	}
	
	function Execute()
	{
		//	each per config.
		foreach( $this->GetSelftestConfig() as $config ){
			//	Connection
			$this->PDO()->Connect($config->database);
		}
	}
	
	function CheckConnection()
	{
		
	} 
	
	function CheckDatabase()
	{
		
	}
	
	function CheckTable()
	{
		
	}
	
	function CheckColumn()
	{
		
	}
	
	function CheckIndex()
	{
		
	}
	
	function CheckAlter()
	{
		
	}
	
	function CheckUser()
	{
		
	}
	
	function GetReceipt()
	{
		
	}
	
	function GetBlueprint()
	{
		
	}
}
