<?php
/**
 * Developer.class.php
 * 
 * Set the only features that developers use.
 * 
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 */

//	Developer is not read from not admin.
if(!OnePiece5::Admin()){
	OnePiece5::StackError("!!ATTENSION!!");
}

/**
 * Developer
 * 
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 */
class Developer extends OnePiece5
{
	/**
	 * Save mark-label for use footer links.
	 *
	 * @param unknown $mark_label
	 */
	static function SetMarkLabel( $mark_label )
	{
		//  Use footer link
		if( empty($_SERVER[__CLASS__]['MARK_LABEL'][$mark_label]) ){
			$_SERVER[__CLASS__]['MARK_LABEL'][$mark_label] = true;
		}
	}
	
	/**
	 * Get save value from session.
	 * 
	 * @param unknown $mark_label
	 * @return NULL
	 */
	static function GetSaveMarkLabelValue( $mark_label )
	{
		return isset($_SESSION[__CLASS__]['MARK_LABEL'][$mark_label]) ?
		$_SESSION[__CLASS__]['MARK_LABEL'][$mark_label]:
		null;
	}
	
	/**
	 * Save on/off flag to session by get value.
	 * 
	 * @param string $mark_label
	 * @param string $mark_value
	 */
	static function SaveMarkLabelValue( $mark_label=null, $mark_value=null )
	{
		//
		if( $mark_label ){
			$_SESSION[__CLASS__]['MARK_LABEL'][$mark_label] = $mark_value;
		}
	}
	
	static function PrintGetFlagList()
	{
		// Only admin
		if(!OnePiece5::admin()){
			return;
		}
		
		//	If CLI case
		if( OnePiece5::GetEnv('cli') ){
			return;
		}
		
		//	Check MIME
		if( Toolbox::GetMIME() !== 'text/html' ){
			return;
		}
		
		//  Hide mark label links setting.
		$key = 'hide_there_links';
		$str = 'Hide there links';
		$var = 1;
		if( self::GetSaveMarkLabelValue($key) ){
			return;
		}
		
		//  Mark label links
		$join = array();
		$join[] = sprintf('<a href="?mark_label=%s&mark_label_value=%s">%s</a>', $key, $var, $str);
		
		if( isset($_SERVER[__CLASS__]) ){
			foreach( $_SERVER[__CLASS__]['MARK_LABEL'] as $mark_label => $null ){
				$key = $mark_label;
				$var = self::GetSaveMarkLabelValue($mark_label);
				$str = $var ? 'Hide': 'Show';
				$var = $var ? 0: 1;
				$join[] = sprintf('<a href="?mark_label=%s&mark_label_value=%s">%s %s info</a>', $key, $var, $str, $key);
			}
		}
		
		print '<!-- '.__FILE__.' - '.__LINE__.' -->';
		if( $join ){
			print '<div style="font-size:small;">[ '.join(' | ', $join).' ]</div>';
		}
	}
}
