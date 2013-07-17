<?php
/**
 * The NewWorld is the new world.
 * 
 * NewWorld's job is only to dispatch the index.php.
 * After dispatch to index.php, your freedom.
 * 
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 *
 */
abstract class NewWorld5 extends OnePiece5
{
	/**
	 * routing table
	 * 
	 * @var array
	 */
	/*
	private $isDispatch = null;
	private $routeTable = null;
	private $content    = null;
	private $_data      = null;
	*/
	private $_isDispatch = null;
	private $_routeTable = null;
	private $_content    = null;
	private $_data		 = array();
	private $_log		 = null;
	
	function __construct($args=array())
	{
		//	Log
		if( $this->_log){ $this->_log[] = __METHOD__; }
		
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
		//	Log
		if( $this->_log ){ $this->_log[] = __METHOD__; }
		
		//  Called dispatch?
		if(!$this->_isDispatch){
			$this->StackError('App has not dispatched. Please call $app->Dispatch();');
		}
		
		//  flush buffer
		ob_end_flush();
		
		//  Check content
		if( $this->_content ){
			$this->p('![ .big .red [Does not call ![ .bold ["Content"]] method. Please call to ![ .bold ["Content"]] method from layout.]]');
			$this->p('![ .big .red [Example: <?php $this->Content(); ?>]]');
			$this->Content();
		}
		
		//  Vivre
		$this->vivre(false);
		
		//  
		$io = parent::__destruct();
		
		//	Log
		if( $this->_log ){ $this->d( $this->_log ); }
		
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
		@list( $path, $query_string ) = explode('?',$request_uri);
		$route = $this->Escape($route);
		$this->_routeTable[md5($path)] = $route;
	}
	
	/**
	 * 
	 * 
	 * @param string $request_uri
	 * @return multitype:
	 */
	function GetRoute($request_uri=null)
	{
		// get request uri
		if( $request_uri ){
			if( preg_match( '|^http://|', $request_uri ) ){
				$this->mark("![ .red [Domain name is not required. 
						Please Document root path. ($request_uri)]]");
			}
		}else{
			$request_uri = $_SERVER['REQUEST_URI'];
		}
		
		// separate query
		list( $path, $query_string ) = explode('?',$request_uri.'?');
		$full_path = $_SERVER['DOCUMENT_ROOT'] . $path;
		
		// Does path exist?
		if( $route = $this->_routeTable[md5($path)] ){
			return $route;
		}
		
		//  Real file is pass through.
		if( preg_match('/\/([-_a-z0-9]+)\.(html|css|js)$/i',$path,$match) ){
			if( $route = $this->HtmlPassThrough( $match, $full_path ) ){
				return $route;
			}
		}
		
		// separate query
		list( $path, $query_string ) = explode('?',$request_uri.'?');
		
		// create absolute path
		$absolute_path = $_SERVER['DOCUMENT_ROOT'] . $path;
		
		//$app_root = getcwd();
		$app_root = $this->GetEnv('AppRoot');
		
		//	absolute from current dir
		$file_path = preg_replace("|$app_root|",'',$absolute_path);
		
		//	search controller
		$this->_getController( $dirs, $args, $file_path, $controller );
		
		//  build
		$route['path'] = '/'.join('/',$dirs);
		$route['file'] = $controller;
		$route['args'] = array_reverse($args);
				
		//  escape
		$route = $this->Escape($route);
		
		return $route;
	}
	
	private function _getController( &$dirs, &$args, $file_path, &$controller )
	{
		// controller file name
		if(!$controller = $this->GetEnv('controller-name')){
			$m = 'Does not set controller-name. Please call $app->SetEnv("controller-name","index.php");';
		//	$this->StackError($m);
			throw new OpNwException($m);
			
			/*
			$dirs = array();
			$args = array();
			return false;
			*/
		}
		
		//  Init
		$app_root = $this->GetEnv('AppRoot');
		$app_root = rtrim($app_root,'/').'/';
		$dirs = explode( '/', rtrim($file_path,'/') );
		$args = array();
		
		//  Loop
		while( count($dirs) ){
			
			$file_name = $app_root.trim(join('/',$dirs)).'/'.$controller;
				
			if( file_exists($file_name) ){
				break;
			}
				
			$args[] = array_pop($dirs);
		}
		
		// anti nortice error
		if(!count($args)){
			$args[0] = null;
		}
		
		return true;
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
						exit(0);
						
					case 'js':
						$this->doJs($route);
						exit(0);
					default:
						$this->mark("![.red[Does not match extension. ($extension)]]");
				}
			}
		
		return false;
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
			
			//	Check selftest
			$config = isset($_SESSION['OnePiece5']['_selftest']) ? $_SESSION['OnePiece5']['selftest']: null;
			
			if( $config ){
			
				$wz = new Wizard();
				$io = $wz->Selftest( $config );
				
				if( $io ){
					$_SESSION['OnePiece5']['selftest'] = null;
				}
			}
		
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
			
			//  content
			$this->doContent();
			
		}catch( OpWzException $e ){
			
			//	Begin the Wizard.
			$config = $e->GetConfig();
			$wz = new Wizard();
			$io = $wz->DoWizard($config);
			if( $io ){
				$this->p("Wizard is successful. Please reload this page.");
			}else{
				$wz->PrintForm( $config->form );
			}
			
			//	Join the content.
			$this->_content  = ob_get_contents(); ob_clean();
			
		}catch( Exception $e ){
			$this->StackError($e);
		}
		
		//  layout
		$this->doLayout();
		
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
		$app_root = rtrim( $this->GetEnv('AppRoot'), '/');
		$ctrl = isset($route['ctrl']) ? $route['ctrl']: $route['path'];
		$ctrl_root = rtrim($app_root . $ctrl, '/') . '/';
		$this->SetEnv('Ctrl-Root',$ctrl_root);
		
		// change dir
		$chdir = rtrim($app_root,'/') .'/'. trim($route['path'],'/');
		
		if( isset($route['pass']) and $route['pass'] ){
			//	$this->mark( $chdir );
			chdir( dirname($route['fullpath']) );
			//	$this->mark( getcwd() );
		}else{
			chdir( $chdir );
		}
		
		//  Controller file path.
		$path = getcwd().'/'.$route['file'];

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

		//	Log
		if( isset($this->_log) ){ $this->_log[] = __METHOD__.", {$route['path']}"; }
		
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
		//  check the layout is set. 
		if(!$layout = $this->GetEnv('layout') ){
			if(is_null($layout)){
				//  Does not set layout.
				if( $this->admin() ){
					$this->p("![ .gray .small [Hint: layout uses \$app->SetEnv('layout','app:/path/to/your/self')]]");
				}
			}
			return;
		}
		
		//  get controller name
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
			if(!isset($_layout)){
				throw new OpNwException("Not set \$_layout variable.");
			}
		}else{
			//  NG
			print $this->_content;
			$m = "does not exists layout controller.($path)";
			$this->StackError( $m,'layout');
			throw new OpNwException($m);
			return;
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
				$this->StackError("does not exists layout file.($path)");
			}
		}
		
		print ${$file_name};
	}
	
	function doCss($route)
	{
		//  Init garbage code. 
		ob_clean();
		
		//  Print headers.
		header("Content-Type: text/css");
		header("X-Content-Type-Options: nosniff");
		
		//  Full path of file.
		$path = $_SERVER['DOCUMENT_ROOT'].$route['path'].'/'.$route['file'];
		
		//  Change cli mode.
		$this->SetEnv('cli',true);
		$this->SetEnv('css',true);
		
		//  Execute.
		$this->template( $path );
		exit(0);
	}
	
	function doJs($route)
	{
		$this->SetEnv('cli',true);
		exit(0);
	}
	
	function Header( $str, $replace=null, $code=null )
	{
		if( null === $replace ){
			switch($str){
				case 'hoge':
					$replace = false;
					break;
				default:
					$replace = true;
			}
		}
	
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
	 * @param unknown $url
	 * @param string $exit
	 * @return void|boolean
	 */
	function Location( $url, $exit=true )
	{
		//	Document root path
		$url = $this->ConvertUrl($url,false);
		
		//	Check infinity loop.
		$temp = explode('?',$_SERVER['REQUEST_URI']);
		if( $io = rtrim($url,'/') == rtrim($temp[0],'/') ){
			$this->mark("Infinith loop");
			if( $this->_log ){ $this->_log[] = __METHOD__.", Infinith loop."; }
			return false;
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
		//	Log
		if( isset($this->_log) ){ $this->_log[] = __METHOD__.", $url"; }
		
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
		//	Log
		if( isset($this->_log) ){ $this->_log[] = __METHOD__; }
		
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
	
	function GetArgs()
	{
		$route = $this->GetEnv('route');
		return $route['args'];
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
	
	/**
	 * Save temporary data pass to template inside.
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


