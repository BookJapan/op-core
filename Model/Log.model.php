<?php
/**
 * Stack the log. Only do it.
 * 
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 *
 */
class Model_Log extends Model_Model
{
	private $log;
	
	function Set( $log, $io=null )
	{
		$stack = array();
		$stack['io']  = $io;
		$stack['log'] = $log;
		$this->log[] = $stack;
	}
	
	function Get()
	{
		if(!$this->Admin()){
			return;
		}
		
		$result = '<div class="table small">';
		foreach( $this->log as $i => $stack ){
			//  init
			$io  = $stack['io'];
			$log = $stack['log'];
			
			//  class
			if( is_null($io) ){
				$class = 'gray';
			}else if( is_bool($io) ){
				$class = $io ? 'blue': 'red';
			}else if( is_string($io)){
				$class = $io;
			}else{
				$class = null;
			}
			
			//  table
			$result .= '<div class="tr">';
			$result .= '<div class="td">';
			$result .= '<span class="'.$class.'">'.$log.'</span>';
			$result .= '</div>';
			$result .= '</div>';
		}
		$result .= '</div>';
		
		return $result;
	}
	
	function Out()
	{
		print $this->Get();
	}
}
