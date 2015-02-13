<?php
/**
 * Autoloader.class.php
 * 
 * Creation: 2014-11-29
 * 
 * @version   1.0
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2014 (C) Tomoaki Nagahara All right reserved.
 * @package   op-core
 */

/**
 * Autoloader
 * 
 * Creation: 2014-11-29
 * 
 * @version   1.0
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2014 (C) Tomoaki Nagahara All right reserved.
 * @package   op-core
 */
class Autoloader
{
	static function Autoload( $class_name )
	{
		//	Checking used sub directory.
		if( $pos = strpos($class_name,'_',1) ){
			list( $prefix, $class ) = explode('_',$class_name);
			switch($prefix){
				case 'Model':
				case 'Config':
					$_is_model = true;
					break;
				case 'App':
					$_is_app = true;
					break;
			}
		}
		
		//	Get include path.
		$include_path = explode( PATH_SEPARATOR, ini_get('include_path') );
		
		//	Add op-core's root.
		$include_path[] = $_SERVER['OP_ROOT'];
		
		//	Generate file name.
		if( isset($_is_model) ){
			$file_name = self::_model($class);
			//	Added sub direcotry.
			$include_path[] = OnePiece5::ConvertPath(OnePiece5::GetEnv('model-dir'));
			$include_path[] = rtrim($_SERVER['OP_ROOT'],DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'Model';
		}else if( isset($_is_app) ){
			$file_name = self::_app($class);
			//	Added sub direcotry.
			$include_path[] = rtrim($_SERVER['APP_ROOT'],DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'App';
			$include_path[] = rtrim($_SERVER['OP_ROOT'],DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'App';
		}else{
			$file_name = self::_class($class_name);
		}
		
		//	Challenge to include.
		foreach( $include_path as $path ){
			if(!$path){
				continue;
			}
			$file_path = rtrim($path,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$file_name;
			if( $io = file_exists($file_path) ){
				$io = include_once($file_path);
			}
		}
		/*
		// Checking autoload successful.
		if(!class_exists($class_name, false)){
			trigger_error("Unable to auto load class: $class_name", E_USER_NOTICE);
			OnePiece5::Mark("Unable to auto load class: $class_name");
		}
		*/
	}
	
	static function _class( $class_name )
	{
		return $class_name.".class.php";
	}
	
	static function _app( $class_name )
	{
		return "{$class_name}.app.php";
	}
	
	static function _model( $class_name )
	{
		return "{$class_name}.model.php";
	}
}
