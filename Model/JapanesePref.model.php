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
	
	function GetList($type='iso')
	{
		$pref = array(
				'01' => '北海道', '02' => '青森県', '03' => '岩手県', '04' => '宮城県', '05' => '秋田県',
				'06' => '山形県', '07' => '福島県', '08' => '茨城県', '09' => '栃木県', '10' => '群馬県',
				'11' => '埼玉県', '12' => '千葉県', '13' => '東京都', '14' => '神奈川県', '15' => '新潟県',
				'16' => '富山県', '17' => '石川県', '18' => '福井県', '19' => '山梨県', '20' => '長野県',
				'21' => '岐阜県', '22' => '静岡県', '23' => '愛知県', '24' => '三重県', '25' => '滋賀県',
				'26' => '京都府', '27' => '大阪府', '28' => '兵庫県', '29' => '奈良県', '30' => '和歌山県',
				'31' => '鳥取県', '32' => '島根県', '33' => '岡山県', '34' => '広島県', '35' => '山口県',
				'36' => '徳島県', '37' => '香川県', '38' => '愛媛県', '39' => '高知県', '40' => '福岡県',
				'41' => '佐賀県', '42' => '長崎県', '43' => '熊本県', '44' => '大分県', '45' => '宮崎県',
				'46' => '鹿児島県', '47' => '沖縄県',
		);
		
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
		return $pref[$index];
	}
	
	function UsedToForm( $args=null )
	{
		return self::UsedToForms( $args );
	}
	
	function UsedToForms( $type='object' )
	{
		//  base pref
		$pref = self::Get();
		
		//  init options
		$options = new Config();
		$options->{0}->value = '';
		
		foreach( $pref as $value => $label ){
			if($type == 'object'){
				$options->$value->value = $value;
				$options->$value->label = $label;
			}else if($type == 'array'){
				$options[$value]['value'] = $value;
				$options[$value]['label'] = $label;
			}
		}
		return $options;
	}
}