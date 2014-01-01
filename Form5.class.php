<?php
/**
 * Form5
 *
 * @version   1.0
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2009 (C) Tomoaki Nagahara All right reserved.
 */
class Form5 extends OnePiece5
{
	private	$status;
	private $config;
	private	$session;
	private $_log = null;
	
	function __destruct()
	{
		//	Log
		if( $this->_log ){
			$this->_log[] = __METHOD__;
			$this->d( $this->_log ); 
		}
		
		//	Destruct
		parent::__destruct();
	}
	
	function Init()
	{
		//	Log
		if( $this->_log ){ $this->_log[] = __METHOD__; }
		
		parent::Init();
		$this->status = new Config();
		$this->config = new Config();
		
		//	Session-ID is regenerate.
		if( headers_sent( $file, $line ) ){
			$this->mark("skip session regenerate. headers is already sent.($file, $line)");
		}else{
			$history = $this->GetSession('history');
			$history[] = $_SERVER['REQUEST_URI'].', '.date('H:i:s');
			$this->SetSession('history', $history);
		//	$io = session_regenerate_id(true);
		}
	}
	
	private function GetRequest( $input_name, $form_name )
	{
		//  Get form config.
		if(!$form = $this->GetConfig( $form_name )){
			return false;
		}
		
		//  Check input config.
		if(!is_null($input_name) ){
			if(!isset($form->input->$input_name) ){
				$this->StackError("Does not exists input config. ($form_name, $input_name)");
				return false;
			}else{
				$input = $form->input->$input_name;
			}
		}
		
		//  If case of file upload.
		if( isset($_FILES[$input_name]) ){
		//	$this->d( $_FILES[$input_name] );
			
			if( $_FILES[$input_name]['size'] == 0 ){
				$request = null;
			}else{
				$request = $_FILES[$input_name]['name']."({$_FILES[$input_name]['type']})";
			}
			
		}else{
			$request = Toolbox::GetRequest( $input_name, $form->method );
		}
		
		//  charset
		$charset = isset($form->charset) ? $form->charset: $this->GetEnv('charset');
				
		//  convert
		if(is_null($input_name)){
			//  many
			foreach( $form->input as $input_name => $input ){
				$value = &$request[$input_name];
				if( is_null($value) ){
					continue;
				}
				if( isset($input->replace) ){
//					$value = $this->CheckReplace( $value, $input->replace, $charset );
				}
				if( isset($input->convert) ){
					$value = $this->CheckConvert( $value, $input->convert, $charset );
				}
			}
		}else{
			// single
			if( !is_null($request) and isset($input->replace) ){
//				$request = $this->CheckReplace( $request, $input->replace, $charset );
			}
			if( !is_null($request) and isset($input->convert) ){
				$request = $this->CheckConvert( $request, $input->convert, $charset );
			}
		}
		
		return $request;
	}
	
	private function SetRequest( $value, $input_name, $form_name )
	{
		if(!$this->CheckConfig( $form_name, $input_name )){
			return false;
		}
		
		return Toolbox::SetRequest( $value, $input_name, $this->config->$form_name->method );
	}
	
	/*******************************************************************************/
	
	public function Secure( $form_name )
	{
		//	Log
		if( $this->_log ){ $this->_log[] = __METHOD__ .', '. $this->status->$form_name->message; }
		
		if(!$this->CheckConfig( $form_name )){
			return false;
		}
		
		return 'secure' == $this->status->$form_name->message ? true: false;
	}
	
	public function GetStatus( $form_name )
	{
		if(!isset($form_name)){
			$this->mark('Form name is required to GetStatus($form_name).');
			return false;
		}
		
		if(!isset($this->config)){
			$this->StackError("Form config has not been initialized yet.");
			return false;
		}
		
		if(!isset($this->config->$form_name)){
			$this->StackError("Form '$form_name' has not been initialized yet.");
			return false;
		}
		
		return $this->status->$form_name->message;
	}
	
	private function SetStatus( $form_name, $message )
	{
		if(!$this->CheckConfig( $form_name )){
			return false;
		}
		
		if(!isset($this->status->$form_name->stack)){
			$this->status->$form_name->stack = array();
		}
		
		$this->status->$form_name->message = $message;
		$this->status->$form_name->stack[] = $message;
	}
	
	/*******************************************************************************/
	
	private function GetTokenKeyName( $form_name )
	{
		return md5( $form_name . $_SERVER['SERVER_ADDR'] . $_SERVER['REMOTE_ADDR'] );
	}
	
	private function GenerateTokenKey( $form_name )
	{	
		//	Create Token-key
		$token_key = md5( $form_name . microtime() . $_SERVER['REMOTE_ADDR'] );

		//	Log
		if( $this->_log ){ $this->_log[] = __METHOD__.", $form_name, $token_key"; }
		
		//	Set new token-key
		$this->SetTokenKey($form_name, $token_key);
	}
	
	private function SetTokenKey( $form_name, $token_key )
	{
		if(!$this->CheckConfig( $form_name )){
			return false;
		}
		
		//	Get token table
		$token = $this->GetSession('token');
		
		//	Log
		if( $this->_log ){ $this->_log[] = __METHOD__." | Current Token key: $token_key"; }
		
		//	Save new token
		$token[$form_name] = $token_key;
		$this->SetSession('token',$token);

	}
	
	private function GetTokenKey( $form_name )
	{
		if(!$this->CheckConfig( $form_name )){
			return false;
		}
		
		$token = $this->GetSession('token');
		$token_key = isset($token[$form_name]) ? $token[$form_name]: null;
		
		//	Log
		if( $this->_log ){ $this->_log[] = __METHOD__." | Current Token key: $token_key"; }
		
		return $token_key;
	}
	
	const STATUS_VISIT_FIRST       = '1st visit';
	const STATUS_SESSION_DESTORY   = 'session is destory';
	const STATUS_TOKEN_KEY_EMPTY   = 'empty submit token key';
	const STATUS_TOKEN_KEY_MATCH   = 'match token key';
	const STATUS_TOKEN_KEY_UNMATCH = 'unmatch token key';
	const STATUS_UNKNOWN_ERROR     = 'unknown error';
	
	private function CheckTokenKey( $form_name )
	{
	//	if(!$this->CheckConfig( $form_name )){
		if(!$config = $this->GetConfig($form_name)){
			return false;
		}
		
		//	TODO: Please leave comment.
		$token_key_name = $this->GetTokenKeyName($form_name);
		
		//	get saved last time token by form_name, save to session
		$save_token = $this->GetTokenKey($form_name);
		
		//	get posted token, method is POST by hidden, is GET by Cookie  
		switch( strtolower($config->method) ){
			case 'post':
				$post_token = Toolbox::GetRequest( $token_key_name );
				break;
				
			case 'get':
				$post_token = $this->GetCookie($key);
				break;
			default:
				$this->mark();
		}
		
		if( !$save_token and !$post_token ){
			
			$this->SetStatus( $form_name, self::STATUS_VISIT_FIRST );
			return false;
			
		}else if(!$save_token and $post_token){
			
			$this->SetStatus( $form_name, self::STATUS_SESSION_DESTORY );
			return false;
			
		}else if( $save_token and !$post_token ){
			
			$this->SetStatus( $form_name, self::STATUS_TOKEN_KEY_EMPTY );
			
			if( $_SERVER['REQUEST_URI']{strlen($_SERVER['REQUEST_URI'])-1} !== '/' ){
				/*
				 * Apatch was transfer to real directory.
				 * Add of slash.
				 * 
				 * http://domain.tld/temp -> http://domain.tld/temp/
				 * 
				 */
				if( file_exists($_SERVER['DOCUMENT_ROOT'].$_SERVER['REQUEST_URI']) ){
				//	$this->mark($_SERVER['DOCUMENT_ROOT'].$_SERVER['REQUEST_URI']);
					$this->mark('Add to slash(/) at action tail.');
				}
			}
			
			return null;
			
		}else if( $save_token !== $post_token ){
			
			$this->SetStatus( $form_name, self::STATUS_TOKEN_KEY_UNMATCH );
			return false;
			
		}else if( $save_token === $post_token ){
			
			$this->SetStatus( $form_name, self::STATUS_TOKEN_KEY_MATCH );
			return true;
			
		}else{
			
			$this->SetStatus( $form_name, self::STATUS_UNKNOWN_ERROR );
			return false;
		}
	}
	
	/*******************************************************************************/
	
	private function CheckCSRF($form_name)
	{
        $fqdn = $this->GetEnv('fqdn');
		$this->mark($fqdn);
	
		if( $fqdn === $_SERVER['HTTP_REFERER']){
			$io = true;
		}else{
			$io = false;
			$this->SetStatus($form_name, 'NG: CSRF');
		}
	
		return $io;
	}
	
	/*******************************************************************************/
	
	/**
	 * Use debug for developer
	 * 
	 * @return Config
	 */
	public function Config()
	{
		return $this->config;
	}
	
	public function GetConfig( &$form_name, $input_name=null, $attr_name=null, $clone=true )
	{
		if(!$this->CheckConfig( $form_name, $input_name, $attr_name )){
			return false;
		}
		
		if( $attr_name ){
			if($clone){
				return clone $this->config->$form_name->input->$input_name->$attr_name;
			}else{
				return $this->config->$form_name->input->$input_name->$attr_name;
			}
		}
		
		if( $input_name ){
			if($clone){
				return clone $this->config->$form_name->input->$input_name;
			}else{
				return $this->config->$form_name->input->$input_name;
			}
		}
		
		if( $form_name ){
			if($clone){
				return clone $this->config->$form_name;
			}else{
				return $this->config->$form_name;
			}
		}
	}

	private function SetConfig( $config, $form_name, $input_name=null, $attr_name=null )
	{
		$this->mark(__METHOD__."( \$config, $form_name, $input_name, $attr_name)", 'form_flow');
		
		if(!$this->CheckConfig($form_name, $input_name, $attr_name)){
			return false;
		}
		
		$config = $this->Escape($config);
		
		if( isset($form_name) and isset($input_name) and isset($attr_name) ){
			$this->config->$form_name->input->$input_name->$attr_name = $config;
		}else 
		if( isset($form_name) and isset($input_name) ){
			$this->config->$form_name->input->$input_name = $config;
		}else
		if( isset($form_name) ){
			$this->config->$form_name = $config;
		}
	}
	
	private function CheckConfig( &$form_name, $input_name=null, $attr_name=null )
	{
		if(!$form_name){
			$form_name = $this->GetCurrentFormName();
		}
		
		if(is_null($form_name)){
			$this->StackError("form_name is null. (Form has started?)");
			return false;
		}
		
		if(!is_string($form_name)){
			$type = gettype($form_name);
			$this->StackError("form_name is not string. ($type)");
			return false;
		}
		
		if(!isset($this->config->$form_name)){
			$this->StackError("![.red[Does not exists this form_name. ({$form_name}) ]]");
			return false;
		}
		
		if(!$input_name){
			return true;
		}
		
		if(!isset($this->config->$form_name->input->$input_name)){
			if( $this->GetTokenKeyName($form_name) === $input_name ){
				return false;
			}
			$this->mark("![.red[This input-name doesn't exist in form-config. ($input_name, $form_name)]]");
			return false;
		}
		
		if(!$attr_name){
			return true;
		}
		
		if($attr_name == 'value'){
			return true;
		}
		
		if(!isset($this->config->$form_name->input->$input_name->$attr_name)){
			$this->mark("![.red[Does not exists this attr_name in input. (form=$form_name, input=$input_name, attr=$attr_name )]]");
			return false;
		}
		
		return true;
	}
	
	/*******************************************************************************/
	
	/**
	 * 
	 * @param  unknown $input_name
	 * @param  string  $joint
	 * @return string
	 */
	public function Value( $input_name, $form_name=null, $joint=null )
	{
		//  Get input value
		$value = $this->GetInputValue( $input_name, $form_name, $joint );
		
		//  Get config
		if(!$input = $this->GetConfig( $form_name, $input_name )){
			return "form_name=$form_name, input_name=$input_name";
		}
		
		//  Supports the options value (Get option's label)
		if( in_array( $input->type, array('select','checkbox','radio') ) ){
			//  If exists option
			if( isset($input->options) ){
				
				//  Accelerate
				if( isset($input->options->$value) ){
					if( isset($input->options->$value->label) ){
						$value = $input->options->$value->label;
					}
				}else{
					//  Brute search
					foreach( $input->options as $option ){
						if( $option->value == $value ){
							if( isset($option->label) ) {
								$value = $option->label;
							}
							break;
						}
					}
				}
			}
		}
		
//		print nl2br($value);
		print $value;
		
		return 'This method(function) is print.';
	}
	
	/**
	 * Get value
	 * 
	 * @param string $input_name
	 * @param string $form_name
	 * @param string $joint
	 * @return Ambigous <boolean, NULL, string, mixed, Ambigous, multitype:, string|array, number>
	 */
	public function GetValue( $input_name, $form_name=null, $joint=null )
	{
		return $this->GetInputValue( $input_name, $form_name, $joint );
	}

	/**
	 * Return to saved-value 1st. 2nd, default-value.
	 * 
	 * @param  string $input_name
	 * @param  string $form_name
	 * @param  string $joint
	 * @return boolean|NULL|string|mixed|Ambigous <string, boolean, multitype:, NULL>
	 */
    public function GetInputValue( $input_name, $form_name=null, $joint=null )
	{
		//  more fast
		if(!$input = $this->GetConfig( $form_name, $input_name )){
			$this->StackError("Does not exists config.(form: $form_name, input: $input_name)");
			return false;
		}
		
		//  Get raw value
		$value = $this->GetInputValueRaw( $input_name, $form_name, $joint );
		
		//	Check input's type
		switch( $type = strtolower($input->type) ){
			case 'file':
				if( $value ){
					//	If set dir
					if(!$dir = $input->save->dir ){
						$dir = null;
					}					
					//	If case of app:/xxx
					$value = $this->ConvertURL($dir.$value);
				}
				return $value;
			default:
		}
		
		//	TODO: Please add comment.
		switch( $type = strtolower(gettype($value)) ){
			case 'null':
				return null;
				
			case 'string':
				return nl2br($value);
				
			case 'boolean':
			case 'integer':
			case 'array':
				break;
				
			default:
				$this->mark("undefined type. ($type)");
		}
		
		//	TODO: Please add comment.
		if( is_array($value) ){
			if( strlen(join('',$value)) ){
				//  joint
				/*
				if( is_null($joint) ){
					$joint = isset($input->joint) ? $input->joint: '';
				}
				*/
				if( $joint === 'bit' ){
					$sum = null;
					foreach($value as $key => $var){
						$sum += $var;
					}
					$value = decbin($sum);
				}else{
					$value = join($joint,$value);
				}
			}else{
				$value = '';
			}
		}
		
		return $value;
	}
	
	public function GetInputValueRaw( $input_name, $form_name=null, &$joint=null )
	{
		if(!$input = $this->GetConfig( $form_name, $input_name )){
			return false;
		}

		//  get joint, pass to call method.
		if( is_null($joint) ){
			$joint = isset($input->joint) ? $input->joint: '';
		}
		
		//  If button
		if( $input->type === 'submit' or $input->type === 'button' or $input->type === 'img' ){
			$value = $this->GetRequest($input_name, $form_name);	
		}else{
			//  GetSaveValue is search session
			$value = $this->GetSaveValue( $input_name, $form_name );
		}
		
		//  If array
		if(is_array($value)){
			//  not check value is removed. 
			$value = array_diff($value,array(''));
		}
				
		//  If null, default value is used.
		if( is_null($value) ){
			if( !empty($input->cookie) ){
				$value = $this->GetCookie($form_name.'/'.$input_name);
			}else if( isset($input->value) ){
				$value = $input->value;
			}
		}
				
		return $value;
	}
	
	public function GetInputValueAll( $form_name )
	{
		//  Get form config.
		if(!$form = $this->GetConfig($form_name)){
			return false;
		}
		
		//  Init
		$return = array();
		
		//  Get saved value.
		foreach( $form->input as $input_name => $input ){
			if( $input_name == 'submit' ){
				continue;
			}
			$return[$input_name] = $this->GetInputValue( $input_name, $form_name );
		}
		
		return $return;
	}
	
	public function GetInputValueRawAll($form_name)
	{
		if(!$form = $this->GetConfig( $form_name )){
			return false;
		}
		
	//	$config = new Config();
		$config = array();
		foreach( $form->input as $input_name => $input ){
		//	$config->$input_name = $this->GetInputValueRaw( $input_name, $form_name );
			$config[$input_name] = $this->GetInputValueRaw( $input_name, $form_name );
		}
		
		//  remove submit button
	//	unset($config->submit);
	//	unset($config->submit_button);
		unset($config['submit']);
		unset($config['submit_button']);
		
		return $config;
	}

	function GetInputOptionValue( $option_name, $input_name, $form_name=null )
	{
		if(!$options = $this->GetConfig( $form_name, $input_name, 'options' )){
			return false;
		}
		
		$this->d($options);
		
		
		return $value;
	}
	
	public function GetInputOptionLabel( $option_name, $input_name, $form_name )
	{
		return $label;
	}
	
	public function SetInputValue( $value, $input_name, $form_name )
	{
		$input = $this->GetConfig( $form_name, $input_name );
		if( $io = $this->CheckInputValue( $input, $form_name, $value ) ){
			$this->SetStatus( $form_name, "OK: SetInputValue ($value, $input_name, $form_name)" );
			
			//  save to session 
			$session = $this->GetSession('form');
			$session[$form_name][$input_name]['value'] = $value;
			$this->SetSession( 'form', $session );
		}else{
			$this->SetStatus( $form_name, "NG: SetInputValue ($value, $input_name, $form_name)" );
		}
		
		return $io;
	}
	
	public function GetSaveValue( $input_name, $form_name )
	{
		return $this->GetSavedValue( $input_name, $form_name );
	}
	
	public function GetSavedValue( $input_name, $form_name )
	{
		$form = $this->GetSession('form');
		
		if( isset($form[$form_name][$input_name]['value']) ){
			$value = $form[$form_name][$input_name]['value'];
		}else{
			$value = null;
		}
		
		return $value;
	}

    /**************************************************************/

	private function SaveRequest( $form_name )
	{
		if(!$this->CheckTokenKey( $form_name )){
			return false;
		}
		
		// get session
		$session = $this->GetSession('form');
		
		// submit value
		$request = $this->GetRequest( null, $form_name);
		
		// 
		$form = $this->GetConfig($form_name);
		
		//  charset
		$charset = isset($form->charset) ? $form->charset: $this->GetEnv('charset');
		
		// 
		$fail = null;
		
		/*

		this is config base check.

		foreach($form->input as $input){
			$input_name = $input->name;
			$value = isset($request[$input_name]) ? $request[$input_name]: '';
		*/
		
		//  this is submit request base check.
		foreach( $request as $input_name => $value ){
			
			//  this form name
			if( $input_name === 'form_name' ){
				continue;
			}
			
			//  token key
			if( $input_name === $this->GetTokenKeyName($form_name) ){
				continue;
			}
			
			//  get input
			$input = $form->input->$input_name;
			
			//	readonly is skip
			if( !empty($input->readonly) or !empty($input->disabled) ){
				continue;
			}
			
			if(!isset($form->input->$input_name)){
				$this->StackError("Does not set input config.($form_name, $input_name)");
				continue;
			}
			
			//  submit is skip
			if( $input->type === 'submit' ){
				if(!isset($input->save) or !$input->save){
					continue;
				}
			}
			
			//  charset
			$input->charset = $charset;
			
			//  check send value
			if( $io = $this->CheckInputValue( $input, $form_name ) ){
				
				// does not save
				if(isset($input->save) and !$input->save){
					continue;
				}
				
				//  file
				if( $input->type === 'file' ){
					$value = $this->SaveFile($input, $form_name);
					if( is_null($value) ){
						// throw
					}else if($value === false){
						$fail = true;
					}else{
						$session[$form_name][$input_name]['value'] = $value;
					}
					continue;
				}
				
				// save session
				if(is_string($value)){
					$session[$form_name][$input_name]['value'] = $value;
				}else if(is_array($value)){
//					unset($value[0]); // I have no idea. Whether or not to delete.
					$session[$form_name][$input_name]['value'] = $value;
				}
				
				// save cookie
				if( isset($input->cookie) and /*$input->cookie and*/ !is_null($value) ){
					//  Remove check index.
					if( empty($value[0]) ){
						unset($value[0]);
					}
					$expire = $input->cookie === true ? 0: $input->cookie;
					$this->SetCookie("$form_name/$input_name", $value, $expire );
				}
			}else{
				$fail = true;
				$this->SetStatus($form_name, "NG: check input value. ($form_name, $input_name)");
			}
		}
		
		// save
		$this->SetSession('form',$session);
		
		// status
		if( is_null($fail) ){
			$this->SetStatus($form_name, 'secure');
		}else{
			$this->SetStatus($form_name, 'NG: save request');
		}
		
		return true;
	}
	
	/**
	 * Auto save (and remove) the upload file.
	 * 
	 * File upload is input's type is file.
	 * return value is file path or success/fail.
	 * 
	 * @param  string $input
	 * @param  string $form_name
	 * @return boolean|string|NULL 
	 */
	private function SaveFile( $input, $form_name )
	{
		$input_name = $input->name;
		$save_value = $this->GetInputValueRaw($input->name,$form_name);
		$post_value = $this->GetRequest($input->name, $form_name);
		
		//	$save_value is document_root path. (always?)
		if( $save_value ){
			//  Is remover?
			if( empty($post_value) or (is_array($post_value) and count($post_value) == 1 and empty($post_value[0])) ){
				
				//	get save directory
				$dir = empty($input->save->dir) ? null: $input->save->dir;
				
				//	Convert real path
				$path = $this->ConvertPath($dir.$save_value);
				
				//	Check file exists 
				if(!file_exists($path)){
					$this->StackError("Does not exists file. ($path)");
					return false;
				}
				
				//  challenge to delete the upload file.
				if(!unlink($path)){
					//  delete is failed.
					$this->StackError("Can not delete the file. ($save_value)");
					
					//  recovery post value TODO: Is this correct????
					$value = $this->ConvertURL($save_value);
					$id = $form_name.'-'.$input_name.'-'.md5($value);
					
					//  TODO: ???
					$_POST[$input_name][$id] = $value;
					
					return false;
				}
				
				//  Reset form config. 
				$this->SetInputValue( null, $input_name, $form_name );
			
				//	TODO: SetInputValue fail to permit=image (Why?)
				$_SESSION['OnePiece5']['Form5']['form'][$form_name][$input_name]['value'] = '';
				
				//  Status
				$this->SetStatus( $form_name, "XX: File delete is success. ($form_name, $input_name)");
				
				//  Return value is empty path.
				return '';
			}
		}
		
		if( isset($_FILES[$input->name]) ){
			$_file = $_FILES[$input->name];
			$name  = $_file['name'];
			$type  = $_file['type'];
			$tmp   = $_file['tmp_name'];
			$error = $_file['error'];
			$size  = $_file['size'];
			
			// extention
			$temp = explode('.',$name);
			$ext  = array_pop($temp);
		}else{
			$value = $this->GetRequest($input_name, $form_name);
			
			if( is_string($value) ){
				//	Use OpenSNS's extend. (delete button mode)
			//	$this->mark($value);
				return $value;
			}else if( is_array($value) ){
				if(!strlen(implode('',$value))){
					$error = -1;
				}else{
					$error = 4;
				}
			}else if( is_null($value) ){
				return $this->GetInputValueRaw( $input_name, $form_name );
			}
		}
		
		switch($error){
			case 0:
				$op_uniq_id = $this->GetCookie( self::KEY_COOKIE_UNIQ_ID );
				
				if( empty($input->save) ){
					$origin_path = sys_get_temp_dir() .'/'. md5($name . $op_uniq_id).".$ext";
				}else{
					if( isset($input->save->path) ){
						//  hard path
					//	$path = $this->ConvertPath( $input->save->path );
						$origin_path = $input->save->path;
					}else{
						//  directory
						if( isset($input->save->dir) ){
							$dir = $input->save->dir;
						//	$dir = $this->ConvertPath($dir);
							$dir = rtrim($dir,'/\\');
						//	$dir = rtrim($dir,'\\');
						}else{
							$dir = sys_get_temp_dir();
						}
						
						//  file name
						if( isset($input->save->name) ){
							$name = $input->save->name;
							$origin_path = $dir.'/'.$name.'.'.$ext;
						}else{
							$origin_path = $dir .'/'. md5($name . $op_uniq_id).".$ext";
						}
					}
				}
				
				//	$path is real path.
				$path = $this->ConvertPath($origin_path);
				
                //  Check directory exists
                if(!file_exists( $dirname = dirname($path) )){
                	$this->mark("Does not exists directory. ($dirname)");
                    if(!$io = mkdir( $dirname, 0766, true ) ){            	
                    	$this->StackError("Failed make directory. (".dirname($path).")");
                    	return false;
                    }
                }
				
				//  file is copy
                if(!copy($tmp, $path)){
                	$this->StackError("Does not copy at upload file. ($tmp, $path)");
                	return false;
                }
                
                //	remove dir path
                if( $dir ){
                	$dir = rtrim($dir,'/').'/';
                	$save_value = str_replace( $dir, '', $origin_path);
                }else{
                	$save_value = $origin_path;
                }

                $temp = array();
                $temp['origin_path'] = $origin_path;
                $temp['save_value'] = $save_value;
                $temp['dir']    = $dir;
                $temp['search'] = $this->ConvertPath($dir);
             //   $this->d($temp);
                
				//	Saved value
				$this->SetStatus( $form_name, "OK: file copy to $path");
				$this->SetInputValue( $save_value, $input_name, $form_name );
				
				return $save_value;
			
			//  
			case 4:
				$this->SetStatus( $form_name, "XX: File has not been submit. ($form_name, $input_name)");
				return null;
			
			//  TODO: This is not needed anymore?
			//  remove 
			case -1:
				$this->SetStatus( $form_name, "OK: File is remove. ($form_name, $input_name)");
				return '';
				
			default:
				$this->SetStatus( $form_name, "NG: Unknown error. ($error)");
		}
		return false;
	}
	
	/*******************************************************************************/
	
	public function GetCurrentFormName()
	{
		$key = 'CurrentFormName';
		return $this->GetEnv($key);
	}
	
	public function SetCurrentFormName($form_name)
	{
		$key = 'CurrentFormName';
		$this->SetEnv($key,$form_name);
	}
	
	/*******************************************************************************/
	
	/**
	 * Convert to Form-Config from Array.
	 */
	private function GenerateConfig( $args )
	{
		if( is_null($args) ){
			$this->StackError('$args is null.');
			return;
		}
		
		switch($type = gettype($args)){
			case 'string':
				$config = $this->GenerateConfigFromPath($args);
				break;
				
			case 'array':
				$config = Toolbox::toObject($args);
				break;
				
			case 'object':
				$config = $args;
				break;
				
			default:
				$this->stackError('Undefined args type.');				
		}
		
		return $config;
	}
	
	/**
	 * Convert old type config to moden config.
	 * 
	 * @param string $file_path
	 */
	private function GenerateConfigFromPath($path)
	{
		//  Convert from abstract path to absolute path.
		$path = $this->ConvertPath($path);
		
		//  Check
		if(!file_exists($path)){
			$this->StackError("File does not exists. ($path)");
			return false;
		}
		
		//  Include
		if(!$io = include($path)){
			$this->StackError("File include is failed. ($path)");
			return false;
		}
		
		//  This is Form5 standard.
		if( isset($config) ){
			//  Save reading file name.
			$config->file_name = $path;
			return $config;
		}

		//  Generate config
		$config = new Config();
		
		//  $_form and $_forms is uses Form4.class.php
		if(isset($_forms)){
			$config = Toolbox::toObject($_forms);
		}else if(isset($_form)){
			$config = Toolbox::toObject($_form);
		}else if(isset($config)){
			// OK
		}else{
			$this->StackError('Does not find form config.');
			return false;
		}
		
		//  Save reading file name.
		$config->file_name = $path;
		
		return $config;
	}
	
	/*******************************************************************************/
	
	function AddFormsFromPath()
	{
		$this->mark('![ .red [Does not implements yet.]]');
	}

	protected function AddFormFromPath($path)
	{
		if( file_exists($path) ){
			include($path);
		}else{
			$path = $this->ConvertPath($path);
			if( file_exists($path) ){
				include($path);
			}else{
				$this->StackError("Does not find config file path. ($path)");
				return false;
			}
		}
		
		if( isset($_forms) ){
			$config = Toolbox::toObject($_forms);
		}else
		if( isset($_form) ){
			$config = Toolbox::toObject($_form);
		}
		
		if(!isset($config)){
			$this->StackError("Does not find config variable. ($path)");
			return false;
		}
		
		return $config;
	}
	
	public function AddForms( $args )
	{
		if(!$config = $this->GenerateConfig($args)){
			return false;
		}
		
		foreach( $config as $form_name => $form ){
			
			if( is_string($form) ){
				$this->AddFormFromPath($form);
				continue;
			}
			
			if( empty($form->name) ){
				$form->name = $form_name;
			}
			 
			if(isset($file_name)){
				$form->file_name = $file_name;
			}
			
			if(!$io = $this->AddForm($form)){
				return $io;
			}
		}
		
		return true;
	}
	
	public function AddForm( $config )
	{
		if( is_string($config) ){
			$config = $this->AddFormFromPath($config);
		}
		
		//  Generate config from file path.
		if(!is_object($config) ){
			if(!$config = $this->GenerateConfig($config) ){
				return false;
			}
		}
		
		//  Check
		if(!isset($config->name)){
			$this->StackError('Is this $config a single config form? There is no form name in the $config.($config->name)');
			return false;
		}
		
		//  check form name
		if(empty($config->name)){
			$this->StackError('Empty form name.');
			return false;
		}else{
			$form_name = $config->name;
		}
		
		//  check exists config
		if(isset($this->config->$form_name)){
			$this->StackError("This form_name is already exists. ($form_name)");
			return false;
		}
		
		//  support plural form
		if(isset($config->inputs) and empty($config->input)){
			$config->input = $config->inputs;
		}

		//	Debug
		if( $this->_log ){
		//	$this->_log[] = __METHOD__ . " | " . $_SERVER['REQUEST_URI'];
			$this->_log[] = __METHOD__ . " | Last time: " . $this->GetSession('request_uri');
			$this->SetSession('request_uri',$_SERVER['REQUEST_URI']);
		}
		
		// default
		$this->status->$form_name = new Config();
		$this->config->$form_name = new Config();
		$this->config->$form_name->method = 'post';
		
		//  All escape
		$config = $this->Escape($config);
		
		// save config. save key is limits.
		foreach(array('name','method','action','multipart','id','class','style','error','errors','file_name') as $key ){
			if( isset($config->$key) ){
				$this->config->$form_name->$key = $config->$key;
			}
		}
		
		foreach( $config->input as $index => $input ){
			if( empty($input->name) ){
				$input->name = $index;
			}
			$this->AddInput( $input, $form_name );
		}
		
		// get charset
		if(isset($config->charset)){
			$charset = $config->charset;
		}else{
			$charset = $this->GetEnv('charset');
		}
		
		// change charset
		$save_charset = mb_internal_encoding();
		if( $save_charset !== $charset ){
			mb_internal_encoding($charset);
		}
		
		// post value is auto save
		$this->SaveRequest($form_name);
		
		// recovery charset
		if( $save_charset !== $charset ){
			mb_internal_encoding($save_charset);
		}
		
		return $config;
	}
	
	public function AddInput( $input, $form_name )
	{
		if( empty($form_name) ){
			$this->StackError("Empty form_name or input_name. ($form_name, {$input->name})");
			return false;
		}
		
		if(!isset($input->name)){
			$this->StackError("Does not set input name.(".serialize($input).")");
			return false;
		}
		
		if( isset($this->config->$form_name->input->{$input->name}) ){
			$this->StackError("Already exists this input. ($form_name, {$input->name})");
			return false;
		}
		
		//  str to lower
		/*
		if( isset($input->name) ){
			$input->name = strtolower($input->name);
			$input_name  = $input->name;
		}
		*/
		
		//	Stop the convert to lowercase.
		$input_name = $input->name;
		
		if( isset($input->type) ){
			$input->type = strtolower($input->type);
		}else{
			$input->type = 'text';
		}
		
		//  type
		$type = $input->type;
		
		//  file is neccesary multi-part
		if( $type === 'file' ){
			//  force change
			$this->SetStatus( $form_name, 'XX: Change multipart(method is force post)' );
			$this->config->$form_name->method    = 'post';
			$this->config->$form_name->multipart = true;
		}
		
		//  Options
		if( in_array($type,array('checkbox','radio','select')) ){
			
			//	Supports option (not options)
			if( empty($input->options) ){
				if( isset($input->option) ){
					$input->options = $input->option;
				}
			}
			
			//	Check value. (Why necessary is this?)
			if(!isset($input->options) and (!isset($input->value) or !strlen($input->value)) ){
				$this->mark("![.red[Empty $type value. ($form_name, $input_name)]]");
				$this->StackError("Empty options of $type. ($form_name, $input_name)");
			}
			
			//	Options
			if( isset($input->options) ){
			foreach( $input->options as $option_name => $option ){
				if( !empty($option->selected) or !empty($option->checked) ){
					if( isset($option->value) ){
						$input->value = $option->value;
					}
				}
			}
		}
		}
		
		//  added permit
		if( isset($input->validate->range) ){
			$input->validate->permit = 'numeric';
			if(!isset($input->validate->length)){
				$input->validate->length = '0-16';
			}
		}
		
		//  Set config
		$this->config->$form_name->input->$input_name = $input;
		
		//	support type=image
		if( $type == 'image'){
			$this->config->$form_name->input->{$input_name."_x"} = $input;
			$this->config->$form_name->input->{$input_name."_y"} = $input;
		}
		
		//  required
		if( isset($input->required) and $input->required ){
			if(empty($input->validate)){
				$input->validate = new Config();
			}
			$input->validate->required = true;
		}
		
		return true;
	}
	
	public function AddOption( $option, $input_name, $form_name )
	{
		if(!$input = $this->GetConfig($form_name, $input_name, null, false)){
			return false;
		}
		
		$input->options->md5(serialize($option))->$option;
		
		return true;
	}
	
	/*******************************************************************************/
	
	public function Start( $form_name=null, $action=null )
	{
		//  Check
		if(!$form_name){
			$this->StackError('form_name is empty. please set form_name.');
			return false;
		}

		//  Check
		if(!$this->CheckConfig($form_name)){
			return false;
		}
		
		//  Check
		if( $temp_name = $this->GetCurrentFormName() ){
			$this->StackError("Form is not finishing. (Open form is $temp_name)");
			return sprintf('<fail class="%s, %s, %s, %s"  />', 'OnePiece', get_class($this), __FUNCTION__, __LINE__);
		}

		//  Check
		if( $action and !is_string($action) ){
			$this->mark('$action is not string');
			$action = null;
		}
		
		// re-generate token key
		$this->GenerateTokenKey($form_name);
		
		$nl = $this->GetEnv('nl');
		$form = $this->config->$form_name;
		$token_key = $this->GetTokenKey($form_name);
		$token_key_name = $this->GetTokenKeyName($form_name);
		
		$form_name = $form->name;
		$method	 = $form->method === 'get' ? 'GET' : 'POST';
		$charset = isset($form->charset)   ? $form->charset: $this->GetEnv('charset');
		$class	 = empty($form->class)     ? null: sprintf('class="%s"', $form->class);
		$style	 = empty($form->style)     ? null: sprintf('style="%s"', $form->style);
		$enctype = empty($form->multipart) ? null: sprintf('enctype="multipart/form-data"');
		
		//  action
		if( is_null($action) ){
			$action = isset($form->action) ? $form->action: '';
		}
		if( $action ){
			$action	 = $this->ConvertUrl($action);
		}
		
		//  print form tag.
		printf('<form name="%s" action="%s" method="%s" %s Accept-Charset="%s" %s %s>'.$nl, $form_name, $action, $method, $enctype, $charset, $class, $style);
		
		if( $method == 'GET' ){
			$this->mark();
			$this->SetCookie( $token_key_name, $token_key, 0 );
		}else{
			printf('<input type="hidden" name="%s" value="%s" />'.$nl, $token_key_name, $token_key);
		}

		//	Log
		if( $this->_log ){ $this->_log[] = __METHOD__." | $form_name, $token_key"; }
		
		$this->SetCurrentFormName($form_name);
	}
	
	public function End( $form_name )
	{
		$this->Finish( $form_name );
	}
	
	public function Begin( $form_name )
	{
		$this->Start( $form_name );	
	}
	
	public function Finish( $form_name=null )
	{
		if(!$form_name){
			$this->StackError('form_name is empty. please set form_name.');
			return false;
		}
		
		if(!$form_current = $this->GetCurrentFormName() ){
			$this->StackError("Form is not started.");
			return false;
		} 
		
		if( $form_current !== $form_name ){
			$this->StackError("Close form name is not match. (open=$form_current, close=$form_name)");
			return false;
		}
		
		printf("</form>");
		
		$this->SetCurrentFormName(null);
		
		return null;
	}
	
	public function Erase( $form_name, $force=false, $location=true )
	{
		if(!is_string($form_name)){
			$this->mark('$form_name is not string.');
			return false;
		}
		if(!$this->CheckConfig($form_name)){
			if( $force ){
				$this->mark("form_name = $form_name is not initialized. buy force cleard.");
			}else{
				return false;
			}
		}
		
		//	Reset token key.
		$this->SetTokenKey($form_name, md5(microtime(true)));
		
        //  Erase the saved value.
		$form = $this->GetSession('form');
		if( isset($form[$form_name]) ){
			unset($form[$form_name]);
		}
		
		//	Empty the $_POST
		$_POST = array();
		
		//	Save empty value to session.
		$this->SetSession('form',$form);
		
		//	Location
		if( $location ){
			if(!header("Location: {$_SERVER['REQUEST_URI']}")){
				$this->StackError('Location is failed.');
			}
		}
		
		return true;
	}
	
	public function Delete( $form_name, $force=false, $location=false )
	{
		return $this->Erase($form_name, $force, $location);
	}
	
	public function Remove( $form_name, $force=false, $location=false )
	{
		return $this->Erase($form_name, $force, $location);
	}
	
	public function Clear( $form_name, $force=false, $auto_relocate=false )
	{
		return $this->Erase($form_name, $force, $auto_relocate);
	}
	
	public function Flash( $form_name, $force=false, $location=false )
	{
		return $this->Erase($form_name, $force, $location);
	}
	
	private function CreateInputTag( $input, $form_name, $value_default=null )
	{
		//  init
		$nl   = $this->GetEnv('nl');
		$tag  = '';
		$join = array();
		$tail = '';
		$value_of_input = null;

		//  attribute
		foreach($input as $key => $var){
			switch($key){
				case 'name':
				case 'type':
				case 'id':
				case 'label':
				case 'tail':
				case 'checked':
				case 'save':
					${$key} = $var;
					break;
					
				case 'value':
					/*
					if( is_null($value_default) ){
						$value_default = $var;
					}
					*/
					$value_of_input = $var;
					break;
				
				case 'readonly':
				case 'disabled':
					$readonly = true;
					if( $var ){
						$join[] = sprintf('%s="%s"',$key,$key);
					}
					break;
					
				case 'session': // What is this? Where do use?
				//	$input->save = $input->session;
					$input->save = $var;
				case 'error':
				case 'option':
				case 'options':
				case 'validate':
				case 'cookie':
				case 'index':
				case 'child':
				case 'joint':
				case 'group':
					break;
					
				default:
					if(!is_string($var)){
						$var = Toolbox::toString( $var, ' ');
					}
					$join[] = sprintf('%s="%s"',$key,$var);
			}
		}
		
        //  name
        if(empty($name)){
            $name = $input->name;
        }
        $input_name = $input->name;

        //  type
        if( empty($type) ){
            $type = 'text';
        }else{
        	$type = strtolower($type);
        }
		
		//  id
		if( empty($id) ){
			$id = $form_name.'-'.$input_name;			
			if( $type !== 'checkbox' or $type !== 'radio' ){
				//  Create unique id.
				if( isset($input->index) ){
					$id .= '-'.$input->index;
				}else if( isset($input->value) ){
					$id .= '-'.md5($input->value);
				}
			}
		}
		$join[] = sprintf('id="%s"',$id);
		
		//	Class
		if( empty($class) ){
			switch($type){
				case 'submit':
					$join[] = 'class="op-input op-input-button op-input-submit"';
					break;
					
				case 'textarea':
					$join[] = 'class="op-input op-input-text op-input-textarea"';
					break;

				case 'password':
					$join[] = 'class="op-input op-input-text op-input-password"';
					break;
						
				default:
					$join[] = sprintf('class="op-input op-input-%s"',$type);
			}	
		}
		
		//  Other attributes
		$attr = join(' ',$join);

		// request
		$_request = $this->GetRequest( null, $form_name );
		
		// Value
		if( $type === 'password' ){
			$value = null;
		}else if( !empty($input->group) ){
			//	Bulk input value
		}else if( $type === 'submit' or $type === 'button' or $type === 'file' ){
			
			if( $value_default ){
				//	Over write
				$value = $value_default;
			}else if( $value = $this->GetSaveValue($input_name, $form_name) ){
				//	overwrite value that have been saved to session. 
			}else{
				$value = $value_of_input;
			}
			
		}else if( isset($_request[$input_name]) and empty($readonly) ){
			$value = $_request[$input_name];
		}else if('checkbox' === $type or 'radio' === $type){
			$value = $this->GetSaveValue($input_name, $form_name);
		}else{
			$value = $this->GetInputValueRaw($input_name, $form_name);
		}
		
		if( 0 ){
			$this->mark($value_default);
			$this->mark($value_of_input);
			$this->mark($value);
		}
		
		// case of save to cookie 
		if( !isset($value) or is_null($value) ){
			$value = $this->GetCookie($form_name.'/'.$input_name);
			if( is_null($value) and ('checkbox'!==$type and 'radio'!==$type) ){
				$value = $value_of_input;
			}
		}
		
		//  tail
		$tail = $this->Decode($tail);
		
		// radio
		if('radio' === $type){
			if( isset($value) and isset($input->value) ){
				$checked = $input->value == $value ? true: false;
			}else{
				//	here parent set
				$checked = isset($checked) ? $checked: '';
			}
		}
		
		//  checkbox
		if('checkbox' === $type ){
			// checked
			$save = isset($save) ? $save: true;
			if( (isset($save) ? $save: true) and is_array($value) ){
				$checked = isset($value[$id]) ? true: false;
			}
			
			//  inner tag value
			$value = isset($input->value) ? $input->value: '';
		}
		
		//  input is group cace
		if( is_array($value) ){
			if( $type === 'group' ){
				$value = join( $input->joint, $value );
			}else{
			$value = isset($value[$id]) ? $value[$id]: '';
		}
		}
		
		//  name
		if( 'radio' !== $type and ('checkbox' === $type or isset($input->child)) ){
			$name .= "[$id]";
		}
		
		//  create
		switch($type){
			case 'textarea':
				$tag = sprintf('<textarea name="%s" %s>%s</textarea>'.$tail, $name, $attr, $value);
				break;
				
			case 'select':
				if( isset($input->options) ){
					$options = $input->options;
				}else{
					$options = array();
				}
				
				//	If group case, use by parent was rewritten value.
				if(!empty($input->group)){
					$value = $input->value;
				}
				$tag = sprintf('<select name="%s" %s>%s</select>'.$tail, $name, $attr, $this->CreateOption( $options, $value));
				break;
				
			case 'file':
				//  remove checkbox
				$value = $this->GetInputValue($input_name);
				
				if( is_string($value) and $value ){
					if( isset($input->remover) and empty($input->remover)){
						//	Does not create remover
						$remover = null;
					}else if( method_exists( $this, 'GetInputConfigRemover')){
						//  If you can method over ride.
						$remover = $this->GetInputConfigRemover( $input, $form_name );
					}else{
						//  default remover
						$value = $this->ConvertURL($value);
						$remover = new Config();
						$remover->name    = $input->name;
						$remover->type    = 'checkbox';
						$remover->value   = $value;
						$remover->label   = $value_default ? $value_default: $value;
						$remover->checked = true;
					}
					
					//  Create remover
					if( $remover ){
						$tag = $this->CreateInputTag($remover, $form_name);
					}
				}else{
					//  Create file tag
					$tag = sprintf('<input type="%s" name="%s" value="%s" %s />'.$tail, $type, $input_name, $value, $attr);
				}
				break;
				
			default:
				//  single or multi
				if( isset($input->options) ){
					//  multi
					
					//	get joint character
					$joint = isset($input->joint) ? $input->joint: null;
					
					//	value of childs
					if( $value and $joint ){
						$value_child = explode( $joint, $value );
					}
					
					//  child
					foreach( $input->options as $index => $option ){
						//	Create input config from parent input.
						$child = Toolbox::Copy($input);
						$child->child = true;
						$child->index = $index;
						unset($child->options);
						
						//  Copy of option's value. 
						foreach( $option as $key => $var ){
							$child->$key = $var;
						}
						
						//	Set group flag
						if( $input->type == 'group' ){
							$child->group = true;
						}
						
						//  Set to label
						if( $child->type === 'radio' or $child->type === 'checkbox' ){
							$child->label = isset($option->label) ? $option->label: $option->value;
						}
						
						//	Set to value
						if( isset($value_child) ){
							$child->value = array_shift($value_child);
						}
						
						//  default checked
						if( isset($input->value) ){
							if( $input->value == $child->value ){
								$child->checked = true;
							}
						}
						$tag .= $this->CreateInputTag($child, $form_name);
					}
				}else{
					//  single
					//  checkbox and radio
					if( $type === 'checkbox' or $type === 'radio' ){
						//  value
						$value = $input->value;
						//  label
						if(!isset($label)){
							$label = $input->value;
						}
						$label = sprintf('<label for="%s">%s</label>', $id, $label);
					}else{
						$label = '';
					}
					
					//  tail
					if(isset($tail)){
						$label .= $tail;
					}
					
					//  checked
					if(!empty($checked)){
						$attr .= ' checked="checked"';
					}
					
					//  create tag
					$tag .= sprintf('<nobr><input type="%s" name="%s" value="%s" id="%s" %s />%s</nobr>', $type, $name, $value, $id, $attr, $label);
				}
				break;
		}
	
		//  dummy
		if( $type === 'checkbox' and !isset($input->child) ){
			$tag .= sprintf('<input type="hidden" name="%s[]" value=""/>', $input_name) . $nl;
		}
		
		return $tag . $nl;
	}
	
	function CreateOption( $options, $save_value )
	{
		$result = '';
		foreach( $options as $option ){
			//	
			$value = isset($option->value) ? $option->value: null;
			$label = isset($option->label) ? $option->label: $value;
			
			if( $value == $save_value and strlen($value) === strlen($save_value) ){

				/*
				
				//  selected
				$selected = 'selected="selected"';
			}else if( !empty($option->selected) or !empty($option->checked) ){
				
				$this->mark("$value, $save_value");
				*/
				
				//  defalut select
				$selected = 'selected="selected"';
			}else{
				$selected = null;
			}
			
			//	attributes
			$attr = array();
			foreach( $option as $key => $var ){
				switch( $key ){
					case 'name':
						$name = $var;
						continue;
					case 'selected':
						continue;
					default:
						$attr[] = sprintf('%s="%s"', $key, $var);
				}
			}
			$attr = implode(' ', $attr);
			
			//	joint
			$result .= sprintf('<option value="%s" %s %s>%s</option>', $value, $attr, $selected, $label);
		}
		
		return $result;
	}
	
	function GetInput( $input_name, $form_name=null, $value=null )
	{
		if(!$input = $this->GetConfig( $form_name, $input_name )){
			return '';
		}
		return $this->CreateInputTag( $input, $form_name, $value );
	}
	
	function Input( $input_name, $value=null, $form_name=null )
	{
		print $this->GetInput( $input_name, $form_name, $value );
		return 'This method(function) is print.';
	}
	
	function Label( $input_name, $form_name=null )
	{
		print $this->GetInputLabel( $input_name, $form_name );
		return 'This method(function) is print.';
	}
	
	function InputLabel( $input_name, $form_name=null )
	{
		$label = $this->GetInputLabel( $input_name, $form_name );
		print $label;
		return 'This method(function) is print.';
	}

	function GetLabel( $input_name, $form_name=null, $option_value=null )
    {
        return $this->GetInputLabel( $input_name, $form_name, $option_value );
    }

	function GetInputLabel( $input_name, $form_name=null, $option_value=null )
	{
		if(!$this->CheckConfig($form_name, $input_name)){
			return false;
		}
		
		if(isset($this->config->$form_name->input->$input_name->label)){
			$label = $this->config->$form_name->input->$input_name->label;
		}else{
			$label = $this->config->$form_name->input->$input_name->name;
		}
		
		return $label; 
	}
	
	/*******************************************************************************/
	
	function Debug( $form_name )
	{
		if(!$this->admin() ){
			$this->mark('Does not view debug info. because you are not admin.');
			return false;
		}
		
		if(!$form_name){
			if(!$form_name = $this->GetCurrentFormName()){
				$this->mark('![ .red [Debug method is required form_name.]]');
				return false;
			}
		}
		
		$temp['form_name'] = $form_name;
		$temp['Status']	 = $this->GetStatus($form_name);
		$temp['Error']	 = Toolbox::toArray($this->status->$form_name->error);
		$temp['Errors']	 = $this->status->$form_name->stack;
		$temp['session'] = $this->GetSession('form');
		$temp['history'] = $this->GetSession('history');
		
		//  Print debug information
		$call = $this->GetCallerLine();
		$this->p("Form debugging[ ![.small[ $call ]] ]");
		Dump::d($temp);
	}
	
	/**
	 * Print error message.
	 * 
	 * @param string $input_name
	 * @param string $html
	 * @param string $form_name
	 */
	function Error( $input_name, $html='span 0xff0000', $form_name=null )
	{
		print $this->GetInputError( $input_name, $form_name, $html );
		return $this->i18n()->Get('This method(function) is print.');
	}
	
	/*
	function InputError( $input_name, $html='span 0xff0000', $form_name=null )
	{
		print $this->GetInputError( $input_name, $html, $form_name );
		return $this->i18n()->Get('This method(function) is print.');
	}
	*/

	/**
	 * Get error message.
	 * 
	 * @param string $input_name
	 * @param string $html
	 * @param string $form_name
	 * @return Ambigous <boolean, string>
	 */
	function GetError( $input_name, $form_name=null, $html='span 0xff0000' )
    {
        return $this->GetInputError( $input_name, $form_name, $html );
    }

    /**
     * Get error message.
     * 
     * @param  string $input_name
     * @param  string $form_name
     * @param  string $html
     * @return boolean|string
     */
	function GetInputError( $input_name, $form_name=null, $html='span 0xff0000' )
	{
		if(!$this->CheckConfig($form_name,$input_name)){
			return false;
		}
		
		if( isset($this->status->$form_name->error->$input_name) ){
			
			$message = '';
			$value2  = '';
			$input   = $this->GetConfig( $form_name, $input_name );
			$label   = isset($input->label) ? $input->label: $input->name;
			
			foreach($this->status->$form_name->error->$input_name as $key => $value){
				
			//	$key   = $this->i18n()->get($key);
			//	$value = $this->i18n()->get($value);
				
				if( isset($input->error->$key) ){
					$format = '![ $html ['.$input->error->$key.']]';
				}else{
					$format = $this->i18n()->Get('$label is error. This field is $key. ($value)');
					$format = "![ $html [$format]]";
				}
				
				$patt = array('/\$label/', '/\$key/', '/\$value2/', '/\$value/', '/\$html/');
				$repl = array( $label, $key, $value2, $value, $html );
				
				if( $temp = preg_replace($patt, $repl, $format) ){
					$message .= $this->wiki2($temp,array('tag'=>true)).PHP_EOL;
				}
			}
			
			return $message;
		}else{
			return '';
		}
	}
	
	function GetErrorList( $form_name )
	{
		return $this->status->$form_name->error;
	}
	
	function SetInputError( $input_name, $form_name, $key, $value='' )
	{
		if( !$input_name or !$form_name or !$key /* or !$value or !strlen($value) */ ){
			$this->StackError("One or more empty. form_name=$form_name, input_name=$input_name, key=$key, value=$value");
			return false;
		}
		$this->status->$form_name->error->$input_name->$key = $value;
	}
	
	/*******************************************************************************/
	
	/**
	 * Pass to CheckValidate method.
	 * 
	 * Is this necessary?
	 * 
	 * @param  Config $input
	 * @param  string $form_name
	 * @param  string|null $value
	 * @return boolean
	 */
	function CheckInputValue( &$input, $form_name, $value=null )
	{
		if( !is_null($value) and !is_string($value)){
			$this->StackError('argument value is only string, yet');
			return false;
		}
		
		if( isset($input->options) ){
			foreach($input->options as $child){
				$child->name = $input->name;
				if(!$io = $this->CheckInputValue($child, $form_name)){						
					return false;
				}
			}
		}
		
		//  validate
		if(!empty($input->validate)){
			if(!$this->CheckValidate($input, $form_name, $value)){					
				return false;
			}
		}
	
		return true;
	}
	
	/**
	 * Convert value.
	 * 
	 * @param  string $value
	 * @param  string $option
	 * @param  string $charset
	 * @return string
	 */
	function CheckConvert( $value, $option, $charset )
	{
		switch( strtolower($option) ){
			case 'hankaku':
            case 'zen-han':
            case 'zen->han':
				$option = 'akh';
				break;
			case 'han-zen':
			case 'zenkaku':
				$option = 'AK';
				break;
			case 'kata-hira':
			case 'hiragana':
				$option = 'c';
				break;
			case 'hira-kata':
			case 'katakana':
				$option = 'C';
				break;
			default:
				$option = $option;
		}
		
		$value = mb_convert_kana( $value, $option, $charset );

		return $value;
	}
	
	function CheckReplace($config)
	{
        /*
		$request = $this->GetRequest();
		
		if(isset($config->charset)){
			$charset = $config->charset;
		}else{
			$charset = $this->GetEnv('charset');
		}
		
		foreach($config->input as $input_name => $input){
			if( isset($input->replace) ){
				list( $patt, $repl ) = explode( ',', $input->replace );
				$config->value = preg_replace( "/$patt/", $repl, $config->value );
			}
		}
		*/

        $this->mark('CheckReplace-method is does not implementation yet.');

		return true;
	}
	
	function CheckValidate( $input, $form_name, $value=null )
	{
		if(!$this->CheckConfig( $form_name, $input->name )){
			return false;
		}
		
		if(!$value){
			// send value
			$value = $this->GetRequest( $input->name, $form_name );
		}
			
		//  trim
		if(!empty($input->trim)){
			$this->SetStatus($form_name,"XX: trim ({$input->name})");
			//  normal
			$value = trim($value);
			//  custom
			if(is_string($input->trim)){
				$value = trim($value,$input->trim);
			}
		}
		
		//  this is file upload remover checkbox.
		if( $input->type == 'file' and is_array($value) ){
			return true;
		}
		
		// check required
		if(!empty($input->validate->required)){
			if(!$this->ValidateRequied($input, $form_name, $value)){
				return false;
			}
		}
		
		// Return, if not required.
		if( is_array($value) ){
			if( 0 === strlen(join('',$value)) ){
				return true;
			}
		}else if( 0 === strlen($value) and !isset($input->validate->compare) ){
			return true;
		}
		
		// permit
		if( isset($input->validate->permit) ){
			if(!$this->ValidatePermit($input, $form_name, $value)){
				return false;
			}
		}
		if( isset($input->validate->length) ){
			if(!$this->ValidateLength($input, $form_name, $value)){
				return false;
			}
		}
		if( isset($input->validate->range) ){
			if(!$this->ValidateRange($input, $form_name, $value)){
				return false;
			}
		}
		if( isset($input->validate->match) ){
			if(!$this->ValidateMatch($input, $form_name, $value)){
				return false;
			}
		}
		if( isset($input->validate->compare) ){
			if(!$this->ValidateCompare($input, $form_name, $value)){
				return false;
			}
		}
		if( isset($input->validate->deny) ){
			if(!$this->ValidateDeny($input, $form_name, $value)){
				return false;
			}
		}
		if( isset($input->validate->allow) ){
			if(!$io = $this->ValidateAllow($input, $form_name, $value)){
				//$this->mark($io);
				return false;
			}
		}
		if( isset($input->validate->mask) ){
			if(!$io = $this->ValidateMask($input, $form_name, $value)){
				return false;
			}
		}
		
		return true;
	}
	
	/*******************************************************************************/
	
	function ValidateRequied( $input, $form_name, $value )
	{
		if(is_array($value)){
			$value = implode('',$value);
		}
		
		if( strlen($value) ){
			$io = true;
		}else if( is_null($value) and $this->GetSaveValue($input->name, $form_name)){
			$io = true;
		}else if( is_null($value) and isset($input->value) and strlen($input->value) ){
			$io = true;
		}else{
			$io = false;
		}
		
		if($io){
			$this->SetStatus($form_name, "OK: Required. ($input->name, $value)");
		}else{
			$this->SetInputError( $input->name, $form_name, 'required', 'empty');
		}
		
		return $io;
	}
	
	function ValidateLength( $input, $form_name, $value )
	{
		list( $min, $max ) = explode('-',$input->validate->length);

		$len = mb_strlen( $value );
		
		if( $len < $min ){
			$this->SetInputError( $input->name, $form_name, 'short', $min - $len );
			return false;
		}
		
		if( $len > $max ){
			$this->SetInputError( $input->name, $form_name, 'long', $len - $max );
			return false;
		}
		
		$this->SetStatus($form_name, "OK: Validate-Length. ($input->name, $value)");
		return true;
	}
	
	function ValidateRange( $input, $form_name, $value )
	{
		list( $min, $max ) = explode('-',$input->validate->range);
		
		if( $min and $value < $min ){
			$this->SetInputError( $input->name, $form_name, 'small', $value );
			return false;
		}
		
		if( $max and $value > $max ){
			$this->SetInputError( $input->name, $form_name, 'large', $value );
			return false;
		}
		
		$this->SetStatus($form_name, "OK: Validate-Range. ($input->name, $value)");
		return true;
	}
	
	function ValidateMatch( $input, $form_name, $value )
	{
		if( preg_match( $input->validate->match, $value, $match ) ){
			$this->SetStatus($form_name, "OK: Validate-Match. ($input->name, {$match[0]})");
			return true;
		}else{
			$this->SetInputError( $input->name, $form_name, 'match', $value );
			return false;
		}
	}
	
	function ValidateAllow( $input, $form_name, $value )
	{
		$this->mark(__METHOD__."( \$input, $form_name, $value )", 'form_flow');
		
		if( preg_match( $input->validate->allow, $value, $match ) ){
			$this->SetStatus($form_name, "OK: Validate-Allow. ($input->name, {$match[0]})");
			return true;
		}else{
			$this->SetInputError( $input->name, $form_name, 'allow', $value );
			return false;
		}
	}
	
	function ValidateDeny( $input, $form_name, $value )
	{
		if( preg_match( $input->validate->deny, $value, $match ) ){
			$this->SetInputError( $input->name, $form_name, 'deny', $match[0] );
			return false;
		}else{
			$this->SetStatus($form_name, "OK: Validate-Deny. ($input->name)");
			return true;
		}
	}
	
	function ValidateCompare( $input, $form_name, $value )
	{
		$this->mark(__METHOD__."( \$input, $form_name, $value )", 'form_flow');
		
		$compare_value = $this->GetRequest($input->validate->compare, $form_name);
		
		if( $value === $compare_value ){
			$this->SetStatus($form_name, "OK: Validate-Compare. ($input->name, $value)");
			return true;
		}else{
			$this->SetInputError( $input->name, $form_name, 'compare', "$compare_value, $value" );
			return false;
		}
	}
	
	function ValidateMask( $input, $form_name, $value )
	{
		$this->mark('This method is test implements.');

        $sum = 0;
		if(is_array($value)){
			foreach($value as $var){
				$sum += $var;
			}
		}

		foreach($input->validate->mask as $key => $var){
			switch($key){
				case 'and':
					$sum = $sum & $var;
					break;
				case 'or':
					$sum = $sum | $var;
					break;
				case 'xor':
					$sum = $sum ^ $var;
					break;
			}
			
			if( $sum ){
				$this->SetInputError( $input->name, $form_name, 'validate-mask-'.$key, $value );
				return false;
			}
		}

        return true;
	}
	
	function ValidatePermit( $input, $form_name, $value )
	{
		switch( $key = $input->validate->permit ){
			
			// English only
			case 'english':
				//  Array is convert string.
				if(is_array($value)){
					$value = implode('',$value);
				}
				//  Check character
				if( $io = preg_match('/([^-_a-z0-9\s\/\\\!\?\(\)\[\]\{\}\.,:;\'"`@#$%&*+^~|]+)/i',$value,$match)){					
					//$this->d($match);
					$this->SetInputError( $input->name, $form_name, 'permit-english', $match[1] );
					//  Permit is failed
					$io = false;
				}else{
					$io = true;
				}
				break;

			// Use for password
			case 'password':
				//  Array is convert to string.
				if(is_array($value)){
					$value = implode('',$value);
				}
				//  Check character
				if( $io = preg_match('/([^-_a-z0-9\/\\\!\?\(\)\[\]\{\}:;\'"`@#$%&*+^~|]+)/i',$value,$match)){
					//$this->d($match);
					$this->SetInputError( $input->name, $form_name, 'permit-password', $match[1] );
					//  Permit is failed
					$io = false;
				}else{
					$io = true;
				}
				break;
				
			// including decimal
			//	case 'number':
			case 'numeric':
				if(is_array($value)){
					$value = implode('',$value);
				}
				if(!$io = is_numeric($value)){
					$this->SetInputError( $input->name, $form_name, 'permit-numeric', $value );
				}
				break;
				
			// including negative integer (not decimal)
			case 'integer':
				if(is_array($value)){
					$value = implode('',$value);
				}
				
				//  Check numeric
				if(!$io = is_numeric($value)){
					$this->SetInputError( $input->name, $form_name, 'permit-integer', $value );
					break;
				}
				
				//  Check integer
				if( $io = preg_match('/([^-0-9])/', $value, $match) ){
					$this->SetInputError( $input->name, $form_name, 'permit-integer', $value );
				}else{
					$io = true;
				}
				break;
				
			case 'url':
				if(is_array($value)){
					$value = implode('/',$value);
				}
				$io = $this->ValidatePermitUrl($input, $form_name, $value);
				break;
				
			case 'email':
				if(is_array($value)){
					$value = implode('@',$value);
				}
				//$io = filter_var( $value, FILTER_VALIDATE_EMAIL);
				$io = $this->ValidatePermitEmail($input, $form_name, $value);
				break;
			
			case 'phone':
				if( strlen(implode('',$value)) ){
					if( is_array($value) ){
						$value = implode('-',$value);
					}
					$io = $this->ValidatePermitPhone($input->name, $form_name, $value);
				}else{
					$io = true;
				}
				break;
			
			case 'date':
				if( is_array($value) ){
					if( strlen(implode('',$value)) ){
						$date = implode('-',$value);
					}else{
						$date = '';
					}
				}else{
					$date = $value;
				}
				
				if(!$date){
					$io = true;
					break;
				}
				
				if(!preg_match('/^[0-9]{1,4}-[0-9]{1,2}-[0-9]{1,2}$/',$date)){
					$io = false;
					$this->SetInputError( $input->name, $form_name, 'permit-date', join('-',$value) );
					break;
				}else{
					$time = strtotime($date);
					if(!$io = checkdate( date('m',$time), date('d',$time), date('Y',$time))){
						$this->SetInputError( $input->name, $form_name, 'permit-date', join('-',$value) );
					}
				}
				break;

			case 'datetime':
				if(!$io = preg_match('/^[0-9]{1,4}-[0-9]{1,2}-[0-9]{1,2} [0-2]?[0-9]:[0-5]?[0-9]:[0-5]?[0-9]$/',$value)){
					$this->SetInputError( $input->name, $form_name, 'permit-datetime', $value );
				}
				break;
				
			case 'image':
				$io = $this->ValidateImage( $input, $form_name, $value );
				break;
				
			default:
                $io = false;
                if(!is_string($key)){
                	$this->d($key);
                	$key = 'undefined';
                }
				$this->StackError("undefined permit key. ($input->name, $key)");
		}
		
		if( $io ){
			if(is_array($value)){
				$value = join(', ',$value);
			}
			$this->SetStatus($form_name, "OK: Permit $key. ($input->name, $value)");
		}
		return $io;
	}
	
	function ValidatePermitUrl( $input, $form_name, $value )
	{
		if(!preg_match('|^https?://|',$value)){
			$this->SetInputError( $input->name, $form_name, 'permit-url', 'Start to http://' );
			return false;
		}
		
		$patt = '|https?://([-_a-z\.]+)/?|';
		if( preg_match( $patt, $value, $match )){
			$host = $match[1];
		}else{
			$this->SetInputError( $input->name, $form_name, 'permit-url', $value );
			return false;
		}
		
		// check exists host
		if( $_SERVER['REMOTE_ADDR'] == '127.0.0.1' or 
			$_SERVER['REMOTE_ADDR'] == '::1' ){
			$this->SetStatus($form_name, "XX: Skip check host. ($input->name, $value)");
			return true;
		}
		
		if(!checkdnsrr($host,'A')){
			$this->SetInputError( $input->name, $form_name, 'permit-host', $host );
			return false;
		}
		
		return true;
	}
	
	function ValidatePermitEmail( $input, $form_name, $value )
	{
		//	check part of address
		$patt = '/^([a-z0-9][-_a-z0-9\.\+]+)@[-_a-z0-9]+/i';
		if(!preg_match( $patt, $value, $match ) ){
			$this->SetInputError( $input->name, $form_name, 'permit-email', $value );
			return false;
		}
		
		//	Does not check by localhost
		if( $_SERVER['REMOTE_ADDR'] == '127.0.0.1' or 
			$_SERVER['REMOTE_ADDR'] == '::1' ){
			$this->mark("![.gray[Skipped remote IP address check. ({$_SERVER['REMOTE_ADDR']})]]");
			$this->SetStatus($form_name, "XX: Skip check host. ($input->name, $value)");
			return true;
		}
		
		// check exists host
		list( $addr, $host ) = explode('@',$value);
		if(!checkdnsrr($host,'MX')){
			$this->SetInputError( $input->name, $form_name, 'permit-host', '@'.$host );
			return false;
		}
		
		return true;
	}
	
	function ValidatePermitPhone( $input, $form_name, $value )
	{
		//	Japanese pattern
		$patt = '/^([0-9]{2,4})-?([0-9]{2,4})-?([0-9]{2,4})$/';
		if(!preg_match( $patt, $value, $match ) ){
			$this->SetInputError( $input->name, $form_name, 'permit-phone', $value );
			return false;
		}
		
		return true;
	}
	
	function ValidateImage( $input, $form_name, $value )
	{
		if( isset($_FILES[$input->name]) ){
			//	
			if($_FILES[$input->name]['error'] == 4){
				$this->SetStatus($form_name,"XX: File has not been submit. ({$input->name})");
				return true;
			}
		}else if( $value ){
			//	already been checked
			$this->SetStatus($form_name,"XX: File has already been checked. ({$input->name})");
			return true;
		}else{
			$this->SetStatus($form_name,"NG: Does not find in \$_FILES. ({$input->name})");
			return false;
		}
		
		//  image info
		if(!$info = getimagesize($_FILES[$input->name]['tmp_name'])){
			$this->SetInputError( $input->name, $form_name, 'image', 'not image' );
			return false;
		}
		
		//  image different
		if($info['mime'] !== $_FILES[$input->name]['type']){
			$this->SetInputError( $input->name, $form_name, 'image', 'not match mime (camouflage)' );
			return false;
		}
		
//		$width  = $info[0];
//		$height = $info[1];
		$mime   = $info['mime'];
//		$size   = $_FILES[$input->name]['size'];
		list($type,$ext) = explode('/',$mime);
		
		if( $type !== 'image' ){
			$this->SetInputError( $input->name, $form_name, 'image', "not image ($type, ext=$ext)" );
			return false;
		}
		
		return true;
	}
	
	/***************************************************************************************/
	
	function Decorate($input_name, $config)
	{
		printf( $config, $this->GetLabel($input_name), $this->GetInput($input_name), $this->GetError($input_name));
	}
}
