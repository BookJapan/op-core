<?php

class Model_CreditCard extends Model_Base
{
	/**
	 * (non-PHPdoc)
	 * @see Model_Base::Config()
	 * @return Config_CreditCard
	 */
	function Config()
	{
		return parent::Config('Config_CreditCard');
	}
	
	private function _GetURL($key)
	{
		$url = 'http://api.uqunie.com/';
		switch($key){
			case 'payment':
				$url .= 'payment/';
				break;
		}
		return $url;
	}
	
	private function _curl($url)
	{
		//	CURL
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$json = curl_exec($ch);
		curl_close($ch);
		
		$this->d($json);
		
		return json_decode($json,true);
	}
	
	function GetFormName()
	{
		return Config_CreditCard::form_name;
	}

	function GetInputName($key)
	{
		switch($key){
			case 'form_name':
				$var = Config_CreditCard::form_name;
				break;
			case 'card_no':
				$var = Config_CreditCard::input_card_no;
				break;
			case 'exp_yy':
				$var = Config_CreditCard::input_exp_yy;
				break;
			case 'exp_mm':
				$var = Config_CreditCard::input_exp_mm;
				break;
			case 'csc':
				$var = Config_CreditCard::input_csc;
				break;
			case 'paymode':
				$var = Config_CreditCard::input_paymode;
				break;
			case 'incount':
				$var = Config_CreditCard::input_incount;
				break;
			case 'submit':
				$var = Config_CreditCard::input_submit;
				break;
		}
		return $var;
	}
	
	function Ready()
	{
		$config = $this->Config()->form_creditcard();
		$this->Form()->AddForm($config);
	}
	
	function Auto()
	{
		$sid = $this->Autorized();
	}
	
	function Authorized()
	{
		return $sid;
	}
	
	function Commit($sid)
	{
		
	}
	
	function PaymentByUID($uid)
	{
		
	}
	
	function PaymentBySID($sid)
	{
		
	}
	
	function Payment($price)
	{
		if(!$price){
			$this->StackError('empty price');
			return false;
		}
		
		$form_name = Config_CreditCard::form_name;
		$card_no = $this->form()->GetInputValue(Config_CreditCard::input_card_no,$form_name);
		$exp_yy  = $this->form()->GetInputValue(Config_CreditCard::input_exp_yy, $form_name);
		$exp_mm  = $this->form()->GetInputValue(Config_CreditCard::input_exp_mm, $form_name);
		$csc     = $this->form()->GetInputValue(Config_CreditCard::input_csc,    $form_name);
		
		//	query
		$query = array();
		$query[] = Config_CreditCard::post_cardno.'='.$card_no;
		$query[] = Config_CreditCard::post_exp.'='.$exp_mm.$exp_yy;
		$query[] = Config_CreditCard::post_csc.'='.$csc;
		$query[] = Config_CreditCard::post_amount.'='.$price;
		
		//	url
		$url  = $this->_GetURL('payment');
		$url .= '?'.join('&',$query);
		
		//	get json
		$json = $this->_curl($url);
		
		$this->mark($url);
		$this->d($json);
		
		return $json;
	}
	
}

class Config_CreditCard extends Config_Base
{
	/**
	 * !!CAUTION!!
	 * 
	 * Do not use this constant from external class.
	 * Because it may change. 
	 * Please use GetFormName, GetInputName method.
	 */
	const form_name     = 'form_creditcard';
	
	const input_card_no = 'creditcard_no';
	const input_exp_yy  = 'creditcard_exp_yy';
	const input_exp_mm  = 'creditcard_exp_mm';
	const input_csc     = 'creditcard_csc';
	const input_paymode = 'creditcard_paymode';
	const input_incount = 'creditcard_incount';
	const input_submit  = 'creditcard_submit';
	
	const post_cardno   = 'cardno';
	const post_exp      = 'exp';
	const post_csc      = 'csc';
	const post_amount   = 'amount';
	
	function form_creditcard()
	{
		$config = New Config();
		
		//	form
		$config->name = self::form_name;
		
		//	input
		$input_name = self::input_card_no;
		$config->input->$input_name->label	 = $this->i18n()->ja('カード番号');
		$config->input->$input_name->type	 = 'text';
		$config->input->$input_name->id		 = $input_name;
		$config->input->$input_name->validate->required = true;
		$config->input->$input_name->error->required = $this->i18n()->ja('この項目は必須入力です。');
		
		$input_name = self::input_csc;
		$config->input->$input_name->label	 = $this->i18n()->ja('セキュリティコード');
		$config->input->$input_name->type	 = 'text';
		$config->input->$input_name->id		 = $input_name;
		$config->input->$input_name->validate->required = true;
		$config->input->$input_name->error->required = $this->i18n()->ja('この項目は必須入力です。');
		
		$input_name = self::input_exp_yy;
		$config->input->$input_name->label	 = $this->i18n()->ja('有効期限（年）');
		$config->input->$input_name->type	 = 'select';
		$config->input->$input_name->id		 = $input_name;
		$config->input->$input_name->validate->required = true;
		$config->input->$input_name->error->required = $this->i18n()->ja('この項目は必須入力です。');
		
		//	select's option
		$config->input->$input_name->options->{'-'}->label = '-';
		$config->input->$input_name->options->{'-'}->value = '';
		for( $i=0, $y=date('y'); $i<=20; $i++ ){
			$yy = $y + $i;
			$config->input->$input_name->options->$yy->value = $yy;
		}
		
		$input_name = self::input_exp_mm;
		$config->input->$input_name->label	 = $this->i18n()->ja('有効期限（月）');
		$config->input->$input_name->type	 = 'select';
		$config->input->$input_name->id		 = $input_name;
		$config->input->$input_name->validate->required = true;
		$config->input->$input_name->error->required = $this->i18n()->ja('この項目は必須入力です。');

		//	select's option
		$config->input->$input_name->options->{'-'}->label = '-';
		$config->input->$input_name->options->{'-'}->value = '';
		for( $i=1; $i<=12; $i++ ){
			$config->input->$input_name->options->$i->label = $i;
			$config->input->$input_name->options->$i->value = sprintf('%02d',$i);
		}
		
		$input_name = self::input_incount;
		$config->input->$input_name->label	 = $this->i18n()->ja('支払い方法');
		$config->input->$input_name->type	 = 'select';
		$config->input->$input_name->id		 = $input_name;
		$config->input->$input_name->validate->required = true;
		
		$config->input->$input_name->options->{'10'}->label = $this->i18n()->ja('一括');
		$config->input->$input_name->options->{'10'}->value = '10';
		
		$config->input->$input_name->options->{'21'}->label = $this->i18n()->ja('ボーナス一括');
		$config->input->$input_name->options->{'21'}->value = '21';
		
		$config->input->$input_name->options->{'31'}->label = $this->i18n()->ja('ボーナス併用');
		$config->input->$input_name->options->{'31'}->value = '31';
		
		$config->input->$input_name->options->{'61'}->label = $this->i18n()->ja('分割');
		$config->input->$input_name->options->{'61'}->value = '61';
		
		$config->input->$input_name->options->{'80'}->label = $this->i18n()->ja('リボルビング');
		$config->input->$input_name->options->{'80'}->value = '80';
		
		$input_name = self::input_paymode;
		$config->input->$input_name->label	 = $this->i18n()->ja('分割回数');
		$config->input->$input_name->type	 = 'select';
		$config->input->$input_name->id		 = $input_name;
		//	$config->input->$input_name->validate->required = true;
		
		foreach(array(1,2,3,4,5,6,10,12,24,36,48) as $num){
			$config->input->$input_name->options->$num->label = $this->i18n()->ja($num."回");
			$config->input->$input_name->options->$num->value = $num;
		}
		
		$input_name = self::input_submit;
		$config->input->$input_name->type  = 'submit';
		$config->input->$input_name->value = 'submit';
		
		return $config;
	}
}
