<?php
/**
 * Blowfish encrypt/decrypt
 * 
 * @version   1.0
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright 2006 (C) Tomoaki Nagahara All right reserved.
 */
class Blowfish
{	
	private $cipher = null;
	private $mode   = null;
	private $key    = null;
	
	function Init()
	{
		if( empty($this->cipher) ){
			$this->cipher = MCRYPT_BLOWFISH;
		}
		
		if( empty($this->mode) ){
			$this->mode = MCRYPT_MODE_CBC;
		} 
		
		if( empty($this->key) ){
			if( class_exists('OnePiece5') ){
				$key = OnePiece5::GetEnv('admin_mail');
			}else{
				$key = md5(__FILE__);
			}
			$this->key  = $key;
		}
		
		return array( $this->cipher, $this->mode, $this->key );
	}
	
	function SetKey($key)
	{
		$this->key = $key;
	}
	
	function Encrypt( $str, $key=null )
	{
		list( $cipher, $mode, $key ) = $this->init();
		
		srand();
		$ivs = mcrypt_get_iv_size($cipher,$mode);
		$iv  = mcrypt_create_iv( $ivs, MCRYPT_RAND ); // Windows is only MCRYPT_RAND.
		$bin = mcrypt_encrypt( $cipher, $key, $str, $mode, $iv );
		$hex = bin2hex($bin);
		
	    return bin2hex($bin).'.'.bin2hex($iv);
	}
	
	function Decrypt( $str, $key=null )
	{
		list( $cipher, $mode, $key ) = $this->init();
		
		//	required "IV"
		list( $hex, $ivt ) = explode( '.', $str );
		
		//	
		if( !$hex or !$ivt or !$key ){
			return '';
		}
		
		//  
		$bin = pack('H*', $hex);
		$iv  = pack('H*', $ivt);
	    $dec = mcrypt_decrypt( $cipher, $key, $bin, $mode, $iv );
		$dec = rtrim($dec, "\0");
	    
	    return $dec;
	}
}

