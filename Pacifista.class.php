<?php

class Pacifista extends OnePiece5
{	
	function Init()
	{
		//	Env
		$this->SetEnv('pacifista',true);
		$this->SetEnv('cli',true);
		
		//	Change Current Directory.(into a calling file)
		$temp = debug_backtrace();
		$separator = DIRECTORY_SEPARATOR;
		$path = preg_replace( "|[^$separator]+$|", '', $temp[0]['file']);
		chdir($path);
		
		//	SERVER
		$_SERVER['REMOTE_ADDR']  = null;
		$_SERVER['SERVER_ADDR']  = null;
		$_SERVER['SERVER_PORT']  = null;
		$_SERVER['HTTP_HOST']    = null;
		$_SERVER['REQUEST_URI']  = null;
		$_SERVER['QUERY_STRING'] = null;
	}
	
	function GetArgs()
	{	
		for( $i=1; $i<count($_SERVER['argv']); $i++ ){
			$args[$i-1] = $_SERVER['argv'][$i];
		}
		return $args;
	}
}

