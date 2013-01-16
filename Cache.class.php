<?php

class Cache extends OnePiece5
{
	private $cache = null;
	
	function Init()
	{
		parent::Init();
		
		if( class_exists('Memcache') ){
			$this->cache = new Memcache();
			$this->InitMemcache();
		}else 
		if( class_exists('Memcached') ){
			$this->cache = new Memcached();
			$this->InitMemcached();
		}else{
			return false;
		}
		
		return true;
	}
	
	function InitMemcache( $host='localhost', $port='11211', $weight=10 )
	{
		$persistent = true;
		
		if(!$io = $this->cache->addServer( $host, $port, $persistent, $weight )){
			throw new Exception("Failed addServer method.");
		}
		
	}

	function InitMemcached( $host='localhost', $port='11211', $weight=10 )
	{
		if(!$io = $this->cache->addServer( $host, $port, $weight )){
			throw new Exception("Failed addServer method.");
		}
	
	}
	
	function Set( $key, $value )
	{
		$this->cache->Set( $key, $value );
	}
	
	function Get( $key )
	{
		return $this->cache->Get( $key );
	}
	
}
