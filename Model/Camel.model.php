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
	/**
	 * 
	 * @param  string $str
	 * @return string
	 */
	static function ConvertSnakeCase($str)
	{
		$str = trim($str);
		if( preg_match('/[^-_a-z0-9\s]/i',$str) ){
			OnePiece5::StackError('Illigal character code.');
			return $str;
		}
		//	Abc DEf-G -> abc DEf-G
		$str = lcfirst($str);
		//	abc DEf-G -> abc  D Ef- G
		$str = preg_replace( '/([A-Z])/', ' \\1', $str );
		//	abc  D Ef-G -> abc  D Ef  G
		$str = str_replace('-', ' ', $str);
		//	abc  D Ef  G -> abc D Ef G
		$str = preg_replace( '/\s+/', ' ', $str );
		//	abc D Ef G -> abc_D_Ef_G
		$str = str_replace(' ', '_', $str);
		//	abc_D_Ef_G -> abc_d_ef_g
		$str = strtolower($str);
		return $str;
	}
	
	static function ConvertPascalCase($str)
	{
		$str = self::ConvertSnakeCase($str);
		$str = str_replace('_',' ',$str);
		$str = ucwords($str);
		$str = str_replace(' ', '', $str);
		return $str;
	}
	
	static function ConvertCamelCase($str)
	{
		$str = self::ConvertPascalCase($str);
		$str = lcfirst($str);
		return $str;
	}
}
