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
	
	function GetFormName()
	{
		return Config_CreditCard::_FORM_NAME_;
	}

	function GetInputName($key)
	{
		switch($key){
			case 'card_no':
				$var = Config_CreditCard::_INPUT_CARD_NO_;
				break;
			case 'exp_yy':
				$var = Config_CreditCard::_INPUT_EXP_YY_;
				break;
			case 'exp_mm':
				$var = Config_CreditCard::_INPUT_EXP_MM_;
				break;
			case 'csc':
				$var = Config_CreditCard::_INPUT_CSS_;
				break;
			case 'paymode':
				$var = Config_CreditCard::_INPUT_PAYMODE_;
				break;
			case 'incount':
				$var = Config_CreditCard::_INPUT_INCOUNT_;
				break;
			case 'submit':
				$var = Config_CreditCard::_INPUT_SUBMIT_;
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
	
	/**
	 * Payment by creditcard no.
	 * 
	 * @param  intger $price
	 * @return boolean
	 */
	function Payment($price, $label=null)
	{
		if(!$price){
			$this->StackError('empty price');
			return false;
		}
		
		//	url
		$url  = $this->Config()->GetURL('payment');
		$args = $this->Config()->GetArgsPayment($price,$label);
		
		//	get json
		$json = Toolbox::Curl( $url, $args, 'get' );
		
		/*
		$this->mark($url);
		$this->d($args);
		$this->d($json);
		*/
		
		//	error check
		if(isset($json['error'])){
			$this->StackError($json['error']);
			return false;
		}
		
		//	insert
		$uid = $json['uid'];
		$sid = $json['sid'];
		$insert = $this->Config()->insert_payment($uid, $sid, $price);
		$id = $this->pdo()->insert($insert);
		if(!$id){
			return false;
		}
		
		return true;
	}
	
}

class Config_CreditCard extends Config_Base
{
	private $_table_prefix = 'op';
	private $_table_name   = 'creditcard';
	
	function Set( $key, $var )
	{
		if(!isset($this->$key)){
			$this->StackError("Does not define this property. ($key)");
			return false;
		}
		
		$this->$key = $var;
	}
	
	function table_name()
	{
		return $this->_table_prefix.'_'.$this->_table_name;
	}
	
	function database()
	{
		return parent::database(array('user'=>'op_mdl_creditcar'));
	}
	
	function insert_payment( $uid, $sid, $amount )
	{
		$config = array();
		$config['table'] = $this->table_name();
		$config['set'][self::_COLUMN_UID_]    = $uid;
		$config['set'][self::_COLUMN_SID_]    = $sid;
		$config['set'][self::_COLUMN_AMOUNT_] = $amount;
		return $config;
	}
	
	/**
	 * !!CAUTION!!
	 * 
	 * Do not use this constant from external class.
	 * Because it may change. 
	 * Please use GetFormName, GetInputName method.
	 */
	const _FORM_NAME_     = 'form_creditcard';
	
	const _INPUT_CARD_NO_	 = 'creditcard_no';
	const _INPUT_EXP_YY_	 = 'creditcard_exp_yy';
	const _INPUT_EXP_MM_	 = 'creditcard_exp_mm';
	const _INPUT_CSS_		 = 'creditcard_csc';
	const _INPUT_PAYMODE_	 = 'creditcard_paymode';
	const _INPUT_INCOUNT_	 = 'creditcard_incount';
	const _INPUT_SUBMIT_	 = 'creditcard_submit';
	
	const _POST_CARD_NO_	 = 'cardno';
	const _POST_EXP_		 = 'exp';
	const _POST_CSC_		 = 'csc';
	const _POST_AMOUNT_		 = 'amount';
	const _POST_LABEL_		 = 'label';
	
	function form_creditcard()
	{
		$config = New Config();
		
		//	form
		$config->name = self::_FORM_NAME_;
		
		//	input
		$input_name = self::_INPUT_CARD_NO_;
		$config->input->$input_name->label	 = $this->i18n()->ja('カード番号');
		$config->input->$input_name->type	 = 'text';
		$config->input->$input_name->id		 = $input_name;
		$config->input->$input_name->validate->required = true;
		$config->input->$input_name->error->required = $this->i18n()->ja('この項目は必須入力です。');
		
		$input_name = self::_INPUT_CSS_;
		$config->input->$input_name->label	 = $this->i18n()->ja('セキュリティコード');
		$config->input->$input_name->type	 = 'text';
		$config->input->$input_name->id		 = $input_name;
		$config->input->$input_name->validate->required = true;
		$config->input->$input_name->error->required = $this->i18n()->ja('この項目は必須入力です。');
		
		$input_name = self::_INPUT_EXP_YY_;
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
		
		$input_name = self::_INPUT_EXP_MM_;
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
		
		$input_name = self::_INPUT_PAYMODE_;
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
		
		$input_name = self::_INPUT_INCOUNT_;
		$config->input->$input_name->label	 = $this->i18n()->ja('分割回数');
		$config->input->$input_name->type	 = 'select';
		$config->input->$input_name->id		 = $input_name;
		//	$config->input->$input_name->validate->required = true;
		
		foreach(array(1,2,3,4,5,6,10,12,24,36,48) as $num){
			$config->input->$input_name->options->$num->label = $this->i18n()->ja($num."回");
			$config->input->$input_name->options->$num->value = $num;
		}
		
		$input_name = self::_INPUT_SUBMIT_;
		$config->input->$input_name->type  = 'submit';
		$config->input->$input_name->value = 'submit';
		
		return $config;
	}
	
	function GetURL($key)
	{
		$url = 'http://api.uqunie.com/';
		switch($key){
			case 'payment':
				$url .= 'payment/';
				break;
		}
		return $url;
	}
	
	function GetArgsPayment( $amount, $label )
	{
		$form_name = self::_FORM_NAME_;
		$card_no = $this->form()->GetInputValue(self::_INPUT_CARD_NO_,$form_name);
		$exp_yy  = $this->form()->GetInputValue(self::_INPUT_EXP_YY_, $form_name);
		$exp_mm  = $this->form()->GetInputValue(self::_INPUT_EXP_MM_, $form_name);
		$csc     = $this->form()->GetInputValue(self::_INPUT_CSS_,    $form_name);
		
		$args[self::_POST_CARD_NO_]	 = $card_no;
		$args[self::_POST_EXP_]		 = $exp_yy.$exp_mm;
		$args[self::_POST_CSC_]		 = $csc;
		$args[self::_POST_AMOUNT_]	 = $amount;
		$args[self::_POST_LABEL_]	 = $label;
		
		return $args;
	}
	
	const _COLUMN_ID_		 = 'id';
	const _COLUMN_UID_		 = 'uid';
	const _COLUMN_SID_		 = 'sid';
	const _COLUMN_AMOUNT_	 = 'amount';
	
	const _COLUMN_UID_LENGHT_ = 16;
	const _COLUMN_SID_LENGHT_ = 16;
	
	function Selftest()
	{
		//  Get config
		$config = new Config();
		
		//	form setting
		$config->form->title	 = 'Setup to Creditcard table';
		$config->form->message	 = 'Please input to ![.bold[password]] of root user.';
		
		//	database setting
		$config->database = $this->database();
		
		//  Tables (op-user)
		$table_name = $this->table_name();
		$config->table->{$table_name}->table   = $table_name;
		$config->table->{$table_name}->comment = '';
		
		//  Columns
		$column_name = self::_COLUMN_UID_;
		$config->table->{$table_name}->column->{$column_name}->name		 = $column_name;
		$config->table->{$table_name}->column->{$column_name}->type		 = 'char';
		$config->table->{$table_name}->column->{$column_name}->length	 = self::_COLUMN_UID_LENGHT_;
		$config->table->{$table_name}->column->{$column_name}->comment	 = 'User ID';
		
		$column_name = self::_COLUMN_SID_;
		$config->table->{$table_name}->column->{$column_name}->name		 = $column_name;
		$config->table->{$table_name}->column->{$column_name}->type		 = 'char';
		$config->table->{$table_name}->column->{$column_name}->length	 = self::_COLUMN_SID_LENGHT_;
		$config->table->{$table_name}->column->{$column_name}->comment	 = 'Shopping ID';
		
		$column_name = self::_COLUMN_AMOUNT_;
		$config->table->{$table_name}->column->{$column_name}->name		 = $column_name;
		$config->table->{$table_name}->column->{$column_name}->type		 = 'int';
		$config->table->{$table_name}->column->{$column_name}->comment	 = 'Charge amount';
		
		$name = 'created';
		$config->table->$table_name->column->$name->type = 'datetime';
		
		$name = 'updated';
		$config->table->$table_name->column->$name->type = 'datetime';
		
		$name = 'deleted';
		$config->table->$table_name->column->$name->type = 'datetime';
		
		$name = 'timestamp';
		$config->table->$table_name->column->$name->type = 'timestamp';
		
		return $config;
	}
}
