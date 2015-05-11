<?php
/**
 * NewWorld5.class.php
 * 
 * @version   1.0
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2010 (C) Tomoaki Nagahara All right reserved.
 */

/**
 * NewWorld5
 * 
 * The NewWorld is the new world.
 * 
 * NewWorld's job is only to dispatch the index.php.
 * After dispatch to index.php, your freedom.
 * 
 * @version   1.0
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2010 (C) Tomoaki Nagahara All right reserved.
 */
abstract class NewWorld5 extends OnePiece5
{
	/**
	 * SetEnv key name, for not found page path.
	 * 
	 * @var string
	 */
	const _NOT_FOUND_PAGE_ = 'NotFoundPage';
	
	/**
	 * op-unit-selftest directory name.
	 * 
	 * @var string
	 */
	const _UNIT_URL_SELFTEST_ = '/_self-test/';
	
	/**
	 * routing table
	 * 
	 * @var array
	 */
	private $_routeTable = null;
	
	/**
	 * Use to check do already dispatched.
	 * 
	 * @var boolean
	 */
	private $_isDispatch = null;
	
	/**
	 * Content to be output is stored.
	 * 
	 * @var string
	 */
	private $_content = null;
	
	/**
	 * use to json.
	 * 
	 * save to assoc format.
	 * Converted to JSON-format when be output.
	 * 
	 * ex.
	 * $this->_json['key'] = $value;
	 * 
	 * @var array
	 */
	private $_json = null;
	
	/**
	 * MIME
	 * 
	 * @var string
	 */
	private $_mime = null;
	
	/**
	 * Start buffering.
	 * 
	 * @param array $args
	 */
	function __construct($args=array())
	{
		ob_start();
		
		//	Get Rewrite base.
		/*
		$patt = preg_quote($_SERVER['DOCUMENT_ROOT'],'|');
		$path = preg_replace("|^$patt|", '', $_SERVER['SCRIPT_FILENAME']);
		$_SERVER['REWRITE_BASE'] = rtrim(dirname($path),'/').'/';
		*/
		
		parent::__construct($args);
	}
	
	/**
	 * Finish buffering.
	 * 
	 * @see OnePiece5::__destruct()
	 */
	function __destruct()
	{
		//  Dispatched check.
		if(!$this->_isDispatch and strlen($this->_content)){
			$class_name = get_class($this);
			$message = "\\$class_name\ does not call the \Dispatch\ method.";
			$this->StackError($message,'en');
		}
		
		//	Get buffering content.
		$this->_content .= ob_get_contents();
		
		//  End of buffering.
		ob_end_clean();
		
		//	Output of content.
		echo $this->_content;
		
		//  Do parent destruct.
		return parent::__destruct();
	}
	
	/**
	 * For developper method.
	 */
	function Debug()
	{
		if(!$this->Admin() ){
			return;
		}
		$this->p('Debug of NewWorld5');
		$debug['route'] = Env::Get('route');
		$this->D($debug);
	}
	
	/**
	 * Dispatch to the End-Point by route table. (End-point is page-controller file) 
	 * 
	 * @param  array   $route
	 * @return boolean
	 */
	function Dispatch($route=null)
	{
		// Deny many times dispatch.
		if( $this->_isDispatch ){
			$this->StackError("Dispatched two times. (Dispatched only one time.)");
			return false;
		}else{
			$this->_isDispatch = true;
		}
		
		//	If route is emtpy, get route.
		if(!$route){
			$route = Router::GetRoute();
		}
		
		//	Save route table.
		Env::Set('route',$route);
		
		//	Execute end point program from route information.
		try{
			//	Get leaked content.
			$this->_content .= ob_get_contents(); ob_clean();
			
			//	Set default mime.
			$this->_mime = strtolower($route['mime']);
			
			//  Execute a end-point program.
			$this->Execute($route);
			
			//	Save to content buffer.
			$this->_content .= ob_get_contents(); ob_clean();
			
			//	Execute layout system.
			if( $this->_mime == 'text/html' ){
				$this->Layout();
			}else{
				$this->Content();
			}
		}catch( Exception $e ){
			$this->StackError($e);
		}
		
		return true;
	}
	
	/**
	 * Execute end-point file.
	 * 
	 * @param array $route
	 */
	function Execute($route)
	{
		//	Check file exists.
		if(!file_exists($route['real_path'])){
			$this->NotFound();
			return;
		}
		
		//	Get execute file.
		$file_path = $route['real_path'];
		
		//	Get end-point root.
		$ctrl_root = dirname($file_path);
		
		//	Change current directory.
		chdir( dirname($route['real_path']) );
		
		//  Execute.
		$this->Template($route['real_path']);
	}
	
	/**
	 * Output content type.
	 */
	function ContentType()
	{
		$mime = $this->_mime;
		$charset = $this->GetEnv('charset');
		$this->SetEnv('mime', $mime);
		header("Content-type: $mime; charset=\"$charset\"");
	}
	
	/**
	 * Output other header.
	 */
	function Headers()
	{
		/*
		header("Content-Security-Policy: default-src 'self'");
		header("Strict-Transport-Security: max-age=31536000; includeSubDomains"); // force https
		*/
		
		/* cache control
		header("Cache-Control: no-cache, no-store, must-revalidate");
		header("pragma: no-cache");
		*/
		
		/* permit cross domain
		header("Access-Control-Allow-Origin: http://www.example.com");
		header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
		header("Access-Control-Allow-Headers: X-TRICORDER");
		header("Access-Control-Max-Age: 1728000");
		*/
		
		header("X-Frame-Options: SAMEORIGIN");
		header("X-XSS-Protection: 1; mode=block");
		header("X-Permitted-Cross-Domain-Policies: master-only");
		header("X-Download-Options: noopen");
		
		//	Brower checking mime.
		header("X-Content-Type-Options: nosniff");
	}
	
	/**
	 * Execute of layout.
	 */
	function Layout()
	{
		static $layout = null;
		if(!$layout){
			$layout = new Layout();
		}
		
		//	Register of dispatcher.
		$layout->Dispatcher($this);
		
		//	Execute of layout.
		$layout->Execute();
	}
	
	/**
	 * Get buffering content.
	 * 
	 * @return string
	 */
	function GetContent()
	{
		return $this->_content;
	}
	
	/**
	 * Output of content.
	 */
	function Content()
	{
		list($main, $sub) = explode('/', $this->_mime);
		
		switch($main){
			case 'text':
				$this->ContentIsText($sub);
				break;
			case 'application':
				$this->ContentIsApplication($sub);
				break;
			default:
				$this->StackError("Does not support this mime. ({$main}/{$sub})");
		}
		
		//	Output content-type header.
		$this->ContentType();
		
		//	Other headers
		$this->Headers();
		
		//	Output content to stdout.
		print $this->_content;
		$this->_content = '';
	}
	
	function ContentIsText($sub)
	{
		switch($sub){
			//	json
			case 'json':
				$this->_doJson();
				break;
				
				//	plain text
			case 'csv':
			case 'plain':
				break;

			case 'javascript':
			case 'css':
			case 'html':
				//	If json.
				if( $this->_json ){
					Dump::D($this->_json);
				}
		
				//	If is admin.
				if( $this->Admin() ){
					//	Notice un exists file.
					if( isset($_SESSION[Router::_KEY_FILE_DOES_NOT_EXISTS_]) ){
						$path = $_SESSION[Router::_KEY_FILE_DOES_NOT_EXISTS_];
						$message = $this->i18n()->Bulk('This file does not exists.','en');
						$this->Mark("![.red[ $message ($path) ]]");
						unset($_SESSION[Router::_KEY_FILE_DOES_NOT_EXISTS_]);
					}
				}
				break;

			default:
				$this->StackError("Does not support this mime. (text/{$sub})");
		}
	}
	
	function ContentIsApplication($sub)
	{
		switch($sub){
			case 'javascript':
				$this->_doJson();
				break;
			default:
				$this->StackError("Does not support this mime. (application/{$sub})");
		}
	}
	
	function GetArgs()
	{
		$route = $this->GetEnv('route');
		if(!$route){
			$this->StackError("Route table is not initialized.",'en');
		}
		$args  = $route['args'];
		return $args;
	}
	
	function GetRequest( $keys=null, $method=null )
	{
		return Toolbox::GetRequest( $keys, $method );
	}
	
	function NotFound()
	{
		if( $page = $this->GetEnv(self::_NOT_FOUND_PAGE_) ){
			return $this->template($page);
		}else{
			if( $this instanceof App ){
				$example = '$this->SetNotFoundPage("filepath")';
			}else{
				$example = '$this->SetEnv(NewWrold5::_NOT_FOUND_PAGE_,"filepath")';
			}
			$this->StackError("NotFound-page has not been set. Please call this method: \\$example\.",'en');
		}
	}
	
	private function _doJson()
	{
		$this->_content .= ob_get_contents();
		ob_clean();
		
		if( $this->Admin() ){
			//	Help to debug information.
			if( strlen($this->_content) ){
				if( Toolbox::GetRequest('html') ){
					print $this->_content;
				}else{
					$this->_json['_LEAKED_CONTENT_'] = strip_tags($this->_content);
				}
			}
		}
		
		//	Execute
		if(!Toolbox::GetRequest('jsonp') ){
			$this->_content = json_encode($this->_json);
		}else{
			if(!$callback = Toolbox::GetRequest('callback') ){
				$callback = 'callback';
			}
			$this->_content = "{$callback}(".json_encode($this->_json).')';
		}
	}
	
	function SetJson( $key, $var )
	{
		static $init = null;
		
		if(!$init){
			$init = true;
			
			//	In case of debug.
			if( Toolbox::GetRequest('html') ){
				//	Debugging.
				$mime = 'text/html';
			}else{
				//	Change MIME.
				if( Toolbox::GetRequest('jsonp') ){
					$mime = 'application/javascript';
				}else{
					$mime = 'text/json';
				}
				
				//	Layout will off.
				Env::Set('layout',false);
			}

			//	Change MIME.
			$this->_mime = $mime;
		}
		
		//	Set json value.
		$this->_json[$key] = $var;
	}
	
	function GetJson( $key )
	{
		return isset($this->_json[$key]) ? $this->_json[$key]: null;
	}
	
	/**
	 * This language code is NewWorld's scope.
	 * Not system(PHP's multibyte function, timezone, etc), Not i18n(user use language code)
	 * NewWorld is use html tag. (<html lang="<?php $this->Lang() ?>">)
	 */
	private $_lang;
	
	/**
	 * Set html's language code.
	 * 
	 * @param string $lang
	 */
	function SetLang( $lang )
	{
		$this->_lang = $lang;
	}
	
	/**
	 * Get html's language code.
	 */
	function GetLang()
	{
		return $this->_lang ? $this->_lang: $this->GetEnv('lang');
	}
	
	/**
	 * print html's language code.
	 * 
	 * <html lang="<?php $this->Lang() ?>">
	 */
	function Lang()
	{
		print $this->GetLang();
	}
	
	/**
	 * This method will abolished.
	 * 
	 * @param  string $request_uri
	 * @return array|boolean
	 */
	function GetRoute($request_uri=null)
	{
		$this->StackError("This method will abolished. Please use \Router::GetRoute();\.",'en');
		return Router::GetRoute($request_uri);
	}
}
