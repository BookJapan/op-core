<?php
/**
 * Validator.class.php
 * 
 * @creation  2015-05-10
 * @version   1.0
 * @package   op-core
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2015 (C) Tomoaki Nagahara All right reserved.
 */

/**
 * Validator
 * 
 * @creation  2015-05-10
 * @version   1.0
 * @package   op-core
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2015 (C) Tomoaki Nagahara All right reserved.
 */
class Validator extends OnePiece5
{
	/**
	 * <pre>
	 * OK:
	 *  0
	 *  1
	 *  1.0
	 *  1.1
	 * -1
	 * -1.0
	 * -1.1
	 * 
	 * NG:
	 * </pre>
	 * 
	 * @return boolean
	 */
	static function isNumber($var)
	{
		return is_numeric($var);
	}

	/**
	 * <pre>
	 * OK:
	 *  1
	 *  1.0
	 *  1.1
	 *
	 * NG:
	 *  0
	 * -1
	 * -1.0
	 * -1.1
	 * </pre>
	 * 
	 * @return boolean
	 */
	static function isNumberPositive($var)
	{
		if(!self::isNumber($var)){
			return false;
		}
		return $var>0 ? true: false;
	}

	/**
	 * <pre>
	 * OK:
	 * -1
	 * -1.0
	 * -1.1
	 *
	 * NG:
	 *  0
	 *  1
	 *  1.0
	 *  1.1
	 * </pre>
	 * 
	 * @return boolean
	 */
	static function isNumberNegative()
	{
		if(!self::isNumber($var)){
			return false;
		}
		return $var<0 ? true: false;
	}
	
	/**
	 * @see Validator::isInteger
	 * @return boolean
	 */
	static function isInt()
	{
		return self::isInteger();
	}

	/**
	 * <pre>
	 * OK:
	 *  0
	 *  1
	 * -1
	 *
	 * NG:
	 *  1.0
	 * -1.0
	 *  1.1
	 * -1.1
	 * </pre>
	 * 
	 * @return boolean
	 */
	static function isInteger($var)
	{
		if(!self::isNumber($var)){
			return false;
		}
		
		if( is_string($var) ){
			return strpos($var, '.') === false ? true: false;
		}else{
			return is_int($var);
		}
	}
	
	/**
	 * <pre>
	 * OK:
	 *  0
	 *  1
	 * -1
	 *  1.0
	 * -1.0
	 *  1.1
	 * -1.1
	 *
	 * NG:
	 * </pre>
	 * 
	 * @return boolean
	 */
	static function isFlat($var)
	{
		return is_float($var);
	}
	
	static function isDecimal($var)
	{
		OnePiece5::Mark();
	}
}
