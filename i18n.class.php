<?php

class i18n extends Api
{
	const _API_UQUNIE_COM_ = 'http://api.uqunie.com/i18n/';
	
	function Get( $text, $from='en', $to=null )
	{
		if(!$to){
			$to = $this->GetEnv('lang');
		}

		$url = self::_API_UQUNIE_COM_;
		$url .= '?';
		$url .= 'text='.urlencode($text);
		$url .= '&from='.urlencode($from);
		$url .= '&to='.urlencode($to);
		
		if(!$json = parent::Curl($url)){
			return $text;
		}
		
		$json = json_decode($json,true);
		return $json['translate'];
	}
}

