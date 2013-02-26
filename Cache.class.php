<?php

class Cache extends OnePiece5
{
	private $cache     = null;
	//private $memcache  = null;
	//private $memcached = null;
	
	function Init()
	{
		parent::Init();
		
		//  Get value
		$memcache  = $this->GetEnv('memcache');
		$memcached = $this->GetEnv('memcached');
		
		//  If undefined
		if( is_null($memcache)){
			$memcache = class_exists('Memcache');
		}

		//  If undefined
		if( is_null($memcached)){
			$memcached = class_exists('Memcached');
		}

		//  Instance
		if( $memcached ){
			$this->cache = new Memcached();
			$this->InitMemcached();
		}
		
		//  Instance
		if( $memcache ){
			$this->cache = new Memcache();
			$this->InitMemcache();
		}
		
		return true;
	}
	
	function InitMemcache( $host='localhost', $port='11211', $weight=10 )
	{
		$persistent = true;
		
		//  Change modan method.
		if(!$hash_strategy = $this->GetEnv('memcache.hash_strategy') ){
			$hash_strategy = 'consistent';
		}
		ini_set('memcache.hash_strategy', $hash_strategy);
		
		//  Added server
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
	
	function Set( $key, $value, $expire=0 )
	{
		switch( $name = get_class($this->cache) ){
			case 'Memcache':
			//	$compress = MEMCACHE_COMPRESSED;
				$compress = null;
				break;
		}
		
		
		$this->cache->Set( $key, $value, $compress, $expire );
	}
	
	function Get( $key )
	{
		if( $this->cache ){
			return $this->cache->Get( $key /* ,MEMCACHE_COMPRESSED */ );
		}else{
			return null;
		}
	}
	
}
