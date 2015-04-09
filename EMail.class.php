<?php
/**
 * EMail.class.php
 * 
 * @creation  2015-04-08
 * @version   1.0
 * @package   op-core
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2009 (C) Tomoaki Nagahara All right reserved.
 */

/**
 * EMail
 * 
 * @creation  2015-04-08
 * @version   1.0
 * @package   op-core
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2009 (C) Tomoaki Nagahara All right reserved.
 */
class EMail extends OnePiece5
{
	private $_head = array();
	private $_body = array();
	private $_debug = array();
	
	function Debug()
	{
		if(!$this->Admin()){
			return;
		}
		
		$this->P(__METHOD__,'h1');
		$this->D($this->_debug);
	}
	
	function From($addr, $name=null)
	{
		$from['addr'] = $addr;
		$from['name'] = $name;
		$this->_head['from'][] = $from;
	}
	
	function To($addr, $name=null)
	{
		$to['addr'] = $addr;
		$to['name'] = $name;
		$this->_head['to'][] = $to;
	}
	function Cc($addr, $name=null)
	{
		$cc['addr'] = $addr;
		$cc['name'] = $name;
		$this->_head['cc'][] = $cc;
	}
	
	function Bcc($addr, $name)
	{
		$bcc['addr'] = $addr;
		$bcc['name'] = $name;
		$this->_head['bcc'][] = $bcc;
	}
	
	function Subject($subject)
	{
		$this->_head['subject'] = $subject;
	}

	function Content($content, $mime='text/plain')
	{
		$body['body'] = $content;
		$body['mime'] = $mime;
		$this->_body[] = $body;
	}
	
	function Send($type=null)
	{
		$save_lang = mb_language();
		$save_char = mb_internal_encoding();

		mb_language('uni');
		mb_internal_encoding('utf-8');
		
		switch($type){
			case 'mta':
				$reslut = $this->_mta();
				break;
			case 'socket':
				$reslut = $this->_socket();
				break;
			default:
				$reslut = $this->_mail();
		}
		
		$reslut['time'] = date('Y-m-d H:i:s').' ('.gmdate('e Y-m-d H:i:s P').')';
		$this->_debug[] = $reslut;
		
		mb_language($save_lang);
		mb_internal_encoding($save_char);
	}
	
	private function _mta()
	{
		
	}
	
	private function _socket()
	{
		
	}
	
	private function _mail()
	{
		//	init
		$to = $this->_get_to(null);
		$subject = $this->_get_subject();
		$content = $this->_get_content();
		$headers = $this->_get_headers();
		$parameters = $this->_get_parameters();
		
		//	Debug
		if( $this->Admin() ){
			$debug['method'] = __METHOD__;
			$debug['to'] = $to;
			$debug['subject'] = $subject;
			$debug['content'] = $content;
			$debug['headers'] = $headers;
			$debug['parameters'] = $parameters;
		}
		
		//	Send mail.
		if(!$io = mail($to, $subject, $content, $headers, $parameters)){
			OnePiece5::P('Failed to send the error mail.');
		}
		
		return $debug;
	}
		
	private function _get_headers()
	{
		$content_type = $this->_get_content_type();
		$mail_address = $this->_get_mail_address();
		$headers = trim($content_type)."\n".trim($mail_address)."\n";
		return OnePiece5::Escape($headers);
	}
	
	private function _get_mail_address()
	{
		foreach(array('from','to','cc','bcc') as $key){
			if(empty($this->_head[$key])){ continue; }
			$full_name = array();
			foreach($this->_head[$key] as $temp){
				$addr = $temp['addr'];
				$name = $temp['name'];
				$full_name[] = $this->_get_full_name($addr, $name);
			}
			$key = ucfirst($key);
			$header[] = "$key: ".join(', ',$full_name);
		}
		return join("\n", $header);
	}
	
	private function _get_from($prefix='From: ')
	{
		$addr = $this->_head['from']['addr'];
		$name = $this->_head['from']['name'];
		return $prefix.$this->_get_full_name($addr, $name);
	}
	
	private function _get_to($prefix='To: ')
	{
		$join = array();
		foreach($this->_head['to'] as $temp){
			$addr = $temp['addr'];
			$name = $temp['name'];
			$join[] = $this->_get_full_name($addr, $name);
		}
		return $prefix.join(', ',$join);
	}
	
	private function _get_cc()
	{
		$join = array();
		foreach($this->_head['cc'] as $temp){
			$addr = $temp['addr'];
			$name = $temp['name'];
			$join[] = $this->_get_full_name($addr, $name);
		}
		return "Cc: ".join(', ',$join);
	}

	private function _get_bcc()
	{
		$join = array();
		foreach($this->_head['bcc'] as $temp){
			$addr = $temp['addr'];
			$name = $temp['name'];
			$join[] = $this->_get_full_name($addr, $name);
		}
		return "Bcc: ".join(', ',$join);
	}
	
	private function _get_full_name($addr, $name)
	{
		$addr = trim($addr);
		if( $name ){
			$name = mb_encode_mimeheader($name);
			$full_name = trim("$name <$addr>");
		}else{
			$full_name = $addr;
		}
		return $full_name;
	}
	
	private function _get_parameters()
	{
		//	parameters
		$local_user  = get_current_user().'@'.$_SERVER['SERVER_ADDR'];
		$parameters = "-f $local_user";
		return $parameters;
	}
	
	private function _get_content_type()
	{
		return "Content-type: text/html; charset=UTF-8";
	}
	
	private function _get_content()
	{
		foreach($this->_body as $body){
			$join[] = $body['body'];
		}
		return join('',$join);
	}

	private function _get_subject()
	{
		return mb_encode_mimeheader($this->_head['subject']);
	}
}
