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
	private $_is_diagnosis = null;
	
	/**
	 * Blue print
	 * 
	 * @var Config
	 */
	private $_blueprint;
	
	/**
	 * Root user's name.
	 * 
	 * @var string
	 */
	private $_root_user;
	
	/**
	 * Root user's password.
	 * 
	 * @var string
	 */
	private $_root_password;
	
	/**
	 * Stack log.
	 *
	 * @var array
	 */
	private $_log;
	
	function Init()
	{
		parent::Init();
		if(!$this->Admin()){
			$this->StackError("Not admin call.");
		}
		$this->_log('Init');
	}
	
	function InitDiagnosis()
	{
		$this->_diagnosis = new Config();
		$this->_blueprint = new Config();
		$this->_blueprint->grant	 = array();
		$this->_blueprint->user		 = array();
		$this->_blueprint->database	 = array();
		$this->_blueprint->table	 = array();
		$this->_blueprint->column	 = array();
		$this->_blueprint->index	 = array();
		$this->_blueprint->alter	 = array();
	}
	
	private function _Log($message, $result=null)
	{
		//	Generate log array.
		$log = array();
		$log['result']	 = $result;
		$log['message']	 = $message;
		
		//	Stack log array.
		$this->_log[] = $log;
	}
	
	function PrintLog()
	{
		$i = 0;
		while($log = array_shift($this->_log)){
			$i++;
			$result = $log['result'];
			$message = $log['message'];
			if( $result === null ){
				$class = 'gray';
			}else if( is_bool($result) ){
				$class = $result ? 'blue': 'red';
			}else{
				$class = $result;
			}
			print $this->P("![.small[ ![.{$class}[{$i}: {$message}]] ]]");
		}
	}
	
	function Root($password, $root_user='root')
	{
		$this->_log('Root');
		$this->_root_user     = $root_user;
		$this->_root_password = $password;
	}
	
	private $_selftest_config;
	
	function SetSelftestConfig( Config $config )
	{
		$this->_selftest_config[] = clone($config);
	}
	
	function GetSelftestConfig()
	{
		return $this->_selftest_config;
	}
	
	function ClearSelftestConfig()
	{
		$this->_selftest_config = null;
	}
	
	function GetBlueprint()
	{
		return $this->_blueprint;
	}
	
	function GetDiagnosis()
	{
		return $this->_diagnosis;
	}
	
	function isDiagnosis()
	{
		if( $this->_root_user ){
			$io = false;
		}else{
			$io = $this->_is_diagnosis;
		}
		return $io;
	}
	
	function Diagnose($root=null)
	{
		$this->InitDiagnosis();
		
		//	each per config.
		foreach( $this->GetSelftestConfig() as $config ){
			
			//	Set root user setting for Carpenter.
			$this->_blueprint->config = Toolbox::toObject($config);
			
			//	Diagnosis
			$this->CheckConnection($config);
			$this->CheckDatabase($config);
			$this->CheckTable($config);
			$this->CheckColumn($config);
			$this->CheckAlter($config);
			$this->CheckIndex($config);
		}
		
		return $this->_is_diagnosis;
	}
	
	function CheckConnection($config)
	{
		//	Database connection config.
		$database = clone($config->database);
		
		//	Set root and password..
		if( $this->_root_user ){
			$database->user     = $this->_root_user;
			$database->password = $this->_root_password;
		}
		
		//	Connection
		$io	 = $this->PDO()->Connect($database);
		$dsn = $this->PDO()->GetDSN();
		$user = $database->user;
		
		//	Write diagnosis
		$this->_diagnosis->connection->$user->$dsn = $io;
		
		//	Log
		$this->_log("DSN: $dsn, user: $user",$io);
		
		//	return
		if(!$io){
			$this->_blueprint->user[] = $user;
			//	Error
			$error = $this->FetchError();
			$this->_log($error['message'],$io);
		}
	}
	
	function CheckDatabase($config)
	{
		if( $io = $this->PDO()->isConnect() ){
			$db_name = $config->database->name;
			$db_list = $this->PDO()->GetDatabaseList($config);
			$io = in_array($db_name, $db_list);
		}
		
		//	return
		if(!$io){
			$this->_is_diagnosis = false;
			$this->_blueprint->database[] = clone($config->database);
		}
	}
	
	function CheckTable($config)
	{
		if(!$io = $this->PDO()->isConnect() ){
			//	Failed database connection.
			$this->_is_diagnosis = false;
			foreach( $config->table as $name => $table ){
				$table->name = $name;
				$this->CheckGrant($config->database, $table);
			}
		}else{
			//	Check each table.
			$table_list = $this->PDO()->GetTableList($config->database->name);
			$this->D($table_list);
			
			foreach($table_list as $table_name){
				$table = new Config();
				$table->name = $table_name;
				$this->_blueprint->table[] = $table;
			}
		}
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
	
	function CheckUser($database)
	{
		
	}
	
	/**
	 * Grant to each user by table.
	 * 
	 * @param Config $database Database connection information.
	 * @param Config $table    Table define.
	 */
	function CheckGrant( $database, $table )
	{
		//	Generate grant config.
		$grant = new Config;
		$grant->host	 = $database->host;
		$grant->database = $database->name;
		$grant->table	 = $table->name;
		$grant->user	 = $database->user;
		$grant->password = $database->password;
		$grant->privilege = isset($table->privilege) ? $table->privilege: 'select,insert,update';
		
		//	Stack grant config.
		$this->_blueprint->grant[] = $grant;
	}
	
}
