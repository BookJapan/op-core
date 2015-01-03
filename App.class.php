<?php
/**
 * App.class.php
 * 
 * @author tomoaki.nagahara@gmail.com
 */

if(!include_once('NewWorld5.class.php')){
	exit(0);
}

/**
 * App
 * 
 * @author tomoaki.nagahara@gmail.com
 */
class App extends NewWorld5
{
	function GetAction()
	{
		static $action;
		if(!$action){
			$args = $this->GetArgs();
			$action = $args[0] ? $args[0]: 'index';
		}
		return $action;
	}
	
	function SetControllerName( $var )
	{
		return $this->SetEnv('controller-name', $var);
	}
	
	function SetSettingName( $var )
	{
		return $this->SetEnv('setting-name', $var);
	}
	
	function SetModelDir( $var )
	{
		return $this->SetEnv('model-dir', $var);
	}

	function SetModuleDir( $var )
	{
		return $this->SetEnv('module-dir', $var);
	}
	
	function SetLayoutDir( $var )
	{
		$this->SetEnv('layout-dir', $var);
		return true;
	}
	
	function GetLayoutName()
	{
		return $this->GetEnv('layout');
	}
	
	function SetLayoutName( $var )
	{
		//	Set layout root. (full path)
		$layout_root = $this->GetEnv('layout-dir');
		$layout_root = $this->ConvertPath($layout_root);
		$this->SetEnv('layout-root',$layout_root.$var);
		
		return $this->SetEnv('layout', $var);
	}
	
	function SetLayoutPath( $var )
	{
		return $this->SetEnv('layout', $var);
	}
	
	function GetTemplateDir( $var )
	{
		return $this->GetEnv('template-dir');
	}

	function SetTemplateDir( $var )
	{
		return $this->SetEnv('template-dir', $var);
	}
	
	function SetHtmlPassThrough( $var )
	{
		return $this->SetEnv('HtmlPassThrough', $var);
	}
	
	function SetTitle( $var )
	{
		$this->SetEnv('title', $var);
	}
	
	function GetTitle()
	{
		return $this->GetEnv('title');
	}
	
	function Title()
	{
		print '<title>'.$this->GetTitle('title').'</title>';
	}
	
	function SetDoctype( $var )
	{
		$this->SetEnv('doctype',$args);
	}
	
	function Doctype( $doctype=null, $version=null )
	{
		if(!$doctype){
			$doctype = $this->GetEnv('doctype');
		}
		
		switch($doctype){
			case 'xml':
				$doctype = '<?xml version="1.0" encoding="UTF-8"?>';
				break;
				
			case 'xhtml':
				$doctype = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN">';
				break;

			case 'html':
				if( $version == 4 or $version == '4.01' ){
					$doctype = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN">';
				}else{
					$doctype = '<!DOCTYPE html>';
				}
				break;
				
			default:
				$doctype = '<!DOCTYPE html>';
		}
		print $doctype.PHP_EOL;
	}
	
	function InitLang()
	{
		if( $lang = $this->GetCookie('lang') ){
			$this->SetLang($lang);
		}
	}
	
	function SetLang( $var )
	{
		if( $var ){
			$this->SetEnv('lang',$var);
			$this->SetCookie('lang', $var);
		}
	}
	
	function GetLang()
	{
		if(!$lang = $this->GetEnv('lang')){
			$lang = $this->GetCookie('lang');
		}
		return $lang;
	}
	
	function SetCharset( $var )
	{
		$this->SetEnv('charset',$var);
	}
	
	function GetCharset( $args=null )
	{
		return $this->GetEnv('charset');
	}
	
	function SetMime( $mime )
	{
		$this->SetEnv('mime',$mime);
	}
	
	function GetMime()
	{
		return $this->GetEnv('mime');
	}
	
	function AddKeyword( $var )
	{
		$this->AddKeywords( $var );
	}
	
	function AddKeywords( $var )
	{
		$keywords = $this->GetEnv('keywords');
		$keywords.= ", $var";
		$this->SetEnv('keywords',$keywords);
	}
	
	function SetKeyword( $var )
	{
		$this->SetEnv('keywords',$var);
	}
	
	function SetKeywords( $var )
	{
		$this->SetEnv('keywords',$var);
	}
	
	function GetKeywords()
	{
		return $this->GetEnv('keywords');
	}
	
	function Keywords()
	{
		print '<meta name="keywords" content="'.$this->GetKeywords().'">';
	}

	function SetDescription( $var )
	{
		$this->SetEnv('description',$var);
	}
	
	function GetDescription()
	{
		return $this->GetEnv('description');
	}
	
	function Description()
	{
		print '<meta name="description" content="'.$this->GetDescription().'">';
	}
	
	function SetMemcache($memcache)
	{
		$this->SetEnv('memcache',$memcache);
	}
	
	function GetMemcache()
	{
		if(!$memcache = $this->GetEnv('memcache') ){
			$memcache = new Config();
		}
		return $memcache;
	}
	
	function SetDatabase( $database )
	{
		$this->SetEnv('database',$database);
	}
	
	function GetDatabase()
	{
		if(!$database = $this->GetEnv('database') ){
			$database = new Config();
		}
		return $database;
	}
	
	function SetTablePrefix( $prefix )
	{
		$this->SetEnv('table_prefix',$prefix);
	}
	
	function Selftest()
	{
		$this->mark();
		$io = $this->Wizard()->Selftest();
		$io = $this->Wizard()->isSelftest();
		return $io;
	}
	
	function En($english)
	{
		return $this->i18n()->En($english);
	}
	
	function Ja($japanese)
	{
		return $this->i18n()->Ja($japanese);
	}
}
