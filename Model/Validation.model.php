<?php
/**
 * Validation.model.php
 * 
 * @created   2015-01-09
 * @version   1.0
 * @package   op-core
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2015 (C) Tomoaki Nagahara All right reserved.
 */

/**
 * Model_Validation
 * 
 * @created   2015-01-09
 * @version   1.0
 * @package   op-core
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2015 (C) Tomoaki Nagahara All right reserved.
 */
class Model_Validation extends OnePiece5
{
	static function isDomain( $host )
	{
		if( $host === 'localhost' ){
			return true;
		}
	}
	
	static function isEMail( $mail, $host=true, $strict=false )
	{
		/**
		 * @link http://www.tt.rim.or.jp/~canada/comp/cgi/tech/mailaddrmatch/
		 * 
		 * Sample:
		 * canada(@home)@tt.rim.or.jp
		 * "me@home"@digital-canvas.com
		 * Tom&Jerry/$100.00@digital-canvas.com
		 * canada@[192.168.0.1]
		 
			$d3     = '\d{1,3}';
			$ip     = join('\.', ($d3) x 4);
			$ascii  = '[\x01-\x7F]';
			$domain = '([-a-z0-9]+\.)*[a-z]+';
			$mailre = "^$ascii+\@($domain|\\[$ip\\])$";
		//	$mailre = '^[\x01-\x7F]+@(([-a-z0-9]+\.)*[a-z]+|\[\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\])';
		*/
		$this->mark();
		
		if( Toolbox::isLocalhost() ){
			$host = false;
		}
		
		/**
		 * 1. addr+part@example.com
		 * 2. addr+part@example
		 */
		$patt = $strict ? '/^([.0-9a-z_+-]+)@(([0-9a-z-]+\.)+[0-9a-z]{2})$/i':
						  '/^([*+!.&#$|\'\\%\/0-9a-z^_`{}=?‾:-]+)@(([0-9a-z-]+¥.)+[0-9a-z]{2,})$/i';
		if( $io = preg_match( $patt, trim($mail), $matche ) ){
			$this->d($match);
			$domain = $match[3];
			if( $host ){
				$io = $this->isDomain($domain);
			}
		}
		
		return $io;
	}
}
