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
	private $_content    = null;
	
	/**
	 * use to template. (data pass to template file)
	 * 
	 * ex.
	 * 
	 * // Controller
	 * $this->SetData('key',$value);
	 * 
	 * // Template
	 * $value = $this->GetData('key');
	 * 
	 * @var string|array|Config
	 */
	private $_data		 = array();
	
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
		$io = ob_start();
		$io = parent::__construct($args);
				
		//  Vivre
		$this->vivre(true);
		
		//  result
		return $io;
	}
	
	function __destruct()
	{	
		//  Called dispatch?
		if(!$this->_isDispatch){
		//	$class_name = get_class($this);
		//	$this->StackError($class_name.' has not dispatched. Please call $app->Dispatch();');
			Error::Set($class_name.' has not dispatched. Please call $app->Dispatch();');
			Error::Set($this->_isDispatch);
			Error::Set($_SERVER['REQUEST_URI']);
		}else{
			
		}
		
		//	mime
		$mime = strtolower($this->GetEnv('mime'));
		switch( $mime ){
			case 'csv':
			case 'text':
				//	CLI mode
				break;
					
			case 'json':
				if( $this->admin() ){
					if( $this->_content ){
						$this->SetJson('Error',$this->_content);
					}
				}
				$this->doJson();
				break;
					
			case 'html':
			default:
				
				//  flush buffer
				ob_end_flush();
				
				//  Check content
				if( $this->_content ){
					//	HTML mode
					$this->p('![ .big .red [Does not call ![ .bold ["Content"]] method. Please call to ![ .bold ["Content"]] method from layout.]]');
					$this->p('![ .big .red [Example: <?php $this->Content(); ?>]]');
					$this->content();
				}
				break;
		}
		
		//  Vivre
		$this->vivre(false);
		
		//  
		$io = parent::__destruct();
		
		return $io;
	}
	
	function Init()
	{
		parent::Init();
		
		$this->GetEnv('doctype','html');
		$this->GetEnv('title','The NewWorld is the new world');
	}
	
	/**
	 * Setup route table
	 * 
	 * @param string $request_uri
	 * @param array  $route
	 */
	function SetRoute($request_uri, $route)
	{
		list( $path, $query_string ) = explode('?',$request_uri);
		$route = $this->Escape($route);
		$this->_routeTable[md5($path)] = $route;
	}
	
	/**
	 * Determine the route to dispatch.
	 * 
	 * @param string $request_uri
	 * @return multitype:
	 */
	function GetRoute($request_uri=null)
	{
		//	get request uri
		if( $request_uri ){
			if( preg_match( '|^http://|', $request_uri ) ){
				$this->mark("![ .red [Domain name is not required. 
						Please specify document root path. ($request_uri)]]");
			}
		}else{
			$request_uri = $_SERVER['REQUEST_URI'];
		}
		
		//	separate query
		list( $path, $query_string ) = explode('?',$request_uri.'?');
		
		//	full path (support the alias path)
		$real = explode('/',dirname( str_replace($_SERVER['DOCUMENT_ROOT'],'',$_SERVER['SCRIPT_FILENAME']) ));
		$meta = explode('/',$path);
		$temp = array();
		for($i=0, $c=count($meta); $i<$c; $i++){
			$temp[] = !empty($real[$i]) ? $real[$i]: $meta[$i];
			if( empty($real[$i]) and empty($meta_root) ){
				$meta_root = join('/',$temp);
			}
		}
		$full_path = $_SERVER['DOCUMENT_ROOT'].join('/',$temp);
		
		// Does path exist? (in route table)
		if( $route = $this->_routeTable[md5($path)] ){
			return $route;
		}
		
		//  Real file is pass through.
		if( preg_match('/\/([-_a-z0-9\.]+)\.(html|css|js)$/i',$path,$match) ){
			if( $route = $this->HtmlPassThrough( $match, $full_path ) ){
				return $route;
			}
		}
		
		//	Admin Notification
		if( $this->admin() ){
			//	If there is extension
			if( preg_match('|\.[a-z0-9]{2,4}$|i', $full_path, $match ) ){
				if(!file_exists($full_path)){
					if( $this->Admin() ){
						$_file_does_not_exists_ = $this->GetSession('file_does_not_exists');
						$_file_does_not_exists_[] = $full_path;
						$this->SetSession('file_does_not_exists',$_file_does_not_exists_);
					}
					/**
					 * Is the URL specified file (with the extension), has been finished.
					 * But does it become an attack?
					 * 
					//	exit
					$this->SetEnv('cli',true);
					exit;
					*/
				}
			}
		}
		
		//	search controller
		$route = $this->_getController( $full_path );
		
		//	create app_root.(support apache's alias)
		$pattern   = rtrim($route['path'],'/').'/?';
		$pattern  .= rtrim(join('/',$route['args']),'/');
		$meta_root = rtrim(join('/',$meta),'/');
		$route['app_root'] = preg_replace("|$pattern$|",'',$meta_root).'/';
		
		//  escape
		$route = $this->Escape($route);
		
		return $route;
	}
	
	private function _getController( $file_path )
	{
		// controller file name
		if(!$controller = $this->GetEnv('controller-name')){
			throw new OpNwException('Does not set controller-name. Please call $app->SetEnv("controller-name","index.php");');
		}
		
		//	app-root
		$app_root = $this->GetEnv('App-Root');
		$app_root = rtrim($app_root,'/').'/';
		
		//	remove app-root
		$argument = preg_replace("|^$app_root|",'',$file_path);
		
		//	separate
		$dirs = explode( '/', rtrim($argument,'/') );
		$args = array();
		
		//  Loop
		while( count($dirs) ){
			//	Check if file exists.
			$file_name = $app_root.trim(join('/',$dirs)).'/'.$controller;
			if( file_exists($file_name) ){
				break;
			}
			//	Move tail's value.
			$args[] = array_pop($dirs);
		}
		
		// Suppression of notice error.
		if(!count($args)){
			$args[0] = null;
		}
		
		//	build route variable.
		$route = array();
		$route['path'] = '/'.join('/',$dirs);
		$route['file'] = $controller;
		$route['args'] = array_reverse($args);
		
		return $route;
	}
	
	function HtmlPassThrough( $match, $full_path )
	{
			//  file extension
			$extension = $match[2];
			
			//  access file name
			$file_name = $match[1].'.'.$match[2];
			
			//  current path is App path.
			$app_root = getcwd();
			
			//  document root path
			$doc_path = $_SERVER['DOCUMENT_ROOT'];
			
			//  create app path
			if( preg_match("|^$app_root(.+)|", $full_path, $match) ){
				$app_path = $match[1];
			}else if( preg_match("|^$doc_path(.+)|", $full_path, $match) ){
				$app_path = $match[1];
			}else{
				$app_path = $full_path;
			}
			
			$route = array();
			$route['app_root'] = $app_root;
			$route['fullpath'] = $full_path;
			$route['path'] = dirname($app_path);
			$route['file'] = $file_name;
			$route['args'] = array(null);
			$route['pass'] = true;
			$route['ctrl'] = null;
			$route = $this->Escape($route);
			
			//  full path is real path.
			$real_path = $route['fullpath'];
			
			//  file is exists?
			if( file_exists($real_path) ){
				
				switch( strtolower($extension) ){
					case 'html':
						if( $this->GetEnv('HtmlPassThrough') ){
							return $route;
						}else{
							$this->mark("![.red[HtmlPassThrough is off. please \$app->SetEnv('HtmlPassThrough',true);]]");
						}
						break;
						
					case 'css':
						$this->doCss($route);
						$this->_isDispatch = true;
						exit(0);
						
					case 'js':
						$this->doJs($route);
						$this->_isDispatch = true;
						exit(0);
					default:
						$this->mark("![.red[Does not match extension. ($extension)]]");
				}
			}
		
		return false;
	}
	
	function SetDispatchFlag($flag)
	{
		$this->_isDispatch = $flag;
	}
	
	function Dispatch($route=null)
	{
		// Deny two time dispatch
		if( $this->_isDispatch ){
			$this->StackError("Dispatched two times. (Dispatched only one time.)");
			return false;
		}else{
			$this->_isDispatch = true;
		}
		
		//	if route is emtpy, get route.
		if(!$route){
			if(!$route = $this->GetRoute()){
				return false;
			}
		}
		
		//	route info
		$this->SetEnv('route',$route);
		
		try{
			//	Flash buffer
			$this->_content  = ob_get_contents(); ob_clean();
			
			//	setting
			if(!$this->doSetting($route)){
				return true;
			}
			
			//	Forward
			if( $this->doForward() ){
				return true;
			}
			
			//	Reload route info
			$route = $this->GetEnv('route');
			
			//	Display to case of html.
			list($uri) = explode('?',$_SERVER['REQUEST_URI']);			
			if(!preg_match('/\.([-_a-z0-9]{2,5})$/i',$uri,$match) ){
				//	This file did not exist. (Warning to developer)
				if( $_file_does_not_exists_ = $this->GetSession('file_does_not_exists') ){
					if( $this->admin() ){
						$this->p("![.red .bold[This file is not exists.]]",'div');
						$this->d($_file_does_not_exists_);
					}
				}
				$this->SetSession('file_does_not_exists',null);
			}
			
			//  content
			$this->doContent();
			
			//	do wizard
			if( $this->admin() ){
				if( ob_start() ){
					$this->Wizard()->Selftest();
					$this->_content .= ob_get_contents();
					ob_end_clean();
				}else{
					$this->StackError("ob_start is failed. Does not execute selftest.");
				}
			}
			
		}catch( Exception $e ){
			$this->StackError($e);
		}
		
		//  layout
		try{
			$this->doLayout();
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
	function doContent()
	{	
		//  Route
		if(!$route = $this->GetEnv('route')){
			$this->StackError('Empty route.');
			return false;
		}

		// controller root
		$app_root = rtrim( $this->GetEnv('App-Root'), '/');
		$ctrl = isset($route['ctrl']) ? $route['ctrl']: $route['path'];
		$ctrl_root = rtrim($app_root . $ctrl, '/') . '/';
		$this->SetEnv('Ctrl-Root',$ctrl_root);
		
		// change dir
		$chdir = rtrim($app_root,'/') .'/'. trim($route['path'],'/');
		
		if( isset($route['pass']) and $route['pass'] ){
			chdir( dirname($route['fullpath']) );
		}else{
			if( file_exists($chdir) ){
				chdir( $chdir );
			}else{
				$this->StackError("Does not exists dir. ($chdir)");
			}
		}
		
		//  Controller file path.
		$path = getcwd().'/'.$route['file'];
				
		//	@see http://d.hatena.ne.jp/sen-u/20131130/p1
		header("X-Frame-Options: SAMEORIGIN");
		header("X-XSS-Protection: 1; mode=block");
		header("X-Permitted-Cross-Domain-Policies: master-only");
		header("X-Download-Options: noopen");
		header("X-Content-Type-Options: nosniff");
		
		//header("Content-Security-Policy: default-src 'self'");
		//header("Strict-Transport-Security: max-age=31536000; includeSubDomains"); // force https
		
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
		
		//	Execute controller.
		$this->_content .= $this->GetTemplate($path);
		
		return true;
	}
	
	function doSetting($route)
	{
		/**
		 * Search begins from AppRoot.
		 * settings-file is looked for forward Dispatch-dir, from AppRoot
		 */
		
		//  Get settings file name.
		if(!$setting = $this->GetEnv('setting-name') ){
			return true;
		}
		
		//  Get app root.
		$app_root = $this->GetEnv('AppRoot');
		$app_root = rtrim( $app_root, '/');
		
		//  Search settings file, and execute settings.
		$save_dir = getcwd();
		
		$io = true;
		foreach(explode('/', rtrim($route['path'],'/') ) as $dir){
			$dirs[] = $dir;
			$path = $app_root.join('/',$dirs)."/$setting";
			
			if( file_exists($path) ){
				chdir( dirname($path) );
				if(!$io = include($path) ){
					break;
				}
			}
		}
		
		//  Recovery current directory.
		chdir($save_dir);
		
		return $io ? true: false;
	}
	
	function doLayout()
	{
		//	get charset
		$charset = $this->GetEnv('charset');
		
		//	get mime
		$mime = $this->GetEnv('mime');
		
		//	not do layout.
		if( $mime != 'text/html' ){
			return true;
		}
		
		//	set header
		header("Content-type: $mime; charset=$charset");
		
		//  check the layout is set. 
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
		
		//  for debug
		if( 0 ){
			$this->d($_SERVER[Env::_ONE_PIECE_]);
			$temp['controller'] = $controller;
			$temp['layout']     = $layout;
			$temp['layout_dir'] = $layout_dir.' ('.$this->GetEnv('layout-dir').')';
			$temp['app-root']   = $this->GetEnv('app-root');
			$temp['proj-root']  = $this->GetEnv('proj-root');
			$temp['site-root']  = $this->GetEnv('site-root');
			$temp['ConvertPath']= $this->ConvertPath($layout) . ", layout=$layout";
			$temp['path']       = $path;
			$this->d($temp);
		}
		
		//  include controller
		if( file_exists($path) ){
			//  OK
			if(!include($path) ){
				throw new OpNwException("include is failed. ($path)");
			}
			if(!isset($_layout) or !count($_layout)){
				throw new OpNwException("Not set \$_layout variable. ($path)");
			}
		}else{
			//  NG
			print $this->_content;
			$msg = "Does not exists layout controller.($path)";
			throw new OpNwException($msg);
		//	$this->StackError($msg);
		//	return;
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
				$msg = "Does not exists layout file.($path)";
				throw new OpNwException($msg);
			//	$this->StackError($msg);
			//	return;
			} 
		}
		
		if( isset(${$file_name}) ){
			print ${$file_name};
		}else{
			$msg = "Does not set file name.($file_name)";
			throw new OpNwException($msg);
		//	$this->StackError($msg);
		//	return;
		}
	}
	
	function doCss($route)
	{
		//  Init garbage code. 
		ob_clean();
		
		//  Print headers.
		header("Content-Type: text/css");
		header("X-Content-Type-Options: nosniff");
		
		//  Change cli mode.
		$this->SetEnv('cli',true);
		$this->SetEnv('css',true);
		$this->SetEnv('mime','text/css');
		
		//  Execute.
		$this->template( $route['fullpath'] );
	}
	
	function doJs($route)
	{
		//  Init garbage code. 
	//	ob_clean();
		
		//  Print headers.
		header("Content-Type: text/javascript");
	//	header("X-Content-Type-Options: nosniff");
		
		//  Change cli mode.
		$this->SetEnv('cli',true);
		$this->SetEnv('js',true);
		$this->SetEnv('mime','text/javascript');
		
		//  Execute.
		$this->template( $route['fullpath'] );
	}
	
	function Header( $str, $replace=null, $code=null )
	{
		/*
		if( null === $replace ){
			switch($str){
				case 'hoge':
					$replace = false;
					break;
				default:
					$replace = true;
			}
		}
		*/
	
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
		
		/*
		$location = $this->GetSession('Location');
		if( $url === $location['referer'] ){
			$this->StackError("Redirect is roop. ($url)");
			return false;
		}
		*/
	
		$io = $this->Header("Location: " . $url);
		if( $io ){
			$location['message'] = 'Do Location!!' . date('Y-m-d H:i:s');
			$location['post']	 = $_POST;
			$location['get']	 = $_GET;
			$location['referer'] = $_SERVER['REQUEST_URI'];
			$this->SetSession( 'Location', $location );
			if($exit){
			//	$this->Vivre(false);
				$this->__destruct();
				exit(0);
			}
		}
		return $io;
	}
	
	/**
	 * Save the forward URL
	 * 
	 * @param string $url
	 */
	function SetForward( $url )
	{
		//	Reset forward URL
		if( empty($url) ){
			$this->SetEnv('forward', null);
			return;
		}
		
		//	Convert URL
		$url = $this->ConvertPath($url);
		$app_root = rtrim($this->GetEnv('app-root'),'/');
		$url = preg_replace( "|^$app_root|", '', $url );
		
		//	Save forward URL
		$this->SetEnv('forward', $url);
	}
	
	/**
	 * Execute forward from saved forward url.
	 * 
	 * @return boolean.
	 */
	function doForward()
	{
		//	Forward URL
		if(!$url = $this->GetEnv('forward')){
			return false;
		}
		
		//	Before route
		$route_old = $this->GetEnv('route');
		
		//	Get change route info.
		$route = $this->GetRoute($url);
		
		//	Compare
		if( $route == $route_old ){
			//	This is already been forwarding.
			return false;
		}
		
		//	Dispatched.
		$this->_isDispatch = false;
		$this->Dispatch($route);
		
		return true;
	}
	
	function GetContent()
	{
		return $this->_content;
	}
	
	function Content()
	{
		print $this->_content;
		$this->_content = '';
	}
	
	function GetArgs( $key=null )
	{
		$route = $this->GetEnv('route');
		
		if( is_null($key) ){
			return $route['args'];
		}
		
		if( is_int($key) ){
			$result = isset($route['args'][$key]) ? $route['args'][$key]: null;
		}else{
			$result = null;
			$needle = ':';
			foreach( $route['args'] as $var ){
				if( strpos( $var, $needle ) ){
					$temp = explode( $needle, $var );
					if( $temp[0] === $key ){
						$result = $temp[1];
					}
				}
			}
		}
		
		return $result;
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
	
	function doJson()
	{
	//	header('Content-type: application/json');
		header('Content-type: text/html');
		print json_encode($this->_json);
	}
	
	function SetJson( $key, $var )
	{
		static $init = null;
		if( !$init ){
			$this->SetEnv('cli',true);
			$this->SetEnv('mime','json');
		}
		$this->_json[$key] = $var;
	}
	
	function GetJson( $key )
	{
		return $this->_json[$key];
	}
	
	/**
	 *  Save temporary data pass to template inside.
	 * 
	 * @param string  $key
	 * @param mixed   $data
	 * @param boolean $session Save to session. (Load is once)
	 */
	function SetData( $key, $data, $session=false )
	{
		$this->_data[$key] = $data;
		if( $session ){
			$this->SetSession($key, $data);
		}
	}
	
	/**
	 * Get temporary data.
	 * 
	 * @param  string $key
	 * @return mixed
	 */
	function GetData( $key )
	{
		if( isset($this->_data[$key]) ){
			$data = $this->_data[$key];
		}else{
			$data = $this->GetSession($key);
			//	Load is once.
			$this->SetSession($key,null);
		}
		return $data;
	}
}

class OpNwException extends OpException
{
	
}


