<?php

class Model_Nav extends Model_Model
{
	private $format;
	
	function Init()
	{
		self::SetFormat();
	}
	
	function Add()
	{
		
	}
	
	function Set()
	{
		
	}
	
	function Get()
	{
		
	}
	
	function Out($format=null)
	{
		if(!$format ){
			$format = self::GetFormat();
		}
		
		$nav = self::Get();
		
	}
	
	function SetFormat( Config $format=null )
	{
		if(!$foramt ){
			//  Init
			$format = new Config();
			$format->join = ' &lt; ';
		}
		
		//  
		$this->format = $format;
		return true;
	}
	
	function GetFormat()
	{
		
	}
}
