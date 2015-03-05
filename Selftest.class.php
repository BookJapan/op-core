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
	 * OnePiece's PDO5 object
	 * 
	 * @var PDO5
	 */
	private $_pdo;
	
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
	 * Blue print
	 * 
	 * @var Config
	 */
	private $_blueprint;
	
	private $_log;
	
	function Init()
	{
		parent::Init();
		if(!$this->Admin()){
			$this->StackError("Not admin call.");
		}
	}
	
	function InitDiagnosis()
	{
		$this->_diagnosis = new Config();
		$this->_blueprint = new Config();
		$this->_blueprint->user		 = array();
		$this->_blueprint->database	 = array();
		$this->_blueprint->database	 = array();
		$this->_blueprint->table	 = array();
		$this->_blueprint->column	 = array();
		$this->_blueprint->index	 = array();
		$this->_blueprint->alter	 = array();
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
	
	function isDiagnosis()
	{
		return $this->_is_diagnosis;
	}
	
	function GetDiagnosis()
	{
		return $this->_diagnosis;
	}
	
	function Diagnose($root=null)
	{
		$this->InitDiagnosis();
		
		//	each per config.
		foreach( $this->GetSelftestConfig() as $config ){
			//	Connection
			try{
				//	Set root user setting for Carpenter.
				$this->_blueprint->config = Toolbox::toObject($config);
				
				//	Diagnosis
				$this->CheckConnection($config);
				$this->CheckDatabase($config);
				$this->CheckTable($config);
				$this->CheckColumn($config);
				$this->CheckAlter($config);
				$this->CheckIndex($config);
				
			}catch( Exception $e ){
				$this->mark('![.red['. $e->getMessage() .']]');
				return false;
			}
		}
		
		return true;
	}
	
	function CheckConnection($config)
	{
		$database = $config->database;
		
		//	Connection
		$io	 = $this->PDO()->Connect($database);
		$dsn = $this->PDO()->GetDSN();
		$user = $database->user;
		
		//	Write diagnosis
		$this->_diagnosis->connection->$user->$dsn = $io;
		
		//	return
		if(!$io){
			//	Write blue print
			$this->_blueprint->user[] = $user;
			//	Exception
			$error = $this->FetchError();
			throw new OpException($error['message']);
		}
	}
	
	function CheckDatabase($config)
	{
		$db_name = $config->database->name;
		$db_list = $this->PDO()->GetDatabaseList($config);
		$io = in_array($db_name, $db_list);
		
		//	return
		if(!$io){
			//	Write blue print
			$this->_blueprint->database[] = clone($config->database);
			//	Exception
			throw new OpException("Database \ $db_name \ is not exists. (or deny access) ",'en');
		}
		
		
		$this->Mark($io);
		
	//	$this->D($config);
	//	$this->D($database);
	}
	
	function CheckTable($config)
	{
		
	}
	
	function CheckColumn($config)
	{
		
	}
	
	function CheckIndex($config)
	{
		
	}
	
	function CheckAlter($config)
	{
		
	}
	
	function CheckUser($config)
	{
		
	}
	
	function GetBlueprint()
	{
		return $this->_blueprint;
	}
}
