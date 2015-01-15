<?php
/**
 * Cloud.model.php
 *
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright Copyright &copy; 2014 Tomoaki Nagahara
 * @version   1.0
 * @package   op-core
 */

/**
 * Model_Cloud
 * 
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright Copyright &copy; 2014 Tomoaki Nagahara
 * @version   1.0
 * @package   op-core
 */
class Model_Cloud extends OnePiece5
{
	function Config()
	{
		static $config;
		if(!$config){
			$config = new Config_Cloud();
		}
		return $config;
	}
	
	function wget( $url, $expire=3600)
	{
		//	Does not cache for admin.
		if( $this->admin() ){
			$expire = false;
		}
		
		//	Generate cache's key.
		$key = md5("$url, $expire");
		
		//	Get from cache.
		if(!$data = $this->Cache()->Get($key) or !$expire){
			//	Get from internet.
			$data = file_get_contents($url);
			//	Save to cache.
			$this->Cache()->Set($key, $data, $expire);
		}
		
		//	Return for result.
		return $data;
	}
	
	function json($url, $expire=null)
	{
		$data = $this->wget($url,$expire);
		$json = json_decode($data,true);
		
		//	Check error is one time only.
		if( isset($json['error']) ){
			$this->StackError($json['error']);
		}
		
		return $json;
	}
	
	function GetGeo($ip=null)
	{
		if(!$ip){
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		
		//	Fetch
		$url  = $this->Config()->url('geo')."?ip={$ip}";
		$json = $this->json($url);
		
		return $json;
	}
	
	/**
	 * Get country code by ip-address
	 * 
	 * @param  string $ip
	 * @return string $country_code
	 */
	function CountryCode($ip=null)
	{
		if(!$json = self::GetGeo($ip)){
			$this->mark($json);
			return false;
		}
		return $json['geo']['code'];
	}
	
	function CountryName($ip=null)
	{
		if(!$json = self::GetGeo($ip)){
			return false;
		}
		return $json['geo']['country'];
	}
	
	function CityName($ip=null)
	{
		if(!$json = self::GetGeo($ip)){
			return false;
		}
		return $json['geo']['city'];
	}
	
	function Geoinfo($ip=null)
	{
		if(!$json = self::GetGeo($ip)){
			return false;
		}
		return array('latitude'=>$json['geo']['latitude'],'longitude'=>$json['geo']['longitude']);
	}
	
	function GetLanguageByIpAddress($ip=null)
	{
		//	init ip-address
		if(!$ip){
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		
		//	get country code by ip-address.
		$country_code = $this->CountryCode($ip);
		
		//	get language code
		return $this->GetLanguage($country_code);
	}
	
	/**
	 * Get language code by country code.
	 * 
	 * @param  string $ip
	 * @return string country_code
	 */
	function GetLanguage($country_code=null)
	{
		//	init country code
		if(!$country_code){
			//	if saved cookie.
			if(!$country_code = $this->GetCookie('country_code')){
				//	get current user's country code by remote ip-address.
				$country_code = $this->CountryCode();
				//	save current user's country code.
				$this->SetCookie('country_code',$country_code);
			}
		}
		
		//	get language code by country code.
		$url  = $this->Config()->url('lang')."?country_code={$country_code}";
		$json = $this->json($url);
		
		return isset($json['language']) ? $json['language']: false;
	}
}

class Config_Cloud extends OnePiece5
{
	const _API_DOMAIN_ = 'api.uqunie.com';
	
	function url( $key )
	{
		switch($key){
			case 'geo':
				$url = 'http://'.self::_API_DOMAIN_.'/geo/';
				break;
				
			case 'lang':
				$url = 'http://'.self::_API_DOMAIN_.'/geo/language/';
				break;
		}
		return $url;
	}
}
