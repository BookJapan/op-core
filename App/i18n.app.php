<?php
/**
 * i18n.app.php
 * 
 * @version   1.0
 * @package   op-core
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2013 (C) Tomoaki Nagahara All right reserved.
 */

/**
 * App_i18n
 * 
 * @version   1.0
 * @package   op-core
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2013 (C) Tomoaki Nagahara All right reserved.
 */
class App_i18n extends App
{
	/**
	 * Wrap the NewWorld Dispatch method.
	 * 
	 * 1st SmartURL arguments is language code.
	 * Convert to real path from SmartURL.
	 * 
	 * @see NewWorld5::Dispatch()
	 * @param  array   $route
	 * @return boolean
	 */
	function Dispatch($route=null)
	{
		if(!$route){
			//	Entry i18n env.
			$this->SetEnv('app.i18n',true);
			
			//	Parse URL arguments.
			if( strpos($_SERVER['REQUEST_URI'], '?') ){
				list($request_uri, $query) = explode('?',$_SERVER['REQUEST_URI']);
				$query = '?'.$query;
			}else{
				$request_uri = $_SERVER['REQUEST_URI'];
				$query = null;
			}
			$args = explode('/',ltrim($request_uri, '/'));
			
			//	Get language list
			if(!$list = $this->i18n()->GetLanguageList()){
				throw new OpException("Empty language list from api.uqunie.com");
			}
			
			//	Check language code.
			if(!array_key_exists($args[0], $list) ){
				$lang = $this->i18n()->GetLang();
				$url = "/{$lang}/".trim(join('/',$args),'/').$query;
				header("Location: $url");
			}
			
			//	Get language code.
			$lang = array_shift($args);
			$this->i18n()->SetLang($lang);
			
			//	Re:Build Request URI.
			$request_uri = '/'.join('/',$args);

			//	Path info
			$pathinfo = pathinfo($request_uri);
			
			//	Branch by extension.
			if( isset($pathinfo['extension']) ){
				switch(strtolower($pathinfo['extension'])){
					case '':
					case 'js':
					case 'css':
					case 'html':
					case 'php':
						break;
						
					default:
						//	Transfer real path. (ex. /img/logo.png)
						header("Location: {$request_uri}{$query}"); exit;
				}
			}
			
			//	Get route infomation.
			$route = Router::GetRoute($request_uri.$query);
		}
		
		return parent::Dispatch($route);
	}
	
	/**
	 * Wrap the OnePiece ConvertURL method.
	 * Support langage code URL.
	 * 
	 * @see OnePiece5::ConvertURL()
	 * @param string  $meta   meta path
	 * @param boolean $domain Add domain
	 * @return string
	 */
	static function ConvertURL($meta,$domain=false)
	{
		//	if include want to domain.
		if( $domain ){
			$domain = Toolbox::GetDomain(array('scheme'=>true));
		}
		
		//	standard
		$app_root = parent::ConvertURL($meta,false);
		
		//	remove rewrite base
		$app_root = preg_replace("|^{$_SERVER['REWRITE_BASE']}|",'/',$app_root);
		
		//	get i18n's set language code
		if(!$lang = OnePiece5::i18n()->GetLang()){
			$lang = 'en';
		}
		
		//	build url by document root
		$url = $_SERVER['REWRITE_BASE'].$lang.$app_root;
		$url = '/'.ltrim($url,'/');
		
		//	return result
		return $domain ? $domain.$url: $url;
	}
	
}
