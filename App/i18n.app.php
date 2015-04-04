<?php
/**
 * i18n.app.php
 * 
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 */

/**
 * App_i18n
 * 
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
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
			
			//	init
			list($request_uri,$query) = explode('?',$_SERVER['REQUEST_URI'].'?');
			$rewrite_base = rtrim($_SERVER['REWRITE_BASE'],'/').'/';
			
			//	Get App root url
			$patt = preg_quote($rewrite_base);
			$path = explode('/', preg_replace("|^{$patt}|", '', $request_uri));
			
			//	Get language list
			if(!$list = $this->i18n()->GetLanguageList()){
				throw new OpException("Empty language list from api.uqunie.com");
			}
			
			//	Get extension
			if( preg_match('|\.([0-9a-z]{2,4})$|i',$request_uri,$match) ){
				$extension = $match[1];
			}else{
				$extension = null;
			}
			
			//	Get lang.
			$lang = array_shift($path);
			
			//	rebuild request uri
			$request_uri = '/'.join('/',$path);
			if( $query ){
				$request_uri .= '?'.$query;
			}
			
			//	Check lang.
			if( array_key_exists($lang,$list) ){
				//	Set lang for i18n
				$this->i18n()->SetLang($lang);
			}else{
				$lang = $this->i18n()->GetLang();
				$url  = '/'.$lang.'/'.ltrim($request_uri,'/');
				$this->Location($url);
			//	$this->Mark($url);
			}
			
			switch(strtolower($extension)){
				case '':
				case 'js':
				case 'css':
				case 'html':
					//	Does not transfer.
					break;
					
				default:
					//	Transfer real path. (ex. /img/logo.png)
					header("Location: $request_uri");
					exit;
			}
			
			//	Get route infomation.
			$route = Router::GetRoute($request_uri);
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
