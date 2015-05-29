<?php
/**
 * Upload.model.php
 * 
 * @creation: 2015-05-29
 * @version   1.0
 * @package   op-core
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2015 (C) Tomoaki Nagahara All right reserved.
 */

/**
 * Model_Upload
 * 
 * @creation: 2015-05-29
 * @version   1.0
 * @package   op-core
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2015 (C) Tomoaki Nagahara All right reserved.
 */
class Model_Upload extends Model_Model
{
	private $_error = null;
	
	function SetError($error)
	{
		$this->_error = $error;
	}
	
	function GetError()
	{
		return $this->_error;
	}
	
	function Validate($input_name, $type, $value=null)
	{
		switch(strtolower($type)){
			case 'mime':
				$io = ValidateMime($input_name, $value);
				break;
				
			case 'image':
				$io = ValidateImage($input_name);
				break;
				
			case 'size':
			case 'filesize':
				$io = ValidateFileSize($input_name, $value);
				break;
				
			case 'dimension':
				$io = ValidateDimension($input_name, $value);
				break;
		}
		return $io;
	}

	function ValidateMime($input_name, $value)
	{
		
	}
	
	function ValidateImage($input_name)
	{
		$_file = $_FILES[$input_name];
		$path  = $_file['tmp_name'];
		return Validator::Image($path);
	}
	
	function ValidateFileSize($input_name, $limit)
	{
		$_file = $_FILES[$input_name];
		$size  = $_file['size'];
		return $size < $limit ? true: false;
	}
	
	function ValidateDimension($input_name, $dimension)
	{
		
	}
	
	function _CalcFilePath($dir, $file_name, $name)
	{
		if( $file_name ){
			if( preg_match('/\.([-_a-z0-9]{3,5})$/', $name, $match) ){
				$ext = $match[1];
			}else{
				$ext = null;
			}
			$file_name .= ".$ext";
		}else{
			$file_name = $name;
		}
		
		$dir = $this->ConvertPath($dir);
		
		return rtrim($dir,'/')."/".$file_name;
	}
	
	function Copy($input_name, $dir, $file_name=null, &$path)
	{
		if(!isset($_FILES[$input_name])){
			$this->SetError("Does not upload files. ($input_name)");
			return;
		}
		
		$_file = $_FILES[$input_name];
		$name  = $_file['name'];
		$type  = $_file['type'];
		$tmp   = $_file['tmp_name'];
		$error = $_file['error'];
		$size  = $_file['size'];
		
		if( $error ){
			$this->SetError("Error occurred. ($error)");
			return;
		}
		
		if(!is_uploaded_file($tmp)){
			$this->SetError("This file is not in uploaded file. ($tmp)");
			return;
		}
		
		//	Generate file path
		$path = $this->_CalcFilePath($dir, $file_name, $name);
		
	//	if(!copy($tmp, $path)){
		if(!move_uploaded_file($tmp, $path)){
			$this->SetError("Did not copy upload file. ($tmp, $path)");
			return;
		}
		
		//	Last check.
		if(!file_exists($path)){
			$this->SetError("Did not copy upload file? ($path)");
			return;
		}
		
		return true;
	}
}
