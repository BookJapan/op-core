<?php
/**
 * Helper model
 * 
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 *
 */
class Model_Helper extends Model_Model
{
	//	Use credit card form
	function GetFormOptionsDateYear($config=null)
	{
		$num   = isset($config->num)   ? $config->num:   20;
		$start = isset($config->start) ? $config->start: date('Y');
		$end   = isset($config->end)   ? $config->end:   $start + $num;
		
		$options = new Config();
		$options->empty->value = '';
		
		for( $i=0; $i<$num; $i++ ){
			$y = $start + $i;
			$options->$y->value = $y ? $y : '';
		}
		
		return $options;
	}
	
	//	Use credit card form
	function GetFormOptionsDateMonth($config=null)
	{
		if( isset($config->padding) ){
			$padding = $config->padding ? $config->padding: false;
		}else{
			$padding = true;
		}
		
		$options = new Config();
		
		for( $i=0; $i<=12; $i++ ){
			if( $padding ){
				$m = sprintf('%02d',$i);
			}
			
			$options->$m->value = $m > 0 ? $m : '';
		}
		
		return $options;
	}
}
