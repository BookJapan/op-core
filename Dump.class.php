<?php
/**
 * Dump
 * 
 * 2006: dump.inc.php > 2011: OnePiece::dump > 2012: dump.class.php
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
	static function D( $args, $lifetime=null ){
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
		
		if(!$calls){
			print self::PrintDumpStyleSheet();
			print self::PrintDumpJavaScript();
		}
		
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
			return '<div class="dump" style="padding: 1px 0px;">'.$table.'</div>';
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
			
			if(gettype($var) == 'object'){
				$key .= ' ('.get_class($var).')';
				$value = self::GetProperty($var);
			}else
			if(gettype($var) == 'string'){
				$value = sprintf('[%s(%s)] %s', gettype($var), mb_strlen($var), $var );
			}else{
				$value = sprintf('[%s] %s', gettype($var), $var );
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
		return sprintf('<table class="op">%s</table>', $united );
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
			$tr = sprintf('<tr>%s%s</tr>', $td_of_key, $td_of_value);
		}else{
			$tr = sprintf('<tr>%s</tr>', $td_of_value);
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
		return sprintf('<td class="key"><div class="dkey ridge" did="%s">%s</div></td>', $did, $args);
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
	static function GetTdOfValue( $args, $did, $lifetime, $history, $label_flag=true ){
		switch($type = gettype($args)){
			
			case 'NULL':
			case 'boolean':
				$args = self::ConvertArgs( $type, $args );
				
			case 'resource':
			case 'string':
			case 'integer':
			case 'double':
				if( $type == 'string' ){
					$args = self::Escape($args);
				}
				$label = $label_flag ? self::GetLabel( $type, $args ) : '';
				$html = sprintf('<td class="value"><div id="%s" class="ridge">%s%s</div></td>', $did, $label.' ', $args);
				break;
			
			case 'object':
			case 'array':
				if(!count($args)){
//					$args = 'empty';
				}
				$args = self::GetDump( $args, $lifetime-1, $history, $label_flag );
				$html = sprintf('<td class="ridge"><div id="%s" class="small">%s</div></td>', $did, $args);
				break;
				
			default:
				$args = sprintf('unsupported type: %s (%s)', $type, __LINE__);
				$html = sprintf('<td class="value"><div id="%s" class="ridge">[%s] %s</div></td>', $did, $type, $args);
		}
		
		return $html;
	}
	
	static function GetLabel( $type, $args )
	{
		$class  = $type;
		$length = '';
		switch($type){
			case 'string':
				$length = '('.mb_strlen($args).')';
				break;
				
			case 'object':
				$type = get_class($args);
				break;
				
			default:
		}
		return sprintf('[<span class="%s">%s%s</span>]', $class, $type, $length);
	}
	
	static function ConvertArgs( $type, $args ){
		switch($type){
			case 'boolean':
				$temp = $args ? 'true': 'false';
				break;
			case 'NULL':
				$temp = 'null';
				break;
			default:
				$temp = $args;
		}
		
		return sprintf('<span class="%s">%s</span>', $temp, $temp);
	}
	
	/**
	 * 
	 * @param  string $args
	 * @return string
	 */
	static function Escape($args){
		if( is_string($args) or is_integer($args)){
			// OK
		}else{
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
	
	static function PrintDumpStyleSheet(){
		print <<<__FINISH__
<style type="text/css">
div.dump{
  color:            black;
  _background-color: white;
  font-size:        9pt;
  font-weight:      normal;
  text-decoration:  none;
  direction: ltr;
}

div.ridge{
  font-size: 9pt;
  padding:1px;
  border-width: 1px;
  border-style: solid;
  border-color: #ddd #aaa #aaa #ddd;
}

.dump .ridge span{
  font-size: 9pt;
}

.dump table{
  margin-top:    0px;
  margin-bottom: 0px;
  border-collapse:collapse;
  border-width: 1px;
  border-style: solid;
  border-color: #ddd #aaa #aaa #ddd;
}

.dump tr{
  background-color: white;
}

.dump th{
  background-color: white;
}

.dump td{
  margin:  0px;
  padding: 1px;
  background-color: white;
}

td.key{
  vertical-align: top;
}

td.value{

}

.dkey{
  cursor:pointer;
}

span.string{
  color: #909090;
}

span.integer{
  color: #8f8fff;
}

span.double{
  color: #3030ff;
}

span.boolean{
  color: green;
}

span.object{
  color: orange;
}

span.resource{
  color: orange;
}

span.hidden{
  display: none;
}

span.true{
  color: blue;
}

span.false{
  color: red;
}

span.null{
  color: red;
}

span.NULL{
  color: red;
}

</style>
__FINISH__;
	}
	
	static function PrintDumpJavaScript(){
		print <<<__FINISH__
<script type="text/javascript">
if( window.attachEvent ){
	window.attachEvent('onload', dump);
}else{
	window.addEventListener('load', dump, false);
}

function dump(){
	
	//	console	
	var div = document.createElement('div');
	div.id = 'console';
	div.style.margin             = '0px';
	div.style.padding            = '0px';
	div.style.color				 = 'black';
	div.style.fontSize			 = '9pt';
	div.style.backgroundColor	 = 'white';
	var tags = document.getElementsByTagName('body');
	var ref = tags[0].insertBefore( div, tags[0].firstChild );

	//	Web Strage	
	var length = sessionStorage.length;
	for( var i=0; i<length; i++ ){
		var dump_id = sessionStorage.key(i);
		var io = sessionStorage.getItem(dump_id);
		if( io == 0 ){
			var tmp = document.getElementById(dump_id);
			if(tmp){
				tmp.style.display = 'none';
			}
		}
	}
	
	//	addEventListener
	var tags = document.getElementsByClassName('dkey');
	for( var i=0; i<tags.length; i++){
		var did = tags[i].getAttribute('did');
		tags[i].addEventListener('click',d2,true);
	}
	
//	oplog('Dump.class.php');
};

function d2(e){
	var dump_id = e.target.getAttribute('did');
	var div = document.getElementById(dump_id);
	if( div.style.display == 'none' ){
		var io = 1;
		div.style.display = 'block';
	}else{
		var io = 0;
		div.style.display = 'none';
	}
	sessionStorage.setItem( dump_id, io );

	e.stopPropagation();
};

function oplog(str){
	var p = document.createElement('p');
	p.style.margin  = '0px';
	p.style.padding = '0px';
	p.innerHTML     = str;
	if(document.getElementById('console')){
		document.getElementById('console').appendChild(p);
	}
};

</script>
__FINISH__;
	}
}
