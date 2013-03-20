<?php

class Model_OpUser extends Model_Model
{
	const KEY_SESSION_USER_ID = 'op_user_id';
	const KEY_COOKIE_UA_ID    = 'op_ua_id';
	
	private $isFirstVisit = false;
	private $isReVisit    = false;
	
	function Init($config=null)
	{
		parent::Init($config);
		$this->InitOpUser();
	//	$this->InitOpUserInfo();
	//	$this->InitOpUserAgent();
	}
	
	function Config($name='ConfigOpUser')
	{
		return parent::Config($name);
	}
	
	function Selftest()
	{
		$config = ConfigOpUser::Selftest();
		$wz = new Wizard();
		$wz->selftest($config);
	}
	
	function InitOpUser()
	{
		//  Already init.
		if( $user_id = $this->GetSession( self::KEY_SESSION_USER_ID ) ){
			return true;
		}
		
		//  Select op_user record.
		$select = $this->config()->select_user();
		$record = $this->pdo()->select($select);
		
		if( isset($record['user_id']) ){
			//  Re-visit
			$this->isReVisit = true;
			
			//  Already registration
			$user_id = $record['user_id'];
		}else{
			//  first-visit
			$this->isFirstVisit = true;
			
			//  User registration for first time visitor.
			$insert  = $this->config()->insert_user();
			$user_id = $this->pdo()->insert($insert);
			
			//  Save user agent (Op-User-ID is Browser(cookie) related.)
			$this->InitOpUserAgent();
		}
		
		//  Update user info.
		$this->InitOpUserInfo();
		
		//  Save op_user.user_id to session. (Init complete)
		$this->SetSession(self::KEY_SESSION_USER_ID, $user_id );
		
		return true;
	}
	
	function InitOpUserInfo()
	{
		//  First visit
		if( $this->isFirstVisit ){
			$insert = $this->config()->insert_user_info();
			$this->pdo()->insert($insert);
		}
		
		// Re visit
		if( $this->isReVisit ){
			$update = $this->config()->update_user_info();
			$this->pdo()->update($update);
		}
	}
	
	function InitOpUserAgent()
	{
		//  Save to user agent.
		$insert = $this->config()->insert_user_agent( self::TABLE_OP_USER_AGENT );
		$op_ua_id = $this->pdo()->insert($insert);
		
		//  If error occourd.
		if(!$op_ua_id){
			$op_ua_id = -1;
		}
		
		//  Save user agent to cookie.
		$this->SetCookie( self::KEY_COOKIE_UA_ID, $op_ua_id );
		
		return $op_ua_id;
	}
	
	function GetOpUserId()
	{
		return $this->GetSession( self::KEY_SESSION_USER_ID );
	}

	function GetOpUaId()
	{
		return $this->GetCookie( self::KEY_COOKIE_UA_ID );
	}
	
	function GetNickName()
	{
		return 'guest';
	}

	function SetMessage( $message )
	{
		$this->message = $message;
		return null;
	}
	
	function GetMessage()
	{
		return null;
	}
	
	/**
	 * All in one.
	 * 
	 * @return Config
	 */
	function Get()
	{
		$data = new Config();
		$data->op_user_id = $this->GetOpUserId();
		$data->nickname   = $this->GetNickName();
		$data->message    = $this->GetMessage();
		return $data;
	}
}

class ConfigOpUser extends ConfigModel
{
	const TABLE_OP_USER			 = 'op_user';
	const TABLE_OP_USER_INFO	 = 'op_user_info';
	const TABLE_OP_USER_AGENT	 = 'op_user_agent';
	
	static function database()
	{
		$config = parent::database();
		$config->user     = 'op_model_opuser';
		return $config;
	}
	
	function select_user()
	{
		$config = parent::select( self::TABLE_OP_USER );
		$config->where->op_uniq_id = $this->GetCookie( OnePiece5::KEY_COOKIE_UNIQ_ID);
		$config->limit = 1;
		return $config;
	} 
	
	function insert_user()
	{
		$config = parent::insert( self::TABLE_OP_USER );
		$config->set->op_uniq_id = $this->GetCookie( OnePiece5::KEY_COOKIE_UNIQ_ID );
		return $config;
	}
	
	function insert_user_info()
	{
		$config = parent::insert( self::TABLE_OP_USER_INFO );
		$insert->set->user_id = $this->GetSession( OnePiece5::KEY_SESSION_USER_ID );
		return $config;
	}
	
	function insert_user_agent()
	{
		//  Get user agent.
		$ua  = $_SERVER['HTTP_USER_AGENT'];
		$md5 = md5($ua);
		
		//  Config
		$config = parent::insert( self::TABLE_OP_USER_AGENT );
		$config->set->user_agent     = $ua;
	//	$config->set->user_agent_md5 = $md5;
		return $config;
	}

	function update_user_info()
	{
		$config = parent::update( self::TABLE_OP_USER_INFO );
		$config->set->visits = '+1';
		$config->where->user_id = $this->GetSession( Model_OpUser::KEY_SESSION_USER_ID );
		$config->limit = 1;
		return $config;
	}
	
	static function Selftest()
	{
		//  Get config
		$config = new Config();
		$config->database = self::database();
		
		//  Tables (op_user)
		$table_name = 'op_user';
		$config->table->{$table_name}->table   = $table_name;
		$config->table->{$table_name}->comment = 'This is wizard test.';
			
			//  Columns
			$column_name = 'user_id';
			$config->table->{$table_name}->column->{$column_name}->name = $column_name;
			$config->table->{$table_name}->column->{$column_name}->ai   = true;
			
			$column_name = 'op_uniq_id';
			$config->table->{$table_name}->column->{$column_name}->name = $column_name;
			$config->table->{$table_name}->column->{$column_name}->type = 'text';
			
			//  created, updated, deleted
			$config->table->{$table_name}->column->merge(parent::Column());

			
		//  Tables (op_user_info)
		$table_name = 'op_user_info';
		$config->table->{$table_name}->table   = $table_name;
		$config->table->{$table_name}->comment = 'This is wizard test.';
			
			//  Primary ID
			$column_name = 'user_id';
			$config->table->{$table_name}->column->{$column_name}->name = $column_name;
			$config->table->{$table_name}->column->{$column_name}->ai   = true;
			
			//  Visit frequency.
			$column_name = 'visits';
			$config->table->{$table_name}->column->{$column_name}->name = $column_name;
			$config->table->{$table_name}->column->{$column_name}->type = 'int';
			
			//  Messages
			$column_name = 'message';
			$config->table->{$table_name}->column->{$column_name}->name = $column_name;
			$config->table->{$table_name}->column->{$column_name}->type = 'text';
			
			//  created, updated, deleted
			$config->table->{$table_name}->column->merge(parent::Column());
			

		//  Tables (op_user_agent)
		$table_name = 'op_user_agent';
		$config->table->{$table_name}->table   = $table_name;
		$config->table->{$table_name}->comment = 'This is wizard test.';
			
			//  Columns
			$column_name = 'user_id';
			$config->table->{$table_name}->column->{$column_name}->name = $column_name;
			$config->table->{$table_name}->column->{$column_name}->ai   = true;
			
			$column_name = 'user_agent';
			$config->table->{$table_name}->column->{$column_name}->name = $column_name;
			$config->table->{$table_name}->column->{$column_name}->type = 'text';
			
			//  created, updated, deleted
			$config->table->{$table_name}->column->merge(parent::Column());
			
		return $config;
	}
}
