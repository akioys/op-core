<?php
/**
 * Stack the log. Only do it.
 * 
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 *
 */
class Model_Log extends Model_Model
{
	private $log;
	
	function Set($log)
	{
		$this->log[] = $log;
	}
	
	function Get()
	{
		return $this->log;
	} 
}
