<?php
/**
 * 
 * @version 1.0
 * @since   2012
 * @author  Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright (C) 2012 Tomoaki Nagahara All rights reserved.
 */
class Config extends stdClass
{
	function Config()
	{
		
	}
	
	function Set( $key,  $val )
	{
		$this->$key = $val;
		return $this;
	}
	
	function Get( $key )
	{
		return $this->$key;
	}
	
	function __set( $name, $value )
	{
		if(!isset($this->{$name})){
			
			if( empty($name) ){
			//	var_dump($name);
			//	var_dump($value);
				return null;
			}
			
			$this->{$name} = new Config();
			$this->{$name} = $value;
			return $this->{$name};
		}else{
			printf('<p>%s, %s</p>',__METHOD__, $name);
		}
	}

	function __get($name)
	{
		if(!isset($this->{$name})){
			
			if( empty($name) ){
			//	var_dump($name);
				return null;
			}
			
			//  Use to property chain.
			$this->{$name} = new Config();
			return $this->{$name};
		}else{
			printf('<p>%s, %s</p>',__METHOD__, $name);
		}
	}
	
	function Merge( $config )
	{
		foreach( $config as $key => $var ){
			if( empty($this->$key) ){
				$this->$key = $var;
			}else{
				switch( gettype( $this->$key ) ){
					case 'object':
						$this->$key->merge($var);
						break;
						
					case 'array':
						print '<p>' . __FILE__ . __LINE__ . '</p>';
						break;
						
					default:
						$this->$key = $var;
				}
			}
		}
	}
	
	function D()
	{
		if( $io = OnePiece5::GetEnv('admin')){
			return;
		}
		
		$cli  = OnePiece5::GetEnv('cli');
		$line = OnePiece5::GetCallerLine();
		
		//
		OnePiece5::p($line,'div');
		
		//
		if( $cli ){
			var_dump( Toolbox::toArray($this) );
		}else{
			Dump::D(Toolbox::toArray($this));
		}
	}
	
}
