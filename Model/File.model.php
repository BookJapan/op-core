<?php /* vim: ts=4:sw=4:tw=80 */
/**
 * File.model.php
 * 
 * @version   1.0
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2009 (C) Tomoaki Nagahara All right reserved.
 * @package   op-core
 */

/**
 * Model_File
 * 
 * @version   1.0
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2009 (C) Tomoaki Nagahara All right reserved.
 * @package   op-core
 */
class Model_File extends Model_Model
{
	function Get( $path=null )
	{
		if(!$path){
			$path = './';
		}
		
		$path = rtrim( $path, '/' ) . '/';
		
		if(!file_exists($path)){
			$this->mark("$path is not exists.");
			return array();
		}
		
		$dir = opendir($path);
		while( $name = readdir($dir) ){
			
			$pos = strpos( $name, '.');
			if( $pos === 0 ){
				continue;
			}
			
			$is_dir = is_dir($path.$name);
			if(!$is_dir){
				$tmp = explode('.',$name);
				$ext = array_pop( $tmp );
			}else{
				$ext = null;
			}
			
			$file = array();
			$file['dir']  = $is_dir;
			$file['name'] = $name;
			$file['ext']  = $ext;
			
			$files[] = $file;
		}
		
		return isset($files) ? $files: array();
	}
}
