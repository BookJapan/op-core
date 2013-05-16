<?php

class Model_CSV extends Model_Model
{
	private $_index = 0;
	private $_array = array();
	
	function Set( $path )
	{
		if(!file_exists($path)){
			$this->StackError("Does not file exists.");
			return false;
		}
		
		$file = file_get_contents($path);
		
		$encode_to   = $this->GetEnv('charset');
		$encode_from = mb_detect_encoding($file, "jis, eucjp-win, sjis-win, utf-8");
		
		$this->mark($encode_from);
		
		$bf = mb_convert_encoding(
				$file,
				$encode_to,
				$encode_from
				);
		$fp = tmpfile();
		fwrite( $fp, $bf );
		rewind( $fp );
		
		while( $line = fgetcsv( $fp ) ){
			$this->_array[] = $line;
		}
		
		return true;
	}
	
	function Get()
	{
		if( isset($this->_array[$this->_index]) ){
			$result = $this->_array[$this->_index];
			$this->_index++;
		}else{
			$result = false;
		}
		
		return $result;
	}
}


