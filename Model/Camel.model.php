<?php
/**
 * For PHP4
 *
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 *
 */
if (!function_exists('lcfirst')) {
	function lcfirst($text) { // upper is ucfirst
		$text{0} = strtolower($text{0});
		return $text;
	}
}

class Model_Camel
{
	function ConvertSnakeCase($str)
	{
		$str = trim($str);
		if( preg_match('/[^-_a-z0-9]/i',$str) ){
			$this->StackError('Illigal character code.');
			return;
		}
		$str = preg_replace( '/([A-Z])/', ' \\1', $str );
		$str = preg_replace( '/\s+/', ' ', $str );
		$str = str_replace('-', ' ', $str);
		$str = str_replace(' ', '_', $str);
		$str = strtolower($str);
		return $str;
	}
	
	function ConvertPascalCase($str)
	{
		$str = self::ConvertSnakeCase($str);
		$str = str_replace('_',' ',$str);
		$str = ucwords($str);
		$str = str_replace(' ', '', $str);
		return $str;
	}
	
	function ConvertCamelCase($str)
	{
		$str = self::ConvertPascalCase($str);
		$str = lcfirst($str);
		return $str;
	}
}
