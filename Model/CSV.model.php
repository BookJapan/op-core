<?php
/**
 * CSV.model.php
 * 
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 */
/**
 * Model_CSV
 * 
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 */
class Model_CSV extends Model_Model
{
	private $_error;
	private $_file_path;
	private $_file_handle;
	private $_locale_of_csv;
	private $_column_names;
	
	function Help()
	{
		$this->p('![a[http://onepiece-framework.com/help/model/csv]]');
	}
	
	/**
	 * Set the CSV's locale.
	 * 
	 * <pre>
	 * Japanese is 'ja_JP.SJIS'
	 * 
	 * How to set the locale to the Linux.
	 * http://bobineko.biz/linux/centos/set_locale.html
	 * 
	 * Locale check.
	 * locale -a | grep ja
	 *
	 * Locale add.
	 * # Linux
	 * localedef -f SHIFT_JIS -i ja_JP /usr/lib/locale/ja_JP.SJIS
	 * 
	 * Locale delete
	 * rm -fr /usr/lib/locale/ja_JP.SJIS
	 * </pre>
	 * 
	 * @param  string  $locale
	 * @return boolean
	 */
	function SetLocaleOfCSV( $locale )
	{
		if(!$locale){
			$this->_error = "Please set locale at arguments.";
			return false;
		}
		
		//	Save csv file's locale.
		$this->_locale_of_csv = $locale;
		
		return true;
	}
	
	function GetError()
	{
		$english = $this->i18n()->En($this->_error,'En');
		$translate = $this->i18n()->En($this->_error);
		return "$translate ($english)";
	}
	
	function SetFile( $file_path )
	{
		//	Convert meta path.
		$file_path = $this->ConvertPath($file_path);
		
		if(!file_exists($file_path)){
			$this->_error = "Does not exists this file. \($file_path)\ ";
			return false;
		}
		
		//	read of csv
		if(!$this->_file_handle = fopen( $file_path, 'r' )){
			$this->_error = "Failed open this file. \($file_path)\ ";
			return false;
		}
		
		return true;
	}
	
	function GetLine( $type='array' )
	{
		//	locale check
		if(!$this->_locale_of_csv){
			$this->_error = "Does not init locale. Please call SetLocaleOfCSV method.";
			return false;
		}
		
		//	encoding
		$encoding_to = Env::Get('charset');
		list($lang_country, $encoding_from) = explode('.',$this->_locale_of_csv);
		
		//	Save current locale.
		$locale = setlocale(LC_ALL, 0);
		
		//	Case of linux.
		if(!$io = setlocale(LC_ALL,$this->_locale_of_csv)){
			$this->_error = "Failed \setlocale\. Please call \Help\ method.";
			return false;
		}
		
		//	Get line.
		if(!$array = fgetcsv( $this->_file_handle, 0, ',', '"' )){
			return null;
		}
		
		//	Convert encoding each cell.
		mb_convert_variables($encoding_to, $encoding_from, $array);
		
		//	Recovery locale.
		if(!setlocale(LC_ALL,$locale)){
			$this->_error = "Failed recovery locale. \($locale)\ ";
			return false;
		}
		
		//	Generate assoc.
		$assoc = array();
		if( $type === 'both' or $type === 'assoc' ){
			//	Checking column names.
			if(!$this->_column_names){
				$this->_error = 'Does not init column name. Please call \SetColumnName\ method.';
				return false;
			}
			
			//	Build assoc.
			for( $i=0,$c=count($array); $i<$c; $i++ ){
				$column_name = $this->_column_names[$i];
				$column_name = trim($column_name);
				$assoc[$column_name] = $array[$i];
			}
			
			//	Case of if assoc only.
			if( $type === 'assoc' ){
				$array = array();
			}
		}
		
		return  array_merge( $array, $assoc );
	}
	
	function SetColumnName( $column_names )
	{
		if(!is_array($column_names)){
			$type = gettype($column_names);
			$this->_error = "Arguments is not array. \($type)\ ";
			return false;
		}
		
		//	Save column name array.
		$this->_column_names = $column_names;
		
		return true;
	}
}
