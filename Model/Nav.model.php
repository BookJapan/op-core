<?php

class Model_Nav extends Model_Model
{
	private $_format;
	private $_stack = array();
	
	function Init()
	{
		parent::Init();
		self::SetFormat();
	}
	
	function Add( $href, $label=null )
	{
		$stack['href']  = $href;
		$stack['label'] = $label;
		
		$this->_stack[] = $stack;
	}
	
	function Out($format=null)
	{
		if(!$format ){
			$format = self::GetFormat();
		}
		
		$join = array();
		foreach( $this->_stack as $stack ){
			$href  = $this->ConvertURL($stack['href']);
			$label = $stack['label'] ? $stack['label']: $stack['href'];
			$join[] = sprintf('<a href="%s">%s</a>',$href,$label);
		}
		
		print join( $format->join, $join );
	}
	
	function SetFormat( Config $format=null )
	{
		if(!$format ){
			//  Init
			$format = new Config();
			$format->join = ' &gt; ';
		}
		
		//  
		$this->_format = $format;
		return true;
	}
	
	function GetFormat()
	{
		return $this->_format;
	}
}
