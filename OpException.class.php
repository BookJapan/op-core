<?php
/**
 * OpException.class.php
 *
 * Creation: 2015-03-02
 *
 * @version   1.0
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2015 (C) Tomoaki Nagahara All right reserved.
 * @package   op-core
 */

/**
 * OpException
 *
 * Creation: 2015-03-02
 *
 * @version   1.0
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2015 (C) Tomoaki Nagahara All right reserved.
 * @package   op-core
 */
class OpException extends Exception
{
	private $_wizard = null;
	private $_isSelftest = null;
	
	/**
	 * Message's original language.
	 * 
	 * @var string
	 */
	private $_lang = null;

	/**
	 * @param message[optional]
	 * @param lang[optional]
	 * @param code[optional]
	 * @param previous[optional]
	 */
	public function __construct( $message, $lang=null, $code=null, $previous=null )
	{
		if(!is_string($message)){
			$message = "Passed error message is not a string. \Caller: ".OnePiece5::GetCallerLine()."\ ";
			$lang = 'en';
		}
		if( $lang ){
			$this->_lang = $lang;
		}
		parent::__construct( $message, $code, $previous );
	}
	
	function getLang()
	{
		return $this->_lang;
	}
	
	function isSelftest($var=null)
	{
		if( $var ){
			$this->_isSelftest = $var;
		}
		return $this->_isSelftest;
	}
	
	function SetWizard()
	{
		OnePiece5::SetEnv('wizard',true);
	}
	
	function GetWizard()
	{
		return OnePiece5::GetEnv('wizard');
	}
}