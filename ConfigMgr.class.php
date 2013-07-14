<?php

abstract class ConfigMgr extends OnePiece5
{
	protected $config;
	protected $_init_pdo;
	
	protected function Set( $key, $var )
	{
		$this->config->$key = $var;
	}
	
	protected function Get( $key )
	{
		return isset($this->config->$key) ? $this->config->$key: null;
	}
	/* 
	function config()
	{
		$this->mark('Your misstake','misstake'); // TODO: Whta is this?
		return $this;
	}
	 */
	function init($config=null)
	{
		parent::Init();
		
		//	Init config
		$this->config = new Config();
		
		//	Init database
		$this->config->database->driver   = 'mysql';
		$this->config->database->host     = 'localhost';
		$this->config->database->database = 'onepiece';
		$this->config->database->user     = 'onepiece';
		$this->config->database->password = md5( OnePiece5::GetEnv('admin-mail') . get_class($this) );
		$this->config->database->charset  = 'utf8';
		$this->config->database->prefix   = 'op';
	}
	
	function pdo($name=null)
	{ 
		if(!$this->_init_pdo){
			$config = $this->database();
			parent::pdo()->Connect($config);
			$this->_init_pdo = true;
		}
		return parent::pdo($name);
	}
	
	function form_prefix( $value=null )
	{
		//  set
		if( $value ){
			$this->config->form->prefix = $value;
		}
		
		//  check
		if( !isset($this->config->form->prefix) ){
			return null;
		}
		
		if( $this->config->form->prefix ){
			$prefix = $this->config->form->prefix . '_';
		}else{
			$prefix = '';
		}
		
		return $prefix;
	}
	
	function form_name( $key, $value=null )
	{
		//  check
		if(!$key){
			$this->StackError("key name is empty");
			return false;
		}
		
		//  set
		if( $value ){
			$this->config->form->$key = $value;
		}
		
		//  get prefix
		$prefix = $this->form_prefix();
		
		//  check
		if( !isset($this->config->form->$key) ){
			$this->d( Toolbox::toarray($this->config->form) );
			$this->StackError("Does not set this key's form name. (key=$key)");
			return false;
		}
		
		return $prefix.$this->config->form->$key;
	}
	
	function SetTablePrefix( $value )
	{
		$this->config->database->prefix = $value;
	}
	
	function GetTablePrefix()
	{
		return isset($this->config->database->prefix) ? $this->config->database->prefix: null;
	}
	
	function table_prefix( $value=null )
	{
		if( $value ){
			$this->config->database->prefix = $value;
		}
		return isset($this->config->database->prefix) ? $this->config->database->prefix: null;
	}
	
	function GetTableName( $label )
	{
		return $this->config->database->prefix .'_'. $label; 
	}
	
	/**
	 * 
	 * @param  string $key   This is table label.
	 * @param  string $value What is this?
	 * @return boolean|string
	 */
	function table_name( $key, $value=null )
	{
		if(!$key){
			$this->StackError("key name is empty");
			return false;
		}
		
		if( $value ){
			$this->config->database->table->$key = $value;
		}
		
		$prefix = isset($this->config->database->prefix) ? $this->config->database->prefix.'_': null;
		$table  = isset($this->config->database->table->$key) ? $this->config->database->table->$key: null;
		
		if(!$table){
			$this->StackError("Does not set this key's table namel. (key=$key)");
			return false;
		}
		
		return $prefix.$table;;
	}
	
	function GetDatabaseConfig()
	{
		return $this->config->database;
	}
	
	static function Database()
	{
		$config = new Config();
		$config->driver   = 'mysql';
		$config->host     = 'localhost';
		$config->database = 'onepiece';
		$config->user     = 'onepiece';
		$config->password = md5(OnePiece5::GetEnv('admin-mail'));
		$config->charset  = 'utf8';
		return $config;
	}
	
	static function Column()
	{
		$config = new Config();
		
		//  Created date time
		$column_name = 'created';
		$config->{$column_name}->name = $column_name;
		$config->{$column_name}->type = 'datetime';
		$config->{$column_name}->null = true;
		
		//  Updated date time
		$column_name = 'updated';
		$config->{$column_name}->name = $column_name;
		$config->{$column_name}->type = 'datetime';
		$config->{$column_name}->null = true;
		
		//  Deleted date time
		$column_name = 'deleted';
		$config->{$column_name}->name = $column_name;
		$config->{$column_name}->type = 'datetime';
		$config->{$column_name}->null = true;

		//  timestamp
		$column_name = 'timestamp';
		$config->{$column_name}->name = $column_name;
		$config->{$column_name}->type = 'timestamp';
		
		return $config;
	}
	
	function insert( $table_name=null )
	{
		$config = new Config();
		$config->table = $table_name;
		$config->set->created    = gmdate('Y-m-d H:i:s');
		$config->update->updated = gmdate('Y-m-d H:i:s');
		return $config;
	}
	
	function select( $table_name=null )
	{
		//	Avoid of ambiguous.
		if( $table_name ){
			if( $pos = strpos( $table_name, '=' ) ){
				//  Join table
				foreach( explode('=',$table_name) as $temp ){
					list( $name, $column ) = explode('.',$temp);
					//  perse　table name
					$tables[] = trim($name);
				}
				foreach( $tables as $name ){
					$deleteds[] = "$name.deleted";
				}
			}else{
				//  Single table
				$deleteds[] = isset($table_name) ? "$table_name.deleted": 'deleted';
			}
		}else{
			$deleteds = array();
		}
		
		//	Create select config.
		$config = new Config();
		$config->table = $table_name;
		//	Avoid of ambiguous.
		foreach( $deleteds as $deleted ){
			$config->where->$deleted = null;
		}
		$config->cache = 1;
		
		return $config;
	}
	
	function update( $table_name=null )
	{
		$config = new Config();
		$config->table = $table_name;
		$config->set->updated = gmdate('Y-m-d H:i:s');
		$config->limit = 1;
		return $config;
	}
	
	function delete( $table_name=null ){
		$config = new Config();
		$config->table = $table_name;
		$config->set->deleted = gmdate('Y-m-d H:i:s');
		return $config;
	}

	function GenerateFormFromDatabase( $struct, $record=null )
	{
		//  init form config
		$config = new Config();
		
		//  
		foreach( $struct as $name => $column ){
			
			//  skip column
			if( $name==='created' or $name==='deleted' or $name==='updated' or $name==='timestamp'){
				continue;
			}
			
			//  init input
			$input = new Config();
			
			//  if auto increment column
			if( $column['extra'] == 'auto_increment' ){
				if( isset($record[$name]) ){
					$input->readonly = true;
					$input->disable  = true;
				}else{
					continue;
				}
			}
			
			//  type
			switch($type = $column['type']){
				case 'int':
					$input->type  = 'text';
					$input->convert = 'zen->han';
					$input->validate->permit  = 'numeric';
					break;
						
				case 'char':
					$input->type  = 'text';
					break;
						
				case 'varchar':
					$input->type  = 'text';
					break;
						
				case 'text':
					$input->type  = 'textarea';
					break;
						
				case 'enum':
					$input->type  = 'radio';
					break;
						
				case 'set':
					$input->type  = 'checkbox';
					break;
						
				case 'tinyint':
					$input->type  = 'checkbox';
					$input->value = 1;
					break;
						
				case 'date':
					$input->type = 'date';
					$input->validate->permit = 'date';
					break;
						
				case 'datetime':
				case 'timestamp':
					$input->type = 'datetime';
					$input->validate->permit = 'datetime';
					break;
				default:
					$input->type = $column['type'];
			}
			//$this->mark($type);
			
			//  name
			$input->name = $name;
			
			//  id
			$input->id   = $name;
			
			//  class
			$input->class = $input->type;
			
			//  required
			$input->validate->required = $column['null'] == 'NO' ? true: false;
			
			//  default value
			$input->value = isset($record[$name]) ? $record[$name]: null;
			
			//  add input
			$config->input->$name = $input;
		}
		
		//  added submit button
		$input = new Config();
		$input->name  = 'submit_button';
		$input->type  = 'submit';
		$input->value = ' Submit ';
		$input->lable = '';
		$config->input->submit_button = $input;
		
		//$this->d( Toolbox::toArray($config) );
		return $config;
	}
	
	function InputDecorate()
	{
		$nl = $this->GetEnv('nl');
		$config  = $nl;
		$config .= '<div class="tr">'.$nl;
		$config .= '    <div class="td top right">%s　</div>'.$nl;
		$config .= '    <div class="td">'.$nl;
		$config .= '        %s'.$nl;
		$config .= '        <p>%s</p>'.$nl;
		$config .= '    </div>'.$nl;
		$config .= '</div>'.$nl;
		return $config;
	}
	
	function Wiki2Options()
	{
		$config = new Config();
		$config->id    = false;
		$config->tag   = false;
		$config->class = true;
		$config->style = false;
		return $config;
	}
	
	function Selftest()
	{
		$config = new Config();
		
		//	Form
		$config->form->title   = 'Wizard Magic';
		$config->form->message = 'Please enter root(or alter) password.';
		
		//	Database
		$config->database = $this->Database();
		
		return $config;
	}
}
