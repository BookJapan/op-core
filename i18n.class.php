<?php
/**
 * i18n
 *
 * @version   1.0
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2013 (C) Tomoaki Nagahara All right reserved.
 */
class i18n extends OnePiece5
{
	var $table = array();
	var $lang  = null;
	
	function init()
	{
		parent::init();
		$this->lang = $this->GetEnv('lang');
	}
	
	function Set( $english, $translate, $lang=null )
	{
		if(!$lang){
			$lang = $this->lang;
		}
		
		$key = md5($english);
		$this->table[$key][$lang] = $translate;
		
		return true;
	}
	
	function Get( $english, $lang=null )
	{
		if(!$lang){
			$lang = $this->lang;
		}
		$key = md5($english);

		if( isset( $this->table[$key][$lang] ) ){
			return $this->table[$key][$lang];
		}
		
		return $english;
	}
	
	function SetByFile( $path, $lang=null )
	{
		if( file_exists($path) ){
			include($path);
		}
		
		if( isset($config) ){
			return $this->SetByConfig($config, $lang);
		}else if( isset($conf) ){
			return $this->SetByArray($conf, $lang);
		}
		
		return false;
	}
	
	function SetByConfig( $config, $lang=null )
	{
		return $this->SetByArray( Toolbox::toArray($config), $lang );
	}

	function SetByArray( $array, $lang=null )
	{
		if(!$lang){
			$lang = $this->lang;
		}
		
		foreach( $array as $temp ){
			if( isset($temp[$lang]) ){
				$english   = $temp['en'];
				$translate = $temp[$lang];
				$this->set( $english, $translate, $lang );
			}
		}
		
		return true;
	}
	
}
