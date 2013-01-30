<?php

class Model_Helper extends Model_Model
{
	function GetFormOptionsDateYear($config=null)
	{
		$num   = isset($config->num)   ? $config->num:   20;
		$start = isset($config->start) ? $config->start: date('Y');
		$end   = isset($config->end)   ? $config->end:   $start + $num;
		
		$options = new Config();
		$options->empty->value = '';
		
		for( $i=0; $i<$num; $i++ ){
			$y = $start + $i;
			$options->$i->value = $y ? $y : '';
		}
		
		return $options;
	}
	
	function GetFormOptionsDateMonth()
	{
		$options = new Config();
		
		for( $i=0; $i<=12; $i++ ){
			$options->$i->value = $i ? $i : '';
		}
		
		return $options;
	}
}
