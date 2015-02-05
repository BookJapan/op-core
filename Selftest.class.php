<?php
# vim: ts=4:sw=4:tw=80
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
	const _KEY_SELFTEST_ = 'SELFTEST_CONFIG';
	
	function Set()
	{
		
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
	
	function Execute()
	{
		//	each per config.
		foreach( $this->GetSelftestConfig() as $config ){
			//	Connection
			$this->PDO()->Connect($config->database);
		}
	}
}
