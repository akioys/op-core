<?php

class Model_Nav extends Model_Model
{
	private $_format;
	private $_stack;
	
	function Init()
	{
		parent::Init();
		self::SetFormat();
	}
	
	function Add( $href, $label=null )
	{
		$stack['href']  = $href;
		$stack['label'] = $label;
		
		$this->_stack[] = $stack;
	}
	
	function Out($format=null)
	{
		if(!$format ){
			$format = self::GetFormat();
		}
		
		foreach( $this->_stack as $stack ){
			$href  = $stack['href'];
			$label = $stack['label'];
			$join[] = sprintf('<a href="%s">%s</a>',$href,$label);
		}
		
	}
	
	function SetFormat( Config $format=null )
	{
		if(!$format ){
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
