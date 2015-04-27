<?php
/**
 * App.class.php
 * 
 * @author tomoaki.nagahara@gmail.com
 */

/**
 * include parent class
 */
if(!include_once('NewWorld5.class.php')){
	exit(0);
}

/**
 * App
 * 
 * @author tomoaki.nagahara@gmail.com
 */
class App extends NewWorld5
{
	/**
	 * SmatURL's key separate character 
	 * 
	 * @var string
	 */
	private $_args_keys_needle = ':';
	
	/**
	 * Get URL-Argument. (SmartURL)
	 * 
	 * (non-PHPdoc)
	 * @see NewWorld5::GetArgs()
	 */
	function GetArgs($key=null)
	{
		//	Accelerate
		static $args;
		if(!$args){
			$args = parent::GetArgs();
		}
		
		//	Return all
		if( is_null($key) ){
			return $args;
		}
		
		//	Return a specific value.
		if( is_int($key) ){
			//	at integer
			$result = isset($args[$key]) ? $args[$key]: null;
		}else{
			//	at key (ex. /foo/bar/color:red/ )
			$result = null;
			$needle = $this->_args_keys_needle;
			foreach( $args as $var ){
				if( strpos( $var, $needle ) ){
					$temp = explode( $needle, $var );
					if( $temp[0] === $key ){
						$result = $temp[1];
						break;
					}
				}
			}
		}
		
		return $result;
	}
	
	/**
	 * Get Action key name by URL.
	 * 
	 * @return string
	 */
	function GetAction()
	{
		$args = $this->GetArgs();
		$action = empty($args[0]) ? 'index': $args[0];
		return $action;
	}
	
	function SetConfig($file_name)
	{
		if( file_exists($file_name) ){
			include($file_name);
		}else{
			self::Mark("Does not exists this file. ($file_name)");
		}
	}
	
	function SetAdminIP($var)
	{
		return $this->SetEnv('admin-ip', $var);
	}
	
	function SetAdminEMail($var)
	{
		return $this->SetEnv('admin-mail', $var);
	}
	
	function SetControllerName( $var )
	{
		return $this->SetEnv('controller-name', $var);
	}
	
	function SetSettingName( $var )
	{
		return $this->SetEnv('setting-name', $var);
	}
	
	function SetModelDir( $var )
	{
		return $this->SetEnv('model-dir', $var);
	}

	function SetModuleDir( $var )
	{
		return $this->SetEnv('module-dir', $var);
	}
	
	function SetLayoutDir( $var )
	{
		$this->SetEnv('layout-dir', $var);
		return true;
	}
	
	function GetLayoutName()
	{
		return $this->GetEnv('layout');
	}
	
	function SetLayoutName( $var )
	{
		//	Set layout root. (full path)
		$layout_root = $this->GetEnv('layout-dir');
		$layout_root = $this->ConvertPath($layout_root);
		$this->SetEnv('layout-root',$layout_root.$var);
		
		return $this->SetEnv('layout', $var);
	}
	
	function SetLayoutPath( $var )
	{
		return $this->SetEnv('layout', $var);
	}
	
	function GetTemplateDir()
	{
		return $this->GetEnv('template-dir');
	}

	function SetTemplateDir( $var )
	{
		return $this->SetEnv('template-dir', $var);
	}
	
	function SetHtmlPassThrough( $var )
	{
		return $this->SetEnv('HtmlPassThrough', $var);
	}
	
	function SetTitle( $var )
	{
		$this->SetEnv('title', $var);
	}
	
	function GetTitle()
	{
		return $this->GetEnv('title');
	}
	
	function Title()
	{
		print '<title>'.$this->GetTitle('title').'</title>';
	}
	
	function SetDoctype( $var )
	{
		$this->SetEnv('doctype',$args);
	}
	
	function Doctype( $doctype=null, $version=null )
	{
		if(!$doctype){
			$doctype = $this->GetEnv('doctype');
		}
		
		switch($doctype){
			case 'xml':
				$doctype = '<?xml version="1.0" encoding="UTF-8"?>';
				break;
				
			case 'xhtml':
				$doctype = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN">';
				break;

			case 'html':
				if( $version == 4 or $version == '4.01' ){
					$doctype = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN">';
				}else{
					$doctype = '<!DOCTYPE html>';
				}
				break;
				
			default:
				$doctype = '<!DOCTYPE html>';
		}
		print $doctype.PHP_EOL;
	}
	
	function InitLang()
	{
		if( $lang = $this->GetCookie('lang') ){
			$this->SetLang($lang);
		}
	}
	
	function SetLang( $var )
	{
		if( $var ){
			$this->SetEnv('lang',$var);
			$this->SetCookie('lang', $var);
		}
	}
	
	function GetLang()
	{
		if(!$lang = $this->GetEnv('lang')){
			$lang = $this->GetCookie('lang');
		}
		return $lang;
	}
	
	function SetCharset( $var )
	{
		$this->SetEnv('charset',$var);
	}
	
	function GetCharset( $args=null )
	{
		return $this->GetEnv('charset');
	}
	
	function SetMime( $mime )
	{
		$this->SetEnv('mime',$mime);
	}
	
	function GetMime()
	{
		return $this->GetEnv('mime');
	}
	
	function AddKeyword( $var )
	{
		$this->AddKeywords( $var );
	}
	
	function AddKeywords( $var )
	{
		$keywords = $this->GetEnv('keywords');
		$keywords.= ", $var";
		$this->SetEnv('keywords',$keywords);
	}
	
	function SetKeyword( $var )
	{
		$this->SetEnv('keywords',$var);
	}
	
	function SetKeywords( $var )
	{
		$this->SetEnv('keywords',$var);
	}
	
	function GetKeywords()
	{
		return $this->GetEnv('keywords');
	}
	
	function Keywords()
	{
		print '<meta name="keywords" content="'.$this->GetKeywords().'">';
	}

	function SetDescription( $var )
	{
		$this->SetEnv('description',$var);
	}
	
	function GetDescription()
	{
		return $this->GetEnv('description');
	}
	
	function Description()
	{
		print '<meta name="description" content="'.$this->GetDescription().'">';
	}
	
	function SetNotFound($filepath)
	{
		$this->SetEnv(NewWorld5::_NOT_FOUND_, $filepath);
	}
	
	function SetMemcache($memcache)
	{
		$this->SetEnv('memcache',$memcache);
	}
	
	function GetMemcache()
	{
		if(!$memcache = $this->GetEnv('memcache') ){
			$memcache = new Config();
		}
		return $memcache;
	}
	
	function SetDatabase( $database )
	{
		$this->SetEnv('database',$database);
	}
	
	function GetDatabase()
	{
		if(!$database = $this->GetEnv('database') ){
			$database = new Config();
		}
		return $database;
	}
	
	function SetTablePrefix( $prefix )
	{
		$this->SetEnv('table_prefix',$prefix);
	}
	
	function En($english)
	{
		return $this->i18n()->En($english);
	}
	
	function Ja($japanese)
	{
		return $this->i18n()->Ja($japanese);
	}
	
	/**
	 * @return Model_Cloud
	 */
	function ModelCloud()
	{
		static $model;
		if(!$model){
			$model = $this->Model('Cloud');
		}
		return $model;
	}
	
	/**
	 * Import is wrapper of Template method.
	 * 
	 * @param  string $path
	 * @return Ambigous <string, boolean>
	 */
	function Import($path)
	{
		return $this->Template($path);
	}
	
	/**
	 * Wrapper of NewWorld5's Dispatch method.
	 * Do check of Admin-IP and Admin-Mail.
	 * 
	 * (non-PHPdoc)
	 * @see NewWorld5::Dispatch()
	 */
	function Dispatch($route=null)
	{
		$admin_ip	 = Env::Get('admin-ip');
		$admin_email = Env::Get('admin-mail');
		
		//	Checking Administrators settings.
		if(!Toolbox::isLocalhost() and (!$admin_ip or !$admin_email) ){
			$this->SetLayoutName(false);
			$path = $this->ConvertPath('op:/Template/introduction-app.phtml');
			$route['real_path']	 = $path;
			$route['extension']	 = 'phtml';
			$route['mime']		 = Router::CalcMime('phtml');
			$route['debug'][] = 'App have created a route table.';
			$route['debug'][] = __FILE__.', '.__METHOD__.', '.__LINE__;
		}else
		
		if( $this->Admin() ){
			$ext  = Router::CalcExtension($_SERVER['REQUEST_URI']);
			$mime = Router::CalcMime($ext);
			
			//	Only HTML
			if( $mime === 'text/html' ){
				//	Compare
				list($uri) = explode('?',$_SERVER['REQUEST_URI'].'?');
				$a = rtrim($this->CompressPath($uri), '/');
				$uri = rtrim($this->ConvertURL('app:/_self-test/'), '/');
				if(!preg_match("|^$uri|i",$_SERVER['REQUEST_URI'])){
					//	Transfer self-test page.
					$this->InitSelftest();
					if(!$this->Doctor()->Diagnose()){
						$this->Location('app:/_self-test');
					}
				}
			}
		}
		
		parent::Dispatch($route);
	}
	
	/**
	 * Set self-test model name.
	 * 
	 * @param array $model_name
	 */
	function SetSelftestModelName($model_name)
	{
		$args = $this->GetSelftestModelName();
		
		if(!$args){
			$args = array();
		}
		
		if( is_string($model_name)){
			if( strpos($model_name,',') ){
				$args = array_merge($args,explode(',', str_replace(' ','',$model_name)));
			}else{
				$args[] = $model_name;
			}
		}else{
			$args = $model_name;
		}
		$this->SetEnv('selftest-model-name',$args);
	}
	
	/**
	 * Get self-test model name.
	 * 
	 * @return array
	 */
	function GetSelftestModelName()
	{
		$list = $this->GetEnv('selftest-model-name');
		return $list ? $list: array();
	}
	
	/**
	 * Init Self-test config.
	 */
	function InitSelftest()
	{
		foreach( $this->GetSelftestModelName() as $model_name ){
			$model = $this->Model($model_name);
			
			$class_name = get_class($model);
			if( $class_name === 'OnePiece5'){
				$this->StackError("Failed instance. ($model_name)",'en');
				continue;
			}
			
			if(!method_exists($model,'Config') ){
				$this->StackError("Does not have Config method. ($model_name)",'en');
				continue;
			}
			
			$config = $model->Config();
			if(!method_exists($config,'selftest') ){
				$class_name = get_class($config);
				$this->StackError("Does not have selftest method. ($class_name)",'en');
				continue;
			}
			
			$data = $config->selftest();
			$this->Doctor()->Registration( $model_name, $data );
		}
	}
}
