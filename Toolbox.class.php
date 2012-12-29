<?php
/**
 * For PHP4
 * 
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 *
 */
if (!function_exists('lcfirst')) {
	function lcfirst($text) {
		$text{0} = strtolower($text{0});
		return $text;
	}
}

/**
 * The Toolbox for present OnePiece-Framework.
 *
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 *
 */
class Toolbox
{
	function __call( $func, $args )
	{
		$this->mark("$func is not exists.");
	}
	
	function Command( $command, $args=array() )
	{
		$command  = escapeshellcmd($command);
		$command .= ' 2>&1';
		foreach($args as $var){
			$var = escapeshellarg($var);
			$command .= " $var";
		}
		exec( $command, $output, $return );
		$output = implode("\n",$output);
		return array('output'=>$output,'return'=>$return);
	}
	
	static function toString( $args, $separater=', ' )
	{
		$type = gettype($args);
		switch($type){
			case 'array':
			case 'object':
				break;
			
			case 'boolean':
				return $args ? 'true': 'false';
			
			default:
				return (string)$args;
		}
		
		foreach($args as $key => $var){
			if(!is_string($var)){
				$var = self::toString($var);
			}
			$join[] = $var;
		}
		
		return implode( $separater, $join );
	}
	
	static function toArray($args)
	{
		$type = gettype($args);
		switch($type){
			case 'object':
				return self::toArrayFromObject($args);
		}
		
		return array($args);
	}
	
	static function toArrayFromObject( $obj )
	{
		$arr = array();
		foreach( (array)$obj as $key => $var ){
			if( is_object($var) ){
				$var = self::toArrayFromObject($var);
			}
			$arr[$key] = $var;
		}
	
		return $arr;
	}
	
	static function toObject($args)
	{
		switch($type = gettype($args)){
			case 'object':
				return $args;
				
			case 'array':
				return Toolbox::toObjectFromArray($args);

			case 'null':
			case 'NULL':
				return null;
			
			case 'string':
				print $args;
				return;
				
			default:
				print '<p>'.__METHOD__ . ": Does not support type. ($type)</p>";
		}
	}
	
	static function toObjectFromArray($arr)
	{
		$obj = new stdClass();
		foreach($arr as $key => $var){
			switch($type = gettype($var)){
				case 'array':
					$obj->$key = Toolbox::toObjectFromArray($var);
					break;
				default:
					$obj->$key = $var;
			}
		}
		
		return $obj;
	}
	
	/*
	function GetVarToString( $args )
	{
		$charset = OnePiece5::GetEnv('charset');
		$type = substr( gettype($args), 0, 3 );
		switch( $type ){
			case 'str':
				$len = mb_strlen( $args, $charset );
				if( $len > 20 ){
					$var = mb_substr( $args, 0, 20 ) . '...';
				}
				$var = str_replace(array("\n","\r","\t"), array('\n ','\r ','\t '), $var);
				$str = sprintf("%s(%s[%s])" . PHP_EOL, $type, $var, $len);
				break;
				
			case 'obj':
				$var = get_class($args);
				$str = "$type($var)" . PHP_EOL;
				break;
				
			case 'arr':
				foreach($args as $key => $var){
					$join[] = "$key=>" . self::GetVarToString($var);
				}
				$var = join(',',$join);
				$str = str_replace(PHP_EOL, '', 'arr('.$var.')');
				break;
				
			case 'boo':
				$var = $args ? 'true': 'false';
				$str = "$type($var)" . PHP_EOL;
				break;
				
			case 'NULL':
				$str = 'null' . PHP_EOL;
				break;
				
			default:
				$var = $args;
				$str = "$type($var)" . PHP_EOL;
		}
		
		return $str;
	}
	*/
	
	/**
	 * Get secure request
	 * 
	 * @param string $key      
	 * @param string $method default is $_SERVER['REQUEST_METHOD']. (GET/POST/REQUEST(include cookie)/BOTH(GET&POST))
	 * @return boolean|string|array
	 */
	static function GetRequest( $key=null, $method=null )
	{
		if(!$method){
			$method = $_SERVER['REQUEST_METHOD'];
		}
		
		switch(strtolower($method)){
			case 'get':
				$request = $_GET;
				break;
				
			case 'post':
				$request = $_POST;
				break;
				
			case 'request': // include cookie
				$request = $_REQUEST;
				break;
				
			case 'both':
				$request = $_GET + $_POST;
				break;
				
			default:
				$this->StackError('An unexpected error: empty method type.');
				return false;
		}
		
		if( is_null($key) ){
			//  null
			$args = $request;
		}else{
			//  string or array
			if( is_string($key) ){
				$keys = explode(',',$key);
			}else if( is_array($key) ){
				$keys = $key;
			}
			//  get intersect
			$args = array_intersect_key( $request, array_flip($keys) );
			//  if want only one
			if( count($keys) === 1 ){
				$args = array_shift($args);
			}
		}
		
		// Escape
		if( !is_null($args) ){
			$args = OnePiece5::Escape($args);
		}
		
		return $args;
	}
	
	static function SetRequest( $value, $key, $method=null )
	{
		if(!$method){
			$method = $_SERVER['REQUEST_METHOD'];
		}
		
		switch(strtolower($method)){
			case 'get':
				$request = &$_GET;
				break;
				
			case 'post':
				$request = &$_POST;
				break;
				
			case 'request': // include cookie
				$request = &$_REQUEST;
				break;
				
			case 'both':
				$this->mark('not yet implements');
				break;
				
			default:
				$this->StackError('An unexpected error: empty method type.');
				return false;
		}
		
		$request[$key] = OnePiece5::Escape($value);
	}
	
	function Module( $name, $args=null )
	{
		$path  = self::ConvertPath($this->GetEnv('module-dir'));
		$path .= '/' . $name . '/' . $name.'.module.php';
		
		if( file_exists($path) ){
			include_once($path);
		}else{
			$this->StackError("does not file exists. ($name.module.php)");
			return null;
		}
		
		$module_name = 'Module_' . $name;
		
		return new $module_name($args);
	}
	
	static function Copy($args)
	{
		$return = new stdClass();
		foreach( $args as $key => $var ){
			// value
			switch( gettype($var) ){
				case 'array':
					$copy = self::Copy($var);
					break;
				case 'object':
					$copy = self::Copy($var);
					break;
				default:
					$copy = $var;
			}
			// key
			switch( gettype($args) ){
				case 'array':
					$return[$key] = $copy;
					break;
				case 'object':
					$return->{$key} = $copy;
					break;
			}
		}
		
		return $return;
	}
	
	/**
	 *
	 * ex. 接続しているリモートがサーバーのアドレスと同一ネットワーク内かチェックする
	 * self::CIDR( $_SERVER['SERVER_ADDR'], $_SERVER['REMOTE_ADDR'], 27 )
	 *
	 * @param  string  $ip1
	 * @param  string  $ip2
	 * @param  integer $prefix
	 * @return boolean
	 */
	function CIDR( $ip1, $ip2, $prefix ){
		//	maskする分をビットシフトして戻すと、末尾が等しくなる
		$mask = 32 - $prefix;
		$ip1 = ip2long($ip1) >> $mask << $mask;
		$ip2 = ip2long($ip2) >> $mask << $mask;
		return $ip1 === $ip2 ? true: false;
	}
	
    /**
     *  Encrypt string.
     *  
     *  @see http://jp2.php.net/manual/ja/book.mcrypt.php
     *  @param  string  $str String of want to encrypt.
     *  @return string  Encrypted string.
     */
	function Encrypt( $str, $key=null )
	{
		$cipher = MCRYPT_RIJNDAEL_128;
		$mode   = MCRYPT_MODE_CBC;
		if(!$key){
			$key = OnePiece5::GetEnv('Encrypt-Key');
		}
		
		srand();
		$ivs = mcrypt_get_iv_size($cipher,$mode);
		$iv  = mcrypt_create_iv( $ivs, MCRYPT_RAND );
		$bin = mcrypt_encrypt( $cipher, $key, $str, $mode, $iv );
		$hex = bin2hex($bin);
		
	    return bin2hex($bin).'.'.bin2hex($iv);
	}

    /**
     *  Decrypt string.
     *  
     *  @param  string  $str String of want to decrypt.
     *  @return string  Decrypted string.
     */
	function Decrypt( $str, $key=null )
	{
		$cipher = MCRYPT_RIJNDAEL_128;
		$mode   = MCRYPT_MODE_CBC;
		if(!$key){
			$key = OnePiece5::GetEnv('Encrypt-Key');
		}
		
		//	ドット区切りでivも渡す必要がある
		list( $hex, $ivt ) = explode( '.', $str );
		
		//	どれかが空なら空文字を返す
		if( !$hex or !$ivt or !$key ){
			$this->mark("hex=$hex, ivt=$ivt, key=$key");
			return '';
		}
		
		$bin = pack('H*', $hex);
		$iv  = pack('H*', $ivt);
	    $dec = mcrypt_decrypt( $cipher, $key, $bin, $mode, $iv );
	    // Java等、\0以外でパディングするプログラムと連携する場合はパディング方法が同じになるよう注意すること。
		$dec = rtrim($dec, "\0");
	    
	    return $dec;
	}
	
	function ConvertConfigFromPath( $args )
	{
		
		return $config;
	}
	
	function ConvertConfigToObject( $args )
	{
		$obj = new stdClass();
		
		
		
		return $obj;
	}

	function ConvertConfigToArray( $args )
	{
		$type = gettype($args);
		
		switch($type){
			case 'string':
				$path = Toolbox::ConvertPath($args);
				if( file_exists($path) ){
					include($path);
				}else{
					$this->StackError("File does not exist. ($path)");
					return false;
				}
				if(isset($_config)){
					$config = $_config;
				}else if(isset($_conf)){
					$config = $_conf;
				}else if(isset($_forms)){
					$config = $_forms;
				}else if(isset($_form)){
					$config[] = $_form;
				}
				break;
			case 'array':
				$config = $args;
				break;
			case 'object':
				$config = self::ConvertArrayFromObject($args);
				break;
		}
		
		return $config;
	}
	
	function ConvertSnakeCase($str)
	{
		$str = trim($str);
		if( preg_match('/[^-_a-z0-9]/i',$str) ){
			$this->StackError('Illigal character code.');
			return;
		}
		$str = preg_replace( '/([A-Z])/', ' \\1', $str );
		$str = preg_replace( '/\s+/', ' ', $str );
		$str = str_replace('-', ' ', $str);
		$str = str_replace(' ', '_', $str);
		$str = strtolower($str);
		return $str;
	}
	
	function ConvertPascalCase($str)
	{
		$str = self::ConvertSnakeCase($str);
		$str = str_replace('_',' ',$str);
		$str = ucwords($str);
		$str = str_replace(' ', '', $str);
		return $str;
	}
	
	function ConvertCamelCase($str)
	{
		$str = self::ConvertPascalCase($str);
		$str = lcfirst($str);
		return $str;
	}
	
	/**
	 * Mark method uses.
	 *
	 * @param string $key
	 */
	static function UseGetFlag( $keys )
	{
		// recovery from session
		$UseGetFlag = &$_SESSION[__CLASS__]['GetFlag'];
		
		// loop
		foreach( explode(',',$keys) as $key ){
			$key = trim($key,' ');
	
			// init
			if( isset($UseGetFlag[$key]) and is_null($UseGetFlag[$key]) ){
				$UseGetFlag[$key] = false;
			}
	
			// save current url query
			if( isset($_GET['GetFlag'][$key]) ){
				$UseGetFlag[$key] = $_GET['GetFlag'][$key];
			}
	
			// find
			if( isset($UseGetFlag[$key]) ){
				return $UseGetFlag[$key];
			}
		}
	
		return false;
	}
	
	static function GetFileListFromDir($path='./')
	{
		$list = array();
		
		if( $dir = opendir($path) ){
			while($file = readdir($dir)){
				if( $file === '.' or $file === '..' ){
					continue;
				}
				if( preg_match('|^\.|',$file)){
					continue;
				}
				$list[] = $file;
			}
		}
		
		return $list;
	}
	
	static function PrintGetFlagList()
	{
		static $isPrint = null;
		if( $isPrint ){
			return;
		}else{
			$isPrint = true;
		}
		
		if( OnePiece5::GetEnv('cli') ){
			return;
		}
		
		// Only admin
		if(!OnePiece5::admin()){
			return;
		}
		
		// set this function flag
		self::UseGetFlag(__FUNCTION__);
		
		// get GetFlag
		$UseGetFlag = $_SESSION[__CLASS__]['GetFlag'];
		
		// Is showing this links?
		if( $UseGetFlag[__FUNCTION__] === '0' ){
			return;
		}else{
			$key = __FUNCTION__;
			$str = 'hide';
			$var = 0;
			$join[] = sprintf('<a href="?GetFlag[%s]=%s">%s %s</a>', $key, $var, $str, 'there links');
		}
		
		// remove
		unset($UseGetFlag[__FUNCTION__]);
		
		// general
		if(!is_null($UseGetFlag)){
			foreach( $UseGetFlag as $key => $var ){
				$str = $var ? 'hide': 'show';
				$var = $var ? 0: 1;
				$join[] = sprintf('<a href="?GetFlag[%s]=%s">%s %s info</a>', $key, $var, $str, $key);
			}
		}
		
		print '<!-- '.__FILE__.' - '.__LINE__.' -->';
		print '<div class="small">[ '.join(' | ', $join).' ]</div>';
	}
	
	static function PrintStyleSheet()
	{
		static $isPrint = null;
		if( $isPrint ){
			return;
		}else{
			$isPrint = true;
		}
		
		if( OnePiece5::GetEnv('cli') ){
			return;
		}
		
		print <<< __EOF__
<style>

.OnePiece {
  direction: ltr;
}

.mark span{
	font-size: 9pt;
}

.trace{
  _color: gray;
  _font-size: smaller;
}

.trace .line{
  margin-left: 1em;
}

.trace .method{
  margin-left: 2em;
  margin-bottom: 0.5em;
}

.i1em{
  margin-left: 1em;
}

.i2em{
  margin-left: 2em;
}

.smaller{
  font-size: smaller;
}

.small{
  font-size: small;
}

.bold{
  font-weight: bold;
}

.italic{
  font-style: italic;
}

.red{
  color: red;
}

.blue{
  color: blue;
}

.gray{
  color: gray;
}

.purple{
  color: #cf00fc;
}

</style>
__EOF__;
	}
}