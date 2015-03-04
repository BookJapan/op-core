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
	
	/**
	 * Diagnosis.
	 * 
	 * @var Config
	 */
	private $_diagnosis;
	
	/**
	 * Is diagnosis.
	 * 
	 * @var boolean
	 */
	private $_is_diagnosis;
	
	/**
	 * OnePiece's PDO5 object
	 * 
	 * @var PDO5
	 */
	private $_pdo;
	
	function Init()
	{
		parent::Init();
		if(!$this->Admin()){
			$this->StackError("Not admin call.");
		}

		$this->_diagnosis = new Config();
	}
	
	function SetSelftestConfig( Config $config )
	{
		$key = md5($this->GetCallerLine());
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
	
	function GetDiagnosis()
	{
		return $this->_diagnosis;
	}
	
	function Diagnose($root=null)
	{
		//	each per config.
		foreach( $this->GetSelftestConfig() as $config ){
			//	Connection
			try {
				
				$this->CheckConnection($config->database);
				
			}catch ( Exception $e ){
				$this->mark( $e->getMessage() );
			}
		}
	}
	
	function CheckConnection($database)
	{
		//	Connection
		$io	 = $this->PDO()->Connect($database);
		$dsn = $this->PDO()->GetDSN();
		$user = $database->user;
		
		//	Write diagnosis
		$this->_diagnosis->connection->$user->$dsn = $io;
		
		//	return
		if(!$io){
			throw new OpException('failed');
		}
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
