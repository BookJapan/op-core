<?php
/**
 * Inherit the NewWorld.
 * 
 * Don't edit NewWorld
 * Please create your original App class. 
 * 
 * @author Tomoaki Nagahara
 *
 */

if(!include_once('NewWorld5.class.php')){
	exit(0);
}

class App extends NewWorld5
{
	/**
	 * @var ConfigMgr
	 */
	private $_mgr = null;
	
	/**
	 * 
	 * @param  ConfigMgr $var
	 * @return ConfigMgr
	 */
	function Config( $mgr=null )
	{
		if( $mgr ){
			if( $mgr instanceof ConfigMgr ){
				$this->_mgr = $mgr;
			}else{
				throw new OpException("This variable is not ConfigMgr.");
			}
		}else if( empty($_mgr) ){
			if(empty($this->_mgr)){
				throw new OpException("Not been instance yet.");
			}
		}
		return $this->_mgr;
	}
	
	function GetAction()
	{
		if(!$action = $this->GetEnv('action') ){
			//  Does not undefine.
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
				if( $version == 4 or $version == 4.01 ){
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

	function SetLang( $var )
	{
		$this->SetEnv('lang',$var);
	}
	
	function GetLang()
	{
		print $this->GetEnv('lang');
	}

	function SetCharset( $var )
	{
		$this->SetEnv('charset',$var);
	}
	
	function GetCharset( $args=null )
	{
		print $this->GetEnv('charset');
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
		print $this->GetEnv('keywords');
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
		print $this->GetEnv('description');
	}
	
	function Description()
	{
		print '<meta name="description" content="'.$this->GetDescription().'">';
	}
}
