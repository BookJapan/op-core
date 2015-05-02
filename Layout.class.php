<?php
/**
 * Layout.class.php
 * 
 * @creation  2015-04-24
 * @version   1.0
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2015 (C) Tomoaki Nagahara All right reserved.
 */

/**
 * Layout
 * 
 * @creation  2015-04-24
 * @version   1.0
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2015 (C) Tomoaki Nagahara All right reserved.
 */
class Layout extends OnePiece5
{
	/**
	 * Directory where layout is in.
	 * 
	 * @return string
	 */
	private function _GetLayoutDir()
	{
		if(!$layout_dir = $this->GetEnv('layout-dir')){
			if(is_null($layout_dir)){
				//  Does not set layout.
				if( $this instanceof App ){
					$method = "\$app->SetLayoutDir('your-use-layout-directory');";
				}else{
					$method = "\$this->SetEnv('layout-dir','app:/path/to/your/self');";
				}
				//	Stack Error
				$this->StackError("Layout directory is null, Use to \\$method\.",'en');
			}
		}
		return $layout_dir;
	}
	
	/**
	 * To be used.
	 * 
	 * @return string
	 */
	private function _GetLayout()
	{
		if(!$layout = $this->GetEnv('layout') ){
			if(is_null($layout)){
				//  Does not set layout.
				if( $this instanceof App ){
					$method = "\$app->SetLayoutName('your-use-layout-name');";
				}else{
					$method = "\$this->SetEnv('layout','app:/path/to/your/self');";
				}
		
				//	Stack Error
				$this->StackError("Layout name is null, Use to \\$method\.",'en');
			}
		}
		return $layout;
	}
	
	/**
	 * Call from layout class inner method.
	 */
	function Content()
	{
		$this->Dispatcher()->Content();
	}
	
	/**
	 * Execute layout.
	 * 
	 * @return boolean
	 */
	function Execute($execute_file=null)
	{
		if(!$execute_file){
			//	Get layout settings.
			$layout_dir	 = $this->_GetLayoutDir();
			$layout		 = $this->_GetLayout();
			
			//	Do not want to layout. (False is if not want layout.)
			if( $layout === false ){
				$this->Content();
				return;
			}
			
			//  Get controller name (layout controller)
			$controller = $this->GetEnv('controller-name');
			
			//	Get layout controller path.
			$execute_file = rtrim($layout_dir,'/')."/{$layout}/{$controller}";
			$execute_file = $this->ConvertPath($execute_file);
		}
		
		if(!file_exists($execute_file)){
			$this->StackError("Does not exists this file. \($layout_path)\\",'en');
			return false;
		}
		
		//	Execute layout controller.
		include($execute_file);
		
		//  Rebuild layout directory.
		$layout_dir = dirname($execute_file) . '/';
		
		//	Execute each part.
		foreach($_layout as $var_name => $file_name){
			//	Build path.
			$path = $layout_dir . $file_name;
			
			if(!file_exists($path)){
				$this->StackError("Does not exists layout file. \($path)\\",'en');
				return false;
			}
			
			//	Assembly of layout.
			ob_start();
			try{
				$this->mark($path,'layout');
				include($path);
				${$file_name} = ob_get_contents();
				${$var_name}  = & ${$file_name}; // TODO: & <- ???
			}catch( Exception $e ){
				$text = $e->getMessage();
				if( method_exists($e,'getLang') ){
					$lang = $e->getLang();
				}else{ $lang = null; }
				$this->StackError($text,$lang);
			}
			ob_end_clean();
		}
		
		//	Execute layout controller.
		if( isset(${$file_name}) ){
			echo ${$file_name};
		}else{
			$this->StackError("Unknown error. ($file_name)",'en');
			return false;
		}
		
		return true;
	}

	/**
	 * Register of dispatcher.
	 *
	 * @see http://onepiece-framework.com/reference/dispatcher
	 * @return NewWorld5 | App | App_i18n
	 */
	function Dispatcher($dispatcher=null)
	{
		static $_dispatcher = null;
		if( $dispatcher ){
			$_dispatcher = $dispatcher;
			return;
		}
		
		if( $_dispatcher ){
			return $_dispatcher;
		}
		
		throw new OpException("Dispatcher has not been registered.",'en');
	}
	
	function __call($name, $args)
	{
		if( $dispatcher = $this->Dispatcher() ){
			if( method_exists($dispatcher, $name) ){
				if( count($args) > 10 ){
					$this->StackError("Limit of argument is exceeded.",'en');
				}
				for($i=0; $i<10; $i++){
					${"arg{$i}"} = isset($args[$i]) ? $args[$i]: null;
				}
				return $dispatcher->$name($arg0, $arg1, $arg2, $arg3, $arg4, $arg5, $arg6, $arg7, $arg8, $arg9);
			}
		}
		parent::__call($name, $args);
	}
	
	static function __callStatic($name, $args)
	{
		if( $dispatcher = $this->Dispatcher() ){
			if( method_exists($dispatcher, $name) ){if( count($args) > 10 ){
					$this->StackError("Limit of argument is exceeded.",'en');
				}
				for($i=0; $i<10; $i++){
					${"arg{$i}"} = isset($args[$i]) ? $args[$i]: null;
				}
				return $dispatcher::$name($arg0, $arg1, $arg2, $arg3, $arg4, $arg5, $arg6, $arg7, $arg8, $arg9);
			}
		}
		parent::__callStatic($name, $args);
	}
}
