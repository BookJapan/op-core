<?php
/**
 * Dump.class.php
 * 
 * 2006: dump.inc.php > 2011: OnePiece::dump > 2012: dump.class.php
 * 
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2006 (C) Tomoaki Nagahara All right reserved.
 */
/**
 * Dump
 * 
 * @version   1.0
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2006 (C) Tomoaki Nagahara All right reserved.   
 */
class Dump
{
	/**
	 * Dump
	 * 
	 * @param mixed   $args
	 * @param integer $depth
	 */
	static function D( $args, $lifetime=null )
	{
		//	JS, CSS
		self::PrintAttach();
		
		if( is_array($args) and count($args) === 0 ){
			print self::GetDump( ' ', null, null, false );
			return;
		}
		self::PrintDump( $args, $lifetime );
	}
	
	static function PrintDump( $args, $lifetime=null ){
		print self::GetDump( $args, $lifetime );
	}
	
	/**
	 * This method nested call.
	 * 
	 * @param $args
	 * @param $lifetime   nest limit.
	 * @param $history    nesting history.(use create did)
	 * @param $label_flag true is print value type.
	 */
	static function GetDump( $args, $lifetime=null, $history=null, $label_flag=true ){
		static $calls;
		static $depth;
		
		$depth++;
		
		//  $calles is count calling times. use did, create uniq key.
		if(!$history){
			$calls++;
		}
		
		//  lifetime
		if( $lifetime === null ){
			$lifetime = 10;
		}
		
		//  nest limit
		if($lifetime < 0){
			$depth--;
			return sprintf('<div class="ridge">death...(nesting is maximum)</div>');
		}
		
		$table = '';
		$tr = '';
		switch($type = gettype($args)){
				
			case 'object':
				/*
				is_a()
				is_subclass_of()
				*/
				$table = self::CaseOfObject( $args, $lifetime -1, $history."$calls, " );
				break;
				
			case 'array':
				foreach($args as $key => $value){
					// stack history
					$history .= "$key, ";
					
					// create did
					$did = md5($calls . $history);
					
					$td_of_key   = self::GetTdOfKey($key, $did);
					$td_of_value = self::GetTdOfValue($value, $did, $lifetime, $history, $label_flag);
					$tr[] = self::GetTr( $td_of_key, $td_of_value );
				}
			
				$table .= self::GetTable($tr);
				break;
				
			default:
				$td_of_key   = null;
				$td_of_value = self::GetTdOfValue( $args, null, null, null, false );
				$tr    = self::GetTr($td_of_key, $td_of_value);
				$table = self::GetTable( $tr );
		}
		
		$depth--;
		
		if(!$depth){
			//	root hierarchy
			return '<div class="op-dump-root"><div class="op-dump-ridge">'.$table.'</div></div>';
		}else{
			return $table;
		}
	}
	
	static function GetProperty($args)
	{
		$prop = array();
		$prop['private']   = array();
		$prop['protected'] = array();
		$prop['public']    = array();
		foreach((array)$args as $key => $var ){
			
			switch( $type = gettype($var) ){
				case 'object':
					$key .= ' ('.get_class($var).')';
					$value = self::GetProperty($var);
					break;
					
				case 'array':
					$value = self::GetDump($var);
					break;
					
				case 'string':
					$value = sprintf('[%s(%s)] %s', gettype($var), mb_strlen($var), $var );
					break;
					
				default:
					$value = "[{$type}] {$var}"; // sprintf('[%s] %s', gettype($var), $var );
			}
			
			$temp = explode("\0",$key);
			if(count($temp) == 1){
				$prop['public'][$key] = $value;
			}else{
				if( $temp[1] == '*' ){
					$prop['protected'][$temp[2]] = $value;
				}else{
					$prop['private'][$temp[1]][$temp[2]] = $value;
				}
			}
		}
		
		if( count($prop['private']) == 0 ){
			unset($prop['private']);
		}
		
		if( count($prop['protected']) == 0 ){
			unset($prop['protected']);
		}
		
		return $prop;
	}
	
	static function CaseOfObject( $args, $lifetime, $history, $label_flag=false ){
		static $calls;
		
		// ready reflection
		$class_name = get_class($args);
//		$reflection = new ReflectionClass($class_name);
		$reflection = new ReflectionClass($args);
		
		$class['file'] = $reflection->getFileName();
		
		// parent class
		$parent_class = get_class($args);
		while( $parent_class = get_parent_class($parent_class) ){
			$class['parent'][] = $parent_class;
		}

		//  get parents class properties
		if( 0 ){
			var_dump($reflection->getParentClass()->getDefaultProperties());
			
			$parent_class = $reflection->getParentClass();
			$class_name = get_class($parent_class);
			$class['parent'][$class_name]  = self::GetDump( $args, $lifetime-1, $history, $label_flag);
		}
		
		// modifier
		if( $modifier = $reflection->getModifiers() ){
			$class['modifier'] = $modifier;
		}
		
		// name space
		if( $name_namespace = $reflection->getNamespaceName() ){
			$name_shortname = $reflection->getShortName();
			$class['Namespace Name'] = $name_namespace;
			$class['Short Name']     = $name_shortname;
		}
		
		// constants
		if( $constants = $reflection->getConstants()){
			$class['constants'] = $constants;
		}
		
		// Easy properties
//		$class['properties_'] = self::GetProperty($args);

		//  use reflection
		$class['properties']['private']   = array();
		$class['properties']['protected'] = array();
		$class['properties']['public']    = array();
		
		// properties
//		$properties = $reflection->getProperties();
//		var_dump($properties);
		
		if(!$properties = $reflection->getProperties()){
//			var_dump($properties);
			$class['properties'] = self::GetProperty($args);
		}


		// properties detail
		foreach( $properties as $key => $value ){
		
			if( $reflection->hasProperty($key) ){
				$temp = $reflection->getProperty($key);
				$temp->setAccessible(true);
				$modifier = $temp->getModifiers() . ': ';
				$static   = $temp->isStatic() ? ' static: ': '';
				
				$default_value = $value;
				$current_value = $temp->getValue($args);
				
			}else{
				$class['properties']['public'][$key] = $value;
				continue;
			}
			
			if($default_value != $current_value){
				$value = sprintf('%s (%s)', $current_value, $default_value );
			}
			
			switch($modifier){
				case 256:
					$class['properties']['public'][$key] = $value;
					break;
				case 512:
					$class['properties']['protected'][$key] = $value;
					break;
				case 1024:
					$class['properties']['private'][$key] = $value;
					break;
				case 257:
					$class['properties']['public'][$key] = '[static] '.$value;
					break;
				case 513:
					$class['properties']['protected'][$key] = '[static] '.$value;
					break;
				case 1025:
					$class['properties']['private'][$key] = '[static] '.$value;
					break;
				default:
					$class['properties'][$modifier][$key] = $value;
			}
		}
		
		// create did
		$history .= "$class_name, ";
		$did = md5($history);
		
		// td
		$td_key = self::GetTdOfKey( $class_name, $did);
		$td_var = self::GetTdOfValue( $class, $did, $lifetime, $history, false);
		
		// tr
		$tr = self::GetTr( $td_key, $td_var );
		
		// table
		$table = self::GetTable( $tr );
		
		return $table;
	}
	
	/**
	 * Get TABLE tag from TR tag.
	 * 
	 * @param $tr
	 * @return string $table
	 */
	static function GetTable( $tr ){
		if(is_string($tr)){
			$united = $tr;
		}else if(is_array($tr)){
			$united = implode('',$tr);
		}else{
			return sprintf('unsupported type: %s (%s)', gettype($tr), __LINE__);
		}
		return sprintf('<table class="op-dump-table">%s</table>', $united );
	}
	
	/**
	 * Get TR tag join key-TD and value-TD.
	 * 
	 * @param $td_of_key
	 * @param $td_of_value
	 * @return string $tr
	 */
	static function GetTr( $td_of_key, $td_of_value ){
		if( $td_of_key ){
			$tr = sprintf('<tr class="op-dump-tr">%s%s</tr>', $td_of_key, $td_of_value);
		}else{
			$tr = sprintf('<tr class="op-dump-tr">%s</tr>', $td_of_value);
		}
		return $tr;
	}
	
	/**
	 * Get TD tag of Keys.
	 * 
	 * @param $args
	 * @param $did
	 * @return string $td_of_key
	 */
	static function GetTdOfKey( $args, $did ){
		$args = self::Escape($args);
		return sprintf('<th class="op-dump-th"><div class="dkey op-dump-ridge" did="%s"><div class="op-dump-key">%s</div></div></th>', $did, $args);
	}
	
	/**
	 * Get TD tag of Values.
	 * 
	 * @param $args
	 * @param $did
	 * @param $lifetime
	 * @param $history
	 * @return string $td_of_value
	 */
	static function GetTdOfValue( $args, $did, $lifetime, $history, $label_flag=true )
	{
		$type = strtolower(gettype($args));
		$class = 'op-dump-value';
		
		if( $type === 'null' ){
			$args = '';
		}else
		
		if( $type === 'boolean' ){
			$args = $args ? '<span class="blue">true</span>': '<span class="red">false</span>';
		}else
		
		if( $type === 'array' or $type === 'object' ){
			$class = null;
			$args = self::GetDump( $args, $lifetime-1, $history, $label_flag );
		}else{
			if( $type === 'string' ){
				$args = self::Escape($args);
			}
		}

		$label = $label_flag ? self::GetLabel( $type, $args ).' ' : '';
		$value = "<div class=\"$class\">$label<span class=\"op-dump-value-string\">$args</span></div>";
		$html = sprintf('<td class="op-dump-td"><div id="%s" class="op-dump-ridge">%s</div></td>', $did, $value);
		
		return $html;
	}
	
	static function GetLabel( $type, $args )
	{
		$class  = $type;
		$length = '';
		
		if( $type === 'string' ){
			$length = '('.mb_strlen($args).')';
		}else if( $type === 'array' or $type === 'object' ){
			return '';
		}
		
		return sprintf('<span class="op-dump-label">[<span class="%s">%s%s</span>]</span>', $class, $type, $length);
	}
	
	/**
	 * Escape string.
	 * 
	 * @param  string $args
	 * @return string
	 */
	static function Escape($args)
	{
		if( is_integer($args) ){
			return $args;
		}else if(!is_string($args)){
			return 'this is not string.('.gettype($args).')';
		}
		
		$patt = array("\0","\r","\n","\t","\v","\a","\b","\f","\z","\e");
		$repl = array('[\0]','[\r]','[\n]','[\t]','[\v]','[\a]','[\b]','[\f]','[\z]','[\e]');
		$args = str_replace( $patt, $repl, $args);
		if( version_compare(PHP_VERSION , '5.2.3') >= 0 ){
			return htmlentities( $args, ENT_QUOTES, 'UTF-8', false);
		}else{
			return htmlentities( $args, ENT_QUOTES, 'UTF-8');
		}
	}
	
	static function PrintAttach()
	{
		//	Only once.
		static $print;
		if(!$print){
			$print = true;
		}else{
			return;
		}
		
		//	If html
		if(!Toolbox::isHtml()){
			return;
		}
		
		//	Print
		print self::PrintDumpStyleSheet();
		print self::PrintDumpJavaScript();
	}
	
	static function PrintDumpStyleSheet()
	{
		static $_print;
		if( $_print ){
			return;
		}
		$_print = true;
		
		$meta = "op:/Template/dump.css";
		$path = OnePiece5::ConvertPath($meta);
		if(file_exists($path)){
			$file = file_get_contents($path);
			print '<style type="text/css">';
			print $file;
			print '</style>';
		}else{
			print "Does not exists $meta.";
		}
	}
	
	static function PrintDumpJavaScript()
	{
		static $_print;
		if( $_print ){
			return;
		}
		$_print = true;

		$meta = "op:/Template/dump.js";
		$path = OnePiece5::ConvertPath($meta);
		if(file_exists($path)){
			$file = file_get_contents($path);
			print '<script type="text/javascript">';
			print $file;
			print '</script>';
		}else{
			print "Does not exists $meta.";
		}
	}
}
