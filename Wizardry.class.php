<?php
/**
 * Wizardry.class.php
 * 
 * @version   1.0
 * @package   op-core
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2015 (C) Tomoaki Nagahara All right reserved.
 */

/**
 * Wizardry
 *
 * @version   1.0
 * @package   op-core
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2015 (C) Tomoaki Nagahara All right reserved.
 */
class Wizardry extends OnePiece5
{
	/**
	 * @return Config_Wizardry
	 */
	function Config()
	{
		static $config;
		if(!$config){
			$config = new Config_Wizardry();
		}
		return $config;
	}
	
	function Init()
	{
		parent::Init();
		$form = $this->Config()->form_config();
		$this->Form()->AddForm($form);
	}
	
	function PrintForm()
	{
		$this->Template('op:/Template/wizardry.phtml');
	}
	
	function Secure()
	{
		$form_name = $this->Config()->form_name();
		return $this->Form()->Secure($form_name);
	}
	
	function GetUser()
	{
		$form_name = $this->Config()->form_name();
		return $this->Form()->GetInputValue('user', $form_name);
	}
	
	function GetPassword()
	{
		$form_name = $this->Config()->form_name();
		return $this->Form()->GetInputValue('password', $form_name);
	}
}

class Config_Wizardry extends OnePiece5
{
	function form_name()
	{
		return "_wizardry_";
	}
	
	function form_config()
	{
		$form = new Config();
		$form->name = $this->form_name();
		
		$input_name = 'user';
		$form->input->$input_name->type	 = 'text';
		$form->input->$input_name->value = 'root';
		$form->input->$input_name->required	 = true;

		$input_name = 'password';
		$form->input->$input_name->type	 = 'password';
		$form->input->$input_name->required	 = true;

		$input_name = 'submit';
		$form->input->$input_name->type	 = 'submit';
		$form->input->$input_name->value = ' Submit ';
		
		return $form;
	}
}
