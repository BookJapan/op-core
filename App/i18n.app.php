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
			$request_uri = $_SERVER['REQUEST_URI'];
			if(!strpos($request_uri, '?')){
				$request_uri .= '?';
			}
			list($request_uri,$query) = explode('?',$request_uri);
			$rewrite_base = rtrim($_SERVER['REWRITE_BASE'],'/').'/';
			$patt = preg_quote($rewrite_base);
			$path = explode('/', preg_replace("|^{$patt}|", '', $request_uri));
			
			//	
			if(!$list = $this->i18n()->GetLanguageList()){
				throw new OpException("Empty language list from api.uqunie.com");
			}
			
			//	
			if( $io = array_key_exists($path[0], $list) ){
				//	get lang
				$lang = array_shift($path);
				
				//	set lang for i18n
				$this->i18n()->SetLang($lang);
				
				//	set lang for App
				$this->SetLang($lang);
				
				//	rebuild request uri
				$request_uri = '/'.join('/',$path);
				if($query){
					$request_uri .= '?'.$query;
				} 
			}else if(preg_match('|\.[a-z]{2,4}$|i',$request_uri)){
				//	js, css, html
			}else{
				$this->SetDispatchFlag(true);
				$this->Location('/');
			}
			//	Get route infomation.
			$route = parent::GetRoute($request_uri);
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
		$i18n = OnePiece5::i18n();
		if(!$lang = $i18n->GetLang()){
			$lang = 'en';
		}
		//	build url by document root
		$url = $_SERVER['REWRITE_BASE'].$lang.$app_root;
		//	return result
		return $domain ? $domain.$url: $url;
	}
	
	function En($string)
	{
		return $this->i18n()->En($string);
	}
	
	function Ja($string)
	{
		return $this->i18n()->Ja($string);	
	}
}
