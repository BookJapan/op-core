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
	 * Call from inner layout.
	 */
	private function Content()
	{
		$this->Dispatcher()->Content();
	}
	
	function Execute()
	{
		//	Get layout settings.
		$layout_dir	 = $this->_GetLayoutDir();
		$layout		 = $this->_GetLayout();
		
		//  Get controller name (layout controller)
		$controller = $this->GetEnv('controller-name');
		
		//	Get layout controller path.
		$layout_path = rtrim($layout_dir,'/')."/{$layout}/{$controller}";
		$layout_path = $this->ConvertPath($layout_path);
		if(!file_exists($layout_path)){
			$this->StackError("Does not exists this file. \($layout_path)\\",'en');
			return false;
		}
		
		//	Execute layout controller.
		include($layout_path);
		
		//  Rebuild layout directory.
		$layout_dir = dirname($layout_path) . '/';
		
		//	Execute each layout.
		foreach($_layout as $var_name => $file_name){
			//	Build path.
			$path = $layout_dir . $file_name;
			
			if(!file_exists($path)){
				$this->StackError("Does not exists layout file. \($path)\\",'en');
				return;
			}
			
			//	Assembly of layout.
			ob_start();
			$this->mark($path,'layout');
			include($path);
			${$file_name} = ob_get_contents();
			${$var_name}  = & ${$file_name}; // TODO: & <- ???
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
	 * Dispatcher
	 *
	 * @see http://onepiece-framework.com/reference/dispatcher
	 * @return EMail
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
	
	function Doctype()
	{
		echo "<!DOCTYPE html>";
	}
	
	function GetLang()
	{
		if(!$lang = $this->GetEnv('lang')){
			$lang = $this->GetCookie('lang');
		}
		return $lang;
	}
	
	function GetCharset()
	{
		return $this->GetEnv('charset');
	}
	
	function GetKeywords()
	{
		return $this->GetEnv('keywords');
	}
	
	function GetDescription()
	{
		return $this->GetEnv('description');
	}
	
	function GetTitle()
	{
		return $this->GetEnv('title');
	}
}
