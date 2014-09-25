<?php
/**
 * Wiki2Engine
 * 
 * Basic description
 *  -- ![attribute[contents]]
 * 
 * Example
 *  -- ![span .class_name #id_name 0xfc9 [lorem ipsum]]
 * 
 * Attribute description
 *  -- A word to begin in a dot is a CLASS name.
 *     A word to begin in a sharp is a ID name.
 *     A word to begin in a alphabet is a TAG name.
 *     A color is expressed with the hexadecimal number notation.
 *     
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 *
 */
class Wiki2Engine extends OnePiece5
{
	static $pattAttr = '[-_a-z0-9:#\.\s]+';
	
	static function Wiki2( $string, $options = null ){
		
		if( is_null($options) and true ){
			$options['id']     = false;
			$options['tag']    = false;
			$options['class']  = true;
			$options['style']  = false;
			$options['script'] = false;
		}
		
		$string  = parent::Escape($string);
		$elements = array();
		
		// Rightmost shortest match
		$pattern = '/!\[('.self::$pattAttr.')\[((?:(?!\!\['.self::$pattAttr.'\[.+?\]{2}).)*?)\]{2}/s';
		
		$i = null;
		while(preg_match($pattern, $string, $match)){
			$i++;
			$replace = sprintf('__%s_%s__', $i, md5($match[0]));
			
			$engine['key']  = $replace;
			$engine['var']  = $match[0]; // all
			$engine['attr'] = $match[1]; // attribute (div, .class, #id)
			$engine['body'] = $match[2]; // body (tag's inner content)
			$elements[] = $engine;
			$string = preg_replace($pattern, $replace, $string, 1);
		}
		
		foreach( array_reverse($elements) as $element ){
			$key  = $element['key'];
			$var  = $element['var'];
			$attr = $element['attr'];
			$body = $element['body'];
			
			//  Build HTML
			$value = self::Build( $attr, $body, $options );
			
			//  Recovery source string.
			$string = preg_replace( "/$key/", $value, $string );
		}
		
		return $string;
	}
	
	static function Build( $attribute, $body, $options ){
		$nl   = OnePiece5::GetEnv('newline');
		$attr = self::GetAttribute( $attribute, $options );
		
		$tag   = $attr['tag'];
		$id    = $attr['id'];
		$class = $attr['class'];
		$style = $attr['style'];
		
		//  touch body.(tag's inner content)
		switch( strtolower($tag) ){
			case 'a':
				list( $href, $label ) = explode('|',$body.'|');
				$body  = $label ? trim($label): trim($href);
				$added = sprintf('href="%s"',trim($href));
				break;
			case 'img':
				$added = sprintf('src="%s"',$body);
				$body  = null;
				break;
			default:
				$added = null;
		}
		
		return sprintf('<%s id="%s" class="%s" style="%s" %s wiki2="true">%s</%s>',
				 $tag, $id, $class, $style, $added, $body, $tag);
	}
	
	static function GetAttribute( $attribute, $options )
	{
		foreach(explode(' ',trim($attribute)) as $temp){
			
			if(preg_match('/^([a-z]+)$/i',$temp,$m)){
				$tag = $m[1];
			}else if(preg_match('/^\.([-_a-z0-9]+)/',$temp,$m)){
				$class[] = $m[1];
			}else if(preg_match('/^\#([-_a-z0-9]+)/',$temp,$m)){
				$id[] = $m[1];
			}else if(preg_match('/^0x([0-0a-f]{3,6})/i',$temp,$m)){
				$color[] = 'color:#'.$m[1];
			}else if(preg_match('/^([-_a-z0-9]+):([-_a-z0-9]+)/',$temp,$m)){
				if( $m[1] === 'colspan' or $m[1] === 'rowspan' ){
					$tag .= " {$m[1]}={$m[2]}"; 
				}else{
					$style[] = $m[1].':'.$m[2];
				}
			}else{
			//	var_dump($temp);
			}
		}
		
		// TODO: $tagがunsetされる、、、なぜ？！
		if(isset($tag)){
			$save_tag = $tag;
		}
		
		//  delete deny attribute
		if($options){
			foreach( $options as $key => $var ){
				if(!$var){
					if( isset(${$key}) ){
						unset(${$key});
					}
				}
			}
		}
		
		//  color is merge to style
		if( isset($color) ){
			if( isset($style) ){
				$style += $color;
			}else{
				$style = $color;
			}
		}
				
		$attr['id']     = isset($id)    ? $id: '';
		$attr['tag']    = isset($save_tag )   ? $save_tag : 'span';
		$attr['class']  = isset($class) ? implode(' ',  $class): '';
		$attr['style']  = isset($style) ? implode('; ', $style): '';
		
		return $attr;
	}
}
