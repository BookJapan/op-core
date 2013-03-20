<?php
/**
 * Password generator
 * 
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 */
class Model_Password extends Model_Model
{
	function Get( $length=8 )
	{
		$result = '';
		$range = array_merge( 
					range('a','k'), 
					range('2','9'), 
					range('m','z'), 
					range('2','9'), 
					range('A','H'),
					range('2','9'), 
					range('J','N'),
					range('2','9'), 
					range('P','Z')
				);
		shuffle( $range );
		
		for( $i=0; $i<$length; $i++ ){
			$result .= $range[$i];
		}
		
		return $result;
	}
}
