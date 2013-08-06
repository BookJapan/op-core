<?php
/**
 * Password generator
 * 
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 */
class Model_Password extends Model_Model
{
	function Get( $length=8, $sym=4, $num=4, $ALP=4, $alp=4 )
	{
		$pw = '';
		$symbol = '?!"#$%&*+-=,./:;(){}[]@~';
		$number = '23456789';
		$alphabet = 'abcdefghijkmnopqrstuvwxyz';
		$ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
		
		srand(time());
		
		for( $i=0; $i<$sym; $i++ ){
			$pw .= $symbol{rand(0,strlen($symbol)-1)};
		}
		
		for( $i=0; $i<$num; $i++ ){
			$pw .= $number{rand(0,strlen($number)-1)};
		}
		
		for( $i=0; $i<$alp; $i++ ){
			$pw .= $alphabet{rand(0,strlen($alphabet)-1)};
		}
		
		for( $i=0; $i<$ALP; $i++ ){
			$pw .= $ALPHABET{rand(0,strlen($ALPHABET)-1)};
		}
		
		return substr( str_shuffle($pw) , 0, $length );
	}
}
