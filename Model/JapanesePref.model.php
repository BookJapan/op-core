<?php

class Model_JapanesePref extends Model_Model
{
	function Get( $value=null )
	{
		if( is_numeric($value) ){
			$value = (int)$value;
		}
		
		switch($type = gettype($value)){
			case 'null':
			case 'NULL':
				$return = $this->GetList();
				break;
				
			case 'integer':
				$return = $this->GetName($value);
				break;
				
			case 'string':
				$return = $this->GetIndex($value);
				break;
				
			default:
				$this->mark("undefined type. ($type)");
				$return = null;
		}
		
		return $return;
	}
	
	function GetList( $type='iso', $prefecture=true )
	{
		$pref = array(
				'01' => '北海道','02' => '青森', '03' => '岩手', '04' => '宮城', '05' => '秋田',
				'06' => '山形', '07' => '福島', '08' => '茨城', '09' => '栃木', '10' => '群馬',
				'11' => '埼玉', '12' => '千葉', '13' => '東京', '14' => '神奈川','15' => '新潟',
				'16' => '富山', '17' => '石川', '18' => '福井', '19' => '山梨', '20' => '長野',
				'21' => '岐阜', '22' => '静岡', '23' => '愛知', '24' => '三重', '25' => '滋賀',
				'26' => '京都', '27' => '大阪', '28' => '兵庫', '29' => '奈良', '30' => '和歌山',
				'31' => '鳥取', '32' => '島根', '33' => '岡山', '34' => '広島', '35' => '山口',
				'36' => '徳島', '37' => '香川', '38' => '愛媛', '39' => '高知', '40' => '福岡',
				'41' => '佐賀', '42' => '長崎', '43' => '熊本', '44' => '大分', '45' => '宮崎',
				'46' => '鹿児島','47' => '沖縄',
		);
		
		if( $prefecture ){
			foreach( $pref as &$word ){
				switch($word){
					case '大阪':
					case '京都':
						$word.='府';
						break;
					case '東京':
						$word.='都';
						break;
					default:
						$word.='県';
				}
			}
		}
		
		return $pref;
	}
	
	function GetNo( $str )
	{
		return $this->GetIndex( $str );
	}
	
	function GetIndex( $str )
	{
		$index = array_search($str, $this->get());
		return $index;
	}
	
	function GetName( $index )
	{
		$pref = $this->Get();
		$index = sprintf('%02d',$index);
		return isset($pref[$index]) ? $pref[$index]: null;
	}
	
	function UsedToForms( $type=null, $is_value_of_int=false )
	{
		return self::UsedToForm( $type, $is_value_of_int );
	}
	
	function UsedToForm( $type='object', $is_value_of_int=false )
	{
		if(!$type){ $type = 'object'; }
		
		//  base pref
		$pref = $this->Get();
		
		//  init options
		$options = new Config();
		$options->{'e'}->value = '';
		
		foreach( $pref as $value => $label ){
			if($type == 'object'){
				$options->$value->value = $is_value_of_int ? (int)$value: $value;
				$options->$value->label = $label;
			}else if($type == 'array'){
				$options[$value]['value'] = $is_value_of_int ? (int)$value: $value;
				$options[$value]['label'] = $label;
			}
		}
		return $options;
	}
}
