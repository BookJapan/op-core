<?php
/**
 * What is this?
 * 
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 *
 */
class Model_Helper extends Model_Model
{
	//  TODO: What is this?
	function GetFormOptionsDateYear($config=null)
	{
		$num   = isset($config->num)   ? $config->num:   20;
		$start = isset($config->start) ? $config->start: date('Y');
		$end   = isset($config->end)   ? $config->end:   $start + $num;
		
		$options = new Config();
		$options->empty->value = '';
		
		for( $i=0; $i<$num; $i++ ){
			$y = $start + $i;
			$options->$i->value = $y ? $y : '';
		}
		
		return $options;
	}

	//  TODO: What is this?
	function GetFormOptionsDateMonth($config=null)
	{
		if( isset($config->padding) ){
			$padding = $config->padding ? $config->padding: false;
		}else{
			$padding = true;
		}
		
		$options = new Config();
		
		for( $i=0; $i<=12; $i++ ){
			if( $padding and $i ){
				$i = sprintf('%02d',$i);
			}
			$options->$i->value = $i ? $i : '';
		}
		
		return $options;
	}
}
