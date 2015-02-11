<?php
/**
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
	private $_json		 = null;
	
	function __construct($args=array())
	{
		//  output is buffering.
		if(!ob_start()){
			print __FILE__.', '.__LINE__;
		}
		
		//	Get Rewrite base.
		$patt = preg_quote($_SERVER['DOCUMENT_ROOT'],'|');
		$path = preg_replace("|^$patt|", '', $_SERVER['SCRIPT_FILENAME']);
		$_SERVER['REWRITE_BASE'] = rtrim(dirname($path),'/').'/';
		
		parent::__construct($args);
	}
	
	function __destruct()
	{
		//  Called dispatch?
		if(!$this->_isDispatch){
			$class_name = get_class($this);
			$message = "$class_name has not dispatched. Please call \$app->Dispatch();'";
			$this->StackError($message);
		}
		
		//  flush buffer
		ob_end_flush();
		
		//  Check content
		if( $this->_content ){
			//	HTML mode
			$this->p('![ .big .red [Does not call ![ .bold ["Content"]] method. Please call to ![ .bold ["Content"]] method from layout.]]');
			$this->p('![ .big .red [Example: <?php $this->Content(); ?>]]');
			$this->content();
		}
		
		//  
		$io = parent::__destruct();
		
		return $io;
	}
	
	function Init()
	{
		parent::Init();
		
		//	Set default value
		$this->GetEnv('doctype','html');
		$this->GetEnv('title','The NewWorld is the new world');
	}
	
	/**
	 * Dispatch to the End-Point(End-point is page-controller file) by route arguments.
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
		
		//	Save route.
		Env::Set('route',$route);
		
		//	Execute end point program from route information.
		try{
			//	Flash buffer 
			$this->_content .= ob_get_contents(); ob_clean();
			
			//  Execute end point program.
			$this->_doContent($route);
			
			//	Save to content buffer.
			$this->_content .= ob_get_contents(); ob_clean();
			
			//	Switch
			if( Toolbox::isHtml() ){
				//	If content-type is html.
				$this->_doWizard();
				$this->_doLayout();
			}else{
				//	Case of css and js, output of content buffer.
				$this->Content();
			}
		}catch( Exception $e ){
			$this->StackError($e);
		}
		
		return true;
	}
	
	/**
	 * Execute controller.
	 * 
	 * @return boolean
	 */
	private function _doContent($route)
	{
		//	Get controller root
		$ctrl_root = dirname($route['full_path']);
		
		//	Check exists controller root
		if(!$io = file_exists($ctrl_root)){
			$_SESSION[Router::_KEY_FILE_DOES_NOT_EXISTS_] = $ctrl_root;
			return false;
		}
		
		//	Save controller root.
		$this->SetEnv('Ctrl-Root',$ctrl_root);
		
		//	Change current directory.
		chdir($ctrl_root);
		
		//	@see http://d.hatena.ne.jp/sen-u/20131130/p1
		header("X-Frame-Options: SAMEORIGIN");
		header("X-XSS-Protection: 1; mode=block");
		header("X-Permitted-Cross-Domain-Policies: master-only");
		header("X-Download-Options: noopen");
		header("X-Content-Type-Options: nosniff");
		
	//	header("Content-Security-Policy: default-src 'self'");
	//	header("Strict-Transport-Security: max-age=31536000; includeSubDomains"); // force https
		
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
		
		switch( $route['extension'] ){
			case 'css':
				header("Content-Type: text/css");
				Env::Set('mime','text/css');
				Env::Set('cli',true);
				Env::Set('css',true);
				break;
				
			case 'js':
				header("Content-Type: text/javascript");
				Env::Set('mime','text/javascript');
				Env::Set('cli',true);
				Env::Set('js',true);
				break;
				
			default:
		}
		
		//  Execute.
		$this->Template( $route['full_path'] );
		
		return true;
	}
	
	private function _doLayout()
	{
		//  Check layout value.
		if(!$layout = $this->GetEnv('layout') ){
			if(is_null($layout)){
				//  Does not set layout.
				if( $this->admin() ){
					$this->p("![ .gray .small [Hint: layout uses \$app->SetEnv('layout','app:/path/to/your/self')]]");
				}
				$io = false;
			}else if(empty($layout)){
				$io = true;
				print $this->Content();
			}else{
				$io = false;
			}
			return $io;
		}
		
		//	get charset
		$charset = $this->GetEnv('charset');
		
		//	get mime
		$mime = $this->GetEnv('mime');
		
		//	set header
		header("Content-type: $mime; charset=$charset");
		
		//  get controller name (layout controller)
		$controller = $this->GetEnv('controller-name');
		
		//  check the layout-directory is set.
		if( $layout_dir = $this->GetEnv('layout-dir') ){
			//  layout has been set.
			$layout_dir = $this->ConvertPath($layout_dir);
			$path = rtrim($layout_dir,'/') .'/'. $layout .'/'. $controller;
		}else{
			$path = $this->ConvertPath($layout) .'/'. $controller;
		}
		
		//  include controller
		if( file_exists($path) ){
			//  OK
			if(!include($path) ){
				throw new OpException("include is failed. ($path)");
			}
			if(!isset($_layout) or !count($_layout)){
				throw new OpException("Not set \$_layout variable. ($path)");
			}
		}else{
			//  NG
			print $this->_content;
			throw new OpException("Does not exists layout controller.($path)");
		}
		
		//  layout directory
		$layout_dir = dirname($path) . '/';
		
		//  do layout
		foreach($_layout as $var_name => $file_name){
			$path = $layout_dir . $file_name;
			
			if( file_exists($path) ){
				ob_start();
				$this->mark($path,'layout');
				
				
				include($path);
				
				
				${$file_name} = ob_get_contents();
				${$var_name}  = & ${$file_name};
				ob_end_clean();
			}else{
				$this->StackError("Does not exists layout file.($path)");
			}
		}
		
		if( isset(${$file_name}) ){
			print ${$file_name};
		}else{
			$msg = "Does not set file name.($file_name)";
			throw new OpException($msg);
		}
	}
	
	private function _doWizard()
	{
		//	do wizard
		if( $this->admin() ){
			if( ob_start() ){
				$this->Wizard()->Selftest();
				$this->_content .= ob_get_contents();
				ob_end_clean();
			}else{
				$this->StackError("\ob_start\ was failed. Does not run selftest.",'en');
			}
		}
	}
	
	function Header( $str, $replace=null, $code=null )
	{
		if( headers_sent() ){
			$io = false;
			$this->StackError("already header sent.");
		}else{
			$io = true;
			$str = str_replace( array("\n","\r"), '', $str );
			header( $str, $replace, $code );
		}
		
		return $io;
	}
	
	/**
	 * Forward local location.(not external URL)
	 * 
	 * @param string  $url  transfer url.
	 * @param boolean $exit default is true.
	 * @return void|boolean
	 */
	function Location( $url, $exit=true )
	{
		//	Document root path
		$url = $this->ConvertUrl($url,false);
		
		//	Check infinity loop.
		if( $_SERVER['REQUEST_METHOD'] == 'POST' ){
			//	Does not for infinity.
		}else{
			$temp = explode('?',$_SERVER['REQUEST_URI']);
			if( $io = rtrim($url,'/') == rtrim($temp[0],'/') ){
				$this->mark("Location is Infinite loop. ($url)");
				return false;
			}
		}
		
		$io = $this->Header("Location: " . $url);
		if( $io ){
			$location['message'] = 'Do Location!!' . date('Y-m-d H:i:s');
			$location['post']	 = $_POST;
			$location['get']	 = $_GET;
			$location['referer'] = $_SERVER['REQUEST_URI'];
			$this->SetSession( 'Location', $location );
			if($exit){
				$this->__destruct();
				exit(0);
			}
		}
		return $io;
	}
	
	function GetContent()
	{
		return $this->_content;
	}
	
	function Content()
	{
		switch( $mime = strtolower(Toolbox::GetMIME(true)) ){
			//	plain text
			case 'csv':
			case 'plain':
			case 'json':
				$this->_doJson();
				break;
				
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
				
			default:
			//	end of default
		}
		
		//	Output content to stdout.
		print $this->_content;
		$this->_content = '';
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
		if( $page = $this->GetEnv('NotFound') ){
			return $this->template($page);
		}else{
			$this->StackError('Does not set env "NotFound" page path. Please call $this->SetEnv("NotFound").');
		}
	}
	
	/*
	function doJson($is_get=null)
	{
		if( $this->admin() ){
			if( strlen($this->_content) ){
				if( Toolbox::GetRequest('html') ){
					print $this->_content;
				}else{
					$this->_json['_LEAKED_CONTENT_'] = strip_tags($this->_content);
				}
				$this->_content = '';
			}
		}
		if( $is_get ){
			return $this->_json;
		}else if($this->_json){
			print json_encode($this->_json);
		}
	}
	*/
	
	private function _doJson()
	{
		if( $this->Admin() ){
			//	Help to debug information.
			if( strlen($this->_content) ){
				if( Toolbox::GetRequest('html') ){
					print $this->_content;
				}else{
					$this->_json['_LEAKED_CONTENT_'] = strip_tags($this->_content);
				}
				$this->_content = '';
			}
		}
		
		print json_encode($this->_json);
	}
	
	function SetJson( $key, $var )
	{
		static $init;
		if(!$init){
			$init = true;
			if(!$html = Toolbox::GetRequest('html')){
				Toolbox::SetMIME('text/plain');
			}
			Env::Set('layout',false);
		}
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

/**
 * Router
 * 
 * creation: 2015-01-30
 * 
 * @version   1.0
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2015 (C) Tomoaki Nagahara All right reserved.
 */
class Router extends OnePiece5
{
	static function SetRoute($route)
	{
		Env::Set('route',$route);
	}

	/**
	 * Determine the route to dispatch from request-uri.
	 *
	 * @param  string $request_uri
	 * @return array
	 */
	static function GetRoute($request_uri=null)
	{
		if(!$route = Env::Get('route')){
			$route = self::CalcRoute($request_uri);
		}
		return $route;
	}
	
	static function CalcRoute($request_uri=null)
	{
		//	Get request uri.
		if(!$request_uri){
			$request_uri = $_SERVER['REQUEST_URI'];
		}

		//	Sanitize.
		$request_uri = self::Escape($request_uri);
		
		//	Separate query.
		list( $request_uri, $query_string ) = explode('?',$request_uri.'?');
		
		//	
		$route = self::_GetRouteAsBase($request_uri);
		
		//	If file have extension.
		if( $route['extension'] ){
			//	If file extension as css or js.
			if( ($route['extension'] === 'css' or $route['extension'] === 'js') ){
				$_is_html_pass_through = true;
			}else{
				//	If file extension is html or other.
				if( Env::Get('HtmlPassThrough') ){
					$_is_html_pass_through = true;
				}else{
					self::Mark('![.red[HtmlPassThrough is off. please $this->SetEnv("HtmlPassThrough",true);]]');
				}
			}
		}
		
		//	This is not html pass through.
		if( empty($_is_html_pass_through) ){
			$route = self::_GetRouteAsController($route);
		}
		
		//	Admin Notification
		if( self::admin() ){
			self::_CheckFileExists($route['full_path']);
		}
		
		return $route;
	}
	
	static private function _GetRouteAsBase($request_uri)
	{
		//	Application root
		$app_root = dirname($_SERVER['SCRIPT_FILENAME']).'/';
		
		//	Check extension.
		if( preg_match('/\/([-_a-z0-9\.]+)\.(html|css|js)$/i',$request_uri,$match) ){
			$file_name = $match[1].'.'.$match[2];
			$extension = strtolower($match[2]);
		}else{
			$file_name = null;
			$extension = null;
		}
		
		$route['app_root']  = $app_root;
		$route['full_path'] = $_SERVER['DOCUMENT_ROOT'].$request_uri;
		$route['file_name'] = $file_name;
		$route['extension'] = $extension;
		
		return $route;
	}
	
	/**
	 * Search of end-point. (end-point is page-controller)
	 * 
	 */
	static private function _GetRouteAsController($route)
	{
		// controller file name
		if(!$controller = Env::Get('controller-name')){
			throw new OpException('Does not set controller-name. Please call $app->SetEnv("controller-name","index.php");');
		}
		
		//	init
		$arr = explode('/',$route['full_path']);
		$dirs = array();
		$args = array();
		
		//	search controller
		while(count($arr)){
			$path = join('/',$arr).'/'.$controller;
			if( file_exists($path) ){
				break;
			}
			$args[] = array_pop($arr);
		}
		
		//	anti-notice
		if(empty($args)){
			$args[] = null;
		}
		
		//	search app.php
		while(count($arr)){
			$path = join('/',$arr).'/app.php';
			if( file_exists($path) ){
				break;
			}
			$dirs[] = array_pop($arr);
		}
		
		//	build route table.
		$route['path'] = '/'.trim(join('/',array_reverse($dirs)),'/').'/';
		$route['file'] = $controller;
		$route['args'] = array_reverse($args);
		$route['full_path'] = rtrim($route['app_root'],'/').$route['path'].$route['file'];
		
		return $route;
	}
	
	const _KEY_FILE_DOES_NOT_EXISTS_ = 'file_does_not_exists';
	
	static function _CheckFileExists($full_path)
	{
		//	If there is extension
		if( preg_match('|\.[a-z0-9]{2,4}$|i', $full_path, $match ) ){
			if(!file_exists($full_path)){
				$_SESSION[self::_KEY_FILE_DOES_NOT_EXISTS_] = $full_path;
			}
		}
	}
}
