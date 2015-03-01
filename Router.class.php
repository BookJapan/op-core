<?php
/**
 * Router.class.php
 * 
 * creation: 2015-02-27
 * 
 * @version   1.0
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2015 (C) Tomoaki Nagahara All right reserved.
 */
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
	/**
	 * Set route table.
	 * 
	 * @param array $route
	 */
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
	
	/**
	 * Calculate route to end-point file.
	 * 
	 * @param  string $request_uri
	 * @return array  $route
	 */
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
			self::_CheckFileExists($route['real_path']);
		}
		
		return $route;
	}
	
	/**
	 * Build base route table.
	 * 
	 * @param  string $request_uri
	 * @return array  $route
	 */
	static private function _GetRouteAsBase($request_uri)
	{
		//	init
		$app_root = dirname($_SERVER['SCRIPT_FILENAME']);
		$doc_root = $_SERVER['DOCUMENT_ROOT'];
		
		//	Check extension.
		if( preg_match('/\/([-_a-z0-9\.]+)\.(html|css|js)$/i',$request_uri,$match) ){
			$file_name = $match[1].'.'.$match[2];
			$extension = strtolower($match[2]);
		}else{
			$file_name = null;
			$extension = null;
		}
		
		//	Check alias.
		if( preg_match("|^($doc_root)(.*)|",$app_root,$match) ){
			//	not alias
			$is_alias = false;
			$real_app_dir = $match[1];
			$rewrite_base = $match[2] ? $match[2]: '/';
		}else{
			//	alias
			$is_alias = true;
			$rewrite_base = dirname($_SERVER['SCRIPT_NAME']).'/';
			$real_app_dir = dirname($_SERVER['SCRIPT_FILENAME']);
		}
		
		//	Build route data.
		$arguments    = preg_replace("|^$rewrite_base|", '', $_SERVER['REQUEST_URI']);
		list($smart_url) = explode('?',$arguments.'?');
		
		//	Build real path
		if(!$is_alias ){
			//	not alias
			$real_path = $real_app_dir . $rewrite_base . $smart_url;
		}else{
			//	alias
			$real_path = $real_app_dir .'/'. $smart_url;
		}
		
		/*
		//	debug
		$route['match'] = $match;
		$route['DOCUMENT_ROOT']		 = $doc_root;
		$route['APP_ROOT']			 = $app_root;
		$route['SCRIPT_FILENAME']	 = $_SERVER['SCRIPT_FILENAME'];
		$route['SCRIPT_NAME']		 = $_SERVER['SCRIPT_NAME'];
		
		$route['$real_app_dir'] = $real_app_dir;
		$route['$arguments'] = $arguments;
		$route['$real_path'] = $real_path;
		*/
		
		//	Build base route table
		$route['alias']		 = $is_alias ? 1: 0;
		$route['rewrite_base'] = $rewrite_base;
		$route['app_root']	 = $app_root;
		$route['meta_path']	 = $_SERVER['DOCUMENT_ROOT'].$request_uri;
		$route['real_path']	 = $real_path;
		$route['file_name']	 = $file_name;
		$route['extension']	 = $extension;
		$route['arguments']	 = $arguments;
		$route['smart_url']	 = $smart_url;
		
		//	SET REWRITE_BASE
		$_SERVER['REWRITE_BASE'] = rtrim($rewrite_base,'/').'/';
		
		return $route;
	}
	
	/**
	 * Search of end-point by route table. (end-point is page-controller)
	 * 
	 * @param  array $route
	 * @return array
	 */
	static private function _GetRouteAsController($route)
	{
		// controller file name
		if(!$controller = Env::Get('controller-name')){
			throw new OpException('Does not set controller-name. Please call $app->SetEnv("controller-name","index.php");');
		}
		
		//	init
		$arr = explode('/',$route['real_path']);
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
		
		//	path
		if( count($dirs) ){
			$path = '/'.join('/',array_reverse($dirs)).'/';
		}else{
			$path = '/';
		}
		
		//	full path
		$real_path = rtrim($route['app_root'],'/') . $path . $controller;
		
		//	build route table.
		$route['path'] = $path;
		$route['file'] = $controller;
		$route['args'] = array_reverse($args);
		$route['real_path'] = $real_path;
		
		return $route;
	}
	
	/**
	 * Used to _CheckFileExists method.
	 * 
	 * @var string
	 */
	const _KEY_FILE_DOES_NOT_EXISTS_ = 'file_does_not_exists';
	
	/**
	 * Do check of file exists.
	 * 
	 * @param string $real_path
	 */
	static function _CheckFileExists($real_path)
	{
		//	If there is extension
		if( preg_match('|\.[a-z0-9]{2,4}$|i', $real_path, $match ) ){
			if(!file_exists($real_path)){
				$_SESSION[self::_KEY_FILE_DOES_NOT_EXISTS_] = $real_path;
			}
		}
	}
}
