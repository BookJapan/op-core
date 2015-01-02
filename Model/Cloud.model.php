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
	
	function GetGeocodeByIpAddress($ip=null)
	{
		if(!$ip){
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		
		//	Fetch
		$url = $this->Config()->url('geocode')."?ip={$ip}";
		if(!$json = file_get_contents($url)){
			$this->StackError("Connection failed. ($url)");
			return false;
		}
		
		//	Convert to assoc.
		$json = json_decode($json,true);
		if( isset($json['error']) ){
			$this->StackError($json['error']);
		}
		
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
		if(!$json = self::GetGeocodeByIpAddress($ip)){
			return false;
		}
		return $json['geocode']['letter'];
	}
	
	function CountryName($ip=null)
	{
		if(!$json = self::GetGeocodeByIpAddress($ip)){
			return false;
		}
		return $json['geocode']['country'];
	}
	
	function CityName($ip=null)
	{
		if(!$json = self::GetGeocodeByIpAddress($ip)){
			return false;
		}
		return $json['geocode']['city'];
	}
	
	function Geocode($ip=null)
	{
		if(!$json = self::GetGeocodeByIpAddress($ip)){
			return false;
		}
		return array('latitude'=>$json['geocode']['latitude'],'longitude'=>$json['geocode']['longitude']);
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
		$url  = $this->Config()->url('lang')."?country={$country_code}";
		$json = file_get_contents($url);
		$json = json_decode($json,true);
		
		return isset($json['language']) ? $json['language']: false;
	}
}

class Config_Cloud extends OnePiece5
{
	const _API_DOMAIN_ = 'api.uqunie.com';
	
	function url( $key )
	{
		switch($key){
			case 'geocode':
				$url = 'http://'.self::_API_DOMAIN_.'/geocode/';
				break;
				
			case 'lang':
				$url = 'http://'.self::_API_DOMAIN_.'/geocode/language/';
				break;
		}
		return $url;
	}
}
