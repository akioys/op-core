<?php
/**
 * TODO: I (will|should) support to spl_autoload_register
 * @see http://www.php.net/manual/ja/function.spl-autoload-register.php
 */
if(!function_exists('__autoload')){
	function __autoload($class_name)
	{
		switch($class_name){
			case 'Memcache':
				return;
			case 'App':
				$class_name = 'NewWorld5';
			default:
				$file_name = $class_name . '.class.php';
		}
		
		// include path
		$dirs = explode( PATH_SEPARATOR, ini_get('include_path') );
		$dirs[] = '.';
		$dirs[] = OnePiece5::GetEnv('App-Root');
		$dirs[] = OnePiece5::GetEnv('OP-Root');
		
		// check
		foreach( $dirs as $dir ){
			$file_path = rtrim($dir,DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file_name;
			//print $file_path . '<br/>' . PHP_EOL;
			if( file_exists($file_path) ){
				include_once($file_path);
				break;
			}
		}
		
		// check
		if (!class_exists($class_name, false)) {
			trigger_error("Unable to auto load class: $class_name", E_USER_NOTICE);
		}
	}
}

/**
 * @see http://jp.php.net/manual/ja/function.register-shutdown-function.php
 */
if(!function_exists('OnePieceShutdown')){
	function OnePieceShutdown()
	{
		static $init;
		if(!is_null($init)){
			return;
		}
		$init = true;
		
		/**
		 * @see http://jp.php.net/manual/ja/features.connection-handling.php
		 */
		$aborted = connection_aborted();
		$status  = connection_status();

		/* @see http://www.php.net/manual/ja/errorfunc.constants.php */
			switch($error['type']){
				case E_WARNING: // 2
					$er = 'E_WARNING';
					break;
					
				case E_NOTICE:  // 8
					$er = 'E_NOTICE';
					break;
					
				case E_STRICT:  // 2048
					$er = 'E_STRICT';
					break;
					
				default:
					$er = $error['type'];
			}
			
			if( $er !== 'E_STRICT' ){
				print __FUNCTION__ . ': ' . __LINE__ . '<br>' . PHP_EOL;
				printf('%s [%s] Error(%s): %s', $error['file'], $error['line'], $er, $error['message']);
			}
		}
		
		// Session reset
		if( $_SERVER['REMOTE_ADDR'] == '127.0.0.1' ){
			$rand = rand( 0, 1000);
			if( 1 == $rand ){
				$_SESSION = array();
				print OnePiece5::Wiki2('![.red[Session is clear]]');
			}
		}
		
		if( isset($_SERVER['OnePiece5']['env']['cli']) and $_SERVER['OnePiece5']['env']['cli'] ){
			print PHP_EOL . '/* OnePiece is shutdown. */' . PHP_EOL;
		}else{
			// Toolbox
			Toolbox::PrintStyleSheet();
			Toolbox::PrintGetFlagList();
			print '<OnePiece/>' . PHP_EOL;
		}
	}
	register_shutdown_function('OnePieceShutdown');
}

if(!function_exists('OnePieceErrorHandler')){
	function OnePieceErrorHandler( $no, $str, $file, $line, $context)
	{
//		print '<p>'.__FILE__.': '.__LINE__.'</p>';
		
		static $oproot;
		if(empty($oproot)){
			$oproot  = dirname(__FILE__) . '/';
		}
		$env = isset($_SERVER['OnePiece5']['env']) ? $_SERVER['OnePiece5']['env']: array();
		
		/* @see http://www.php.net/manual/ja/errorfunc.constants.php */
		switch($no){
			case E_WARNING: // 2
				$er = 'E_WARNING';
				break;
			case E_NOTICE:  // 8
				$er = 'E_NOTICE';
				break;
			case E_STRICT:  // 2048
				$er = 'E_STRICT';
				break;
			default:
				$er = 'ERROR: '.$no;
		}
		
		//  Output error message.
		$format = '%s [%s] %s: %s';
		if(empty($env['cgi'])){
			$format = '<p>'.$format.'</p>';
		}
		
		//  check ini setting
		if( ini_get( 'display_errors') ){
			printf( $format.PHP_EOL, $file, $line, $er, $str );
		}
		
		return true;
	}
	
	$level = $_SERVER['HTTP_HOST'] === 'local.onepiece.com' ? E_ALL | E_STRICT: error_reporting();
	set_error_handler('OnePieceErrorHandler',$level);
}

if(!function_exists('OnePieceExceptionHandler')){
	function OnePieceExceptionHandler($e)
	{
		print "<h1>Please implement the e-mail alert.</h1>";
		printf('<div><p>[%s] %s</p><p>%s : %s</p></div>', get_class($e), $e->GetMessage(), $e->GetFile(), $e->GetLine() );
		dump::d(Toolbox::toArray($e));
	}
	set_exception_handler('OnePieceExceptionHandler');
}

/**
 * 
 * @author Tomoaki Nagahara
 *
 */
class OnePiece5
{
	const OP_UNIQ_ID = 'op-uniq-id';
	
	public  $env     = array();
	public  $laptime = null;
	private $errors  = array();
	private $session = array();
	private $isInit  = null;
	
	/**
	 * 
	 */
	function __construct($args=array())
	{
		//  all
		$this->InitSession();

		//  extends class have init method.
		if( method_exists($this, 'Init') ){
			//  op-root has set the first.
			$this->SetEnv('op-root',dirname(__FILE__));
			$this->Init();
		}
		
		//  Check already init. 
		if( $this->GetEnv('init') ){
			return;
		}
		$this->SetEnv('init',true);
		
		// Error control
		$save_level = error_reporting();
		error_reporting( E_ALL );
		ini_set('display_errors',1);
		
		// Added op-root to include_path.
		$op_root = dirname(__FILE__);
		$include_path = ini_get('include_path');
		$include_path = trim( $include_path, PATH_SEPARATOR );
		$include_path .= PATH_SEPARATOR . $op_root;
		ini_set('include_path',$include_path);
		
		//  unique id
		if(!$this->GetCookie( self::OP_UNIQ_ID )){
			$this->SetCookie( self::OP_UNIQ_ID, md5(microtime() + $_SERVER['REMOTE_ADDR']));
		}
		
		// init
		$this->InitEnv($args);
		$this->InitLocale($this->GetEnv('locale'));
		
		//  recovery (display_errors)
		if( $this->admin() ){
			ini_set('display_errors',1);
		}else{
			ini_set('display_errors',0);
		}
		
		//  recovery (error_reporting)
		error_reporting( $save_level );
	}

	/**
	 * 
	 */
	function __destruct()
	{
		//  Called Init?
		if(!$this->isInit){
			$this->StackError(get_class($this).' has not call "parent::init();".');
		}
		
		$this->PrintTime();
		$this->PrintError();
	}
	
	/**
	 * 
	 * @param string $name
	 * @param array  $args
	 */
	function __call( $name, $args )
	{
		//  Toolbox
		//call_user_func_array(array($this->toolbox, $name), $args);
		
		//  If Toolbox method.
		if( method_exists('Toolbox', $name) and false ){
			return Toolbox::$name(
				isset($args[0]) ? $args[0]: null,
				isset($args[1]) ? $args[1]: null,
				isset($args[2]) ? $args[2]: null,
				isset($args[3]) ? $args[3]: null,
				isset($args[4]) ? $args[4]: null,
				isset($args[5]) ? $args[5]: null
			);
		}
		
		//  error reporting
		$join = array();
		foreach( $args as $temp ){
			switch(gettype($temp)){
				case 'string':
					$join[] = '"'.$temp.'"';
					break;
					
				case 'array':
					$join[] = var_export($temp,true);
					break;
					
				default:
					$join[] = gettype($temp);
					break;
			}
		}
		$argument = join(', ',$join);
		$message = sprintf('Catch magic method __call: function %s(%s)', $name, $argument );
		self::StackError($message);
	}
	
	public static function __callStatic( $name , $arguments )
	{
		//  PHP 5.3.0 later
		$this->StackError( __FUNCTION__ );
		$this->mark(PHP_VERSION);
	}
	
	function __set( $name, $value )
	{
		$this->StackError( __FUNCTION__ );
		$this->mark("![.red .bold[ $name is not accessible property. (value=$value)]]");
	}
	
	function __get( $name )
	{
		$this->StackError( __FUNCTION__ );
		$this->mark("![.red .bold[ $name is not accessible property.]]");
	}
	
	function __isset( $name )
	{
		$this->StackError( __FUNCTION__ );
		$this->mark("![.red .bold[ $name is not accessible property.]]");
	}
	
	function __unset( $name )
	{
		$this->StackError( __FUNCTION__ );
		$this->mark("![.red .bold[ $name is not accessible property.]]");
	}
	
	/*
	function __sleep()
	{
		serialize() called __sleep method.
	}
	
	function __wakeup()
	{
		
	}
	*/
	
	function __toString()
	{
		$this->mark('![.red .bold[ CATCH MAGIC METHOD ]]');
	}
	
	function __invoke()
	{
		$this->mark('![.red .bold[ CATCH MAGIC METHOD ]]');
	}
	
	function __set_state()
	{
		$this->mark('![.red .bold[ CATCH MAGIC METHOD ]]');
	}
	
	function Init()
	{
		$this->isInit = true;
		
		//  Create i18n configuration file path.
		$path = $this->ConvertPath('op:/i18n/'.get_class($this).'.i18n.php');
		
		//  Include configuration file.
		if( file_exists($path) ){
			$this->i18n()->SetByFile($path);
		}
	}
	
	/**
	 * You check whether you an administrator by IP-Address.
	 */
	static function Admin()
	{
		$server_addr = @$_SERVER['SERVER_ADDR'];
		$remote_addr = @$_SERVER['REMOTE_ADDR'];
		
		if( $server_addr == '127.0.0.1'){
			$io = true;
		}else

		if( $server_addr == $remote_addr ){
			$io = true;
		}else
		
		if(self::GetEnv('admin-ip') == $remote_addr){
			$io = true;
		}else
		
		{
			$io = false;
		}
		
		return $io;
	}
	
	/**
	 * Error stacking
	 * 
	 * @param string $message is message.
	 * @param string $class is label use to print.
	 */
	function StackError( $args, $class=__CLASS__ )
	{
		$encoding = mb_internal_encoding();
		
		$error['incident'] = $this->GetCallerLine( 1, 1, 'incident');
		$error['message']  = $this->Escape( $args, $encoding );
		$error['trace']	   = $this->GetCallerLine( 0, -1, 'trace');
		$_SERVER[__CLASS__]['errors'][] = $error;
	}
	
	/**
	 * Error print.
	 */
	function PrintError()
	{
		// init
		$nl = $this->GetEnv('nl');
		$class = __CLASS__;
		
		if(isset($_SERVER[__CLASS__]['errors'])){
			$errors = $_SERVER[__CLASS__]['errors'];
			unset($_SERVER[__CLASS__]['errors']);
		}else{
			return true;
		}
		
		//	Require OnePiece Style-Sheet. (print from Toolbox.class.php)
		$this->SetEnv('isRequireStyleSheet',true);
		
		// stack trace show/hide script  
		$javascript = <<< __EOL__
		<script>
		function op_error_trace( t, id ){
			if( t.checked ){
				document.getElementById(id).style.display = 'none';
			}else{
				document.getElementById(id).style.display = 'block';
			}
		}
		</script>
__EOL__;

		// stack trace style
		$style = 'display:none;';

		// create print
		$print  = '';
		$print .= "<div class=\"$class small \">";
		
		//  wiki2 options
		$o = array('tag'=>true);
		
		for( $i=0, $count = count($errors); $i<$count; $i++ ){
			
			$error = $errors[$i];
			$incident = $error['incident'];
			$message  = $error['message'];
			$trace    = $error['trace'];
			
			// stack trace show/hide control input
			$trace_id  = 'op-trace-div-'.$i;
			$input_id  = 'op-trace-input-'.$i;
			$input = sprintf('<input type="checkbox" id="%s" checked="checked" onChange="op_error_trace( this, \'%s\')" />', $input_id, $trace_id);

			$print .= sprintf('<div class="red">%s <label for="%s">%s - %s</label></div>'.$nl, $input, $input_id, self::wiki2($incident,$o), self::wiki2($message,$o));
			$print .= sprintf('<div class="trace" style="%s" id="%s">%s</div>'.$nl, $style, $trace_id, self::wiki2($trace,$o));
		}
		$print .= '</div>';
		
		// text/plane
		if( false ){
			$print = strip_tags( $print, null );
		}
		
		// admmin
		if( !self::Admin() or self::GetEnv('cli') ){
			$ua   = $this->GetEnv('UserAgent');
			$ip   = $this->GetEnv('RemoteIp');
			$href = $this->GEtEnv('href');
			$host = gethostbyaddr($ip);
			$date = date('Y-m-d H:i:s');
			$url  = $this->GetEnv('url');
			
			//  The same mail is not delivered repeatedly.
			$key = 'mail-notify-' . md5($errors[0]['trace']);
			if( $num = $this->GetSession($key) ){
				$this->SetSession($key, $num +1 );
				return;
			}else{
				$this->SetSession($key, 1 );
			}
			
			//  Subject
			$incident = strip_tags($this->wiki2($errors[0]['incident']));
			$incident = str_replace('&gt;',   '>', $incident);
			$incident = str_replace('&quot;', '' , $incident);
			$subject  = sprintf('[%s] PrintError: %s', __CLASS__, $incident );
			
			//  page
			$page_info  = "Page Info: $nl";
			$page_info .= "  Date: $date" . $nl;
			$page_info .= "  URL: $url" . $nl;
			$page_info .= "  REFERER: $href" . $nl . $nl;
			
			//  user
			$user_info  = "User Info: $nl";
			$user_info .= "  IP: $host($ip)" . $nl;
			$user_info .= "  UA: $ua" . $nl . $nl;
			
			//  message
			$message = $page_info . $user_info . "Errors: $nl ". strip_tags($print);
			//$message = str_replace( array('%20'), array(' '), $message);
			$message = str_replace( array('%20','%3C','%3E'), array(' ','<','>'), $message);
			
			$mail['to'] = $this->GetEnv('Admin-mail');
			$mail['subject'] = $subject;
			$mail['message'] = html_entity_decode( $message, ENT_QUOTES, 'utf-8');
			self::Mail($mail);
			
			$this->d($mail);
		}else{
			print $javascript . $nl;
			print $print;
		}
		
		return true;
	}
	
	function StackLog( $string, $tag=null )
	{
		$class    = self::GetCallerLine( 1, 1, '$class'); // get_called_class
		$incident = self::GetCallerLine( 1, 1, 'incident') . ' ';
		$method   = self::GetCallerLine( 1, 1, '$method');
		$str = "$incident $method / $string";
		$log['tag'] = "$class, $tag";
		$log['str'] = $str;
		$_SERVER[__CLASS__]['logs'][] = $log;
	}
	
	function PrintLog( $args=null )
	{
		if(!self::Admin()){
			print "<!-- not admin. -->";
			return;
		}
		
		//	Require OnePiece Style-Sheet. (print from Toolbox.class.php)
		$this->SetEnv('isRequireStyleSheet',true);
		
		// init
		$br = '<br/>';
		$nl = self::GetEnv('newline');
		$tags = explode(',',$args);
		
		if( $logs = @$_SERVER[__CLASS__]['logs'] ){
			print '<div class="OnePiece small" style="">' . $nl;
			foreach($logs as $log){
				$tag = $log['tag'];
				$str = self::wiki2($log['str']);
				foreach($tags as $temp){
					$temp = trim($temp);
					if( preg_match("/$temp/",$tag) ){
						print $str . $br . $nl;
						break;
					}
				}
			}
			print '</div>' . $nl;
		}
	}
	
	/**
	 * locale setting.
	 * 
	 * @see http://jp.php.net/manual/ja/class.locale.php
	 * @param string $locale lang_territory.codeset@modifier
	 * @return void
	 */
	private function InitLocale( $locale=null ){
		
		// @todo We will support to de_DE@euro
		
		/**
		 * Windows 
		 * 	Japanese_Japan.932 = sjis
		 * 	Japanese_Japan.20932 = euc-jp
		 * 
		 * PostgreSQL for Windows
		 * 	Japanese_Japan.932 = utf-8 // auto convert
		 * 	
		 * http://lets.postgresql.jp/documents/technical/text-processing/2/
		 * 
		 */
		
		if(!$locale){
			$locale = 'ja_JP.utf-8';
		}
		
		if( preg_match('|([a-z]+)[-_]?([a-z]+)?\.?([-_a-z0-9]+)?|i', $locale, $match) or true){
			$lang = @$match[1] ? $match[1]: 'ja';
			$area = @$match[2] ? $match[2]: 'JP';
			$code = @$match[3] ? $match[3]: 'utf-8';
		}
		
		// Windows is unsupport utf-8
		if( PHP_OS == 'WINNT' and $lang == 'ja' ){
			// Shift_JIS
			setlocale( LC_ALL, 'Japanese_Japan.932');
		}else if(!setlocale( LC_ALL, $locale )){
			/* @see http://jp.php.net/manual/ja/function.setlocale.php */
			$this->StackError("Illigal locale: $locale");
			return false;
		}
		
		if( $lang == 'ja'){
			$codes[] = 'eucjp-win';
			$codes[] = 'sjis-win';
			$codes[] = 'UTF-8';
			$codes[] = 'ASCII';
			$codes[] = 'JIS';
		}
		
		/**
		 * timezone list
		 * @see http://jp2.php.net/manual/ja/timezones.php 
		 */
		if( $area == 'JP'){
			$timezone = 'Asia/Tokyo';
		}
		
		mb_language($lang);
		mb_internal_encoding($code);
		mb_detect_order($codes);
		//mb_http_input();
		//mb_http_output()
		ini_set('date.timezone',$timezone);
		//date_default_timezone_set($timezone);
		
		$this->SetEnv('locale', $locale);
		$this->SetEnv('lang',   $lang);
		$this->SetEnv('area',   $area);
		$this->SetEnv('charset',$code);
	}
	
	/**
	 * Getter and Setter uses.
	 * 
	 * @param string $key
	 * @param string|array $var
	 */
	static private function Env( $key, $var=null, $ope )
	{
		// convert key name
		$key = strtolower($key);
		switch( $key ){
			case 'nl':
				$key = 'newline';
				break;
				
			case (preg_match('/(remote)[-_]?(ip|addr)/',$key,$match) ? true: false):
				$key = 'REMOTE_ADDR';
				break;
				
			case (preg_match('/(user)[-_]?(agent)/',$key,$match) ? true: false):
				$key = 'HTTP_USER_AGENT';
				break;
			
			case 'href':
				$key = 'HTTP_REFERER';
				break;
			
			case 'lang':
				$key = 'language';
				break;
				
			case 'fqdn':
			case 'domain':
				$key = 'HTTP_HOST';
				break;
			
			case 'origin':
				$this->mark($key);
				break;
				
			default:
				if( preg_match( '/^([a-z0-9]+)[-_]?(root|dir|mail)$/',$key, $match ) ){
					$key = $match[1].'_'.$match[2];
					if( $match[2] === 'mail' ){
						//  mail
					}else if( $ope === 'set' ){
						$var = rtrim($var,'/') . '/';
					}
				}
				break;
		}
		
		// get key's value
		switch($key){
			case isset($_SERVER[strtolower($key)]):
				$var = $_SERVER[strtolower($key)];
				break;
				
			case 'url':
				$schema = 'http://';
				$var = $schema . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
				$var = self::Escape($var);
				break;
			
			default:
				if( $ope == 'set' ){
					
					/**
					 * TODO: To notice? About overwrite the value.
					 * 
					if( isset( $_SERVER[__CLASS__]['env'][$key] ) ){
						$var = $_SERVER[__CLASS__]['env'][$key];
						//print ("Already set value. ($key=$var)");
					}
					*/
					
					$_SERVER[__CLASS__]['env'][$key] = $var;
					
				}else if( $ope == 'get' ){
					if( isset( $_SERVER[__CLASS__]['env'][$key])){
						$var = $_SERVER[__CLASS__]['env'][$key];
					}
				}else{
					self::StackError('Operand is empty.');
				}
				break;
		}
		
		if(empty($var)){
			switch($key){
				case 'encrypt-key':
					$var = OnePiece5::GetEnv('admin-mail');
					break;
			}
		}
		
		return $var;
	}
	
	/**
	 * 
	 */
	private function InitEnv($args=array('InitEnv'=>true))
	{
		if(!is_array($args)){
			$args = Toolbox::toArray($args);
		}
		
		/*
			Under line sample added .htaccess or httpd.conf
			SetEnv ADMIN_IP 192.168.1.1
		*/
		if( isset($_SERVER['ADMIN_ADDR']) ){
			$_SERVER['ADMIN_IP'] = $_SERVER['ADMIN_ADDR']; 
		}
		$admin_ip = isset($_SERVER['ADMIN_IP']) ? $_SERVER['ADMIN_IP']: '127.0.0.1';
		$local    = $_SERVER['SERVER_ADDR'] === '127.0.0.1' ? true: false;
		
		// server admin(mail address)
		if( preg_match('|[-_a-z0-9\.\+]+@[-_a-z0-9\.]+|i',@$_SERVER['SERVER_ADMIN']) ){
			$admin_mail = $_SERVER['SERVER_ADMIN'];
		}else{
			$admin_mail = 'noreply@onepiece-framework.com';
		}
		
		//  any root
		$op_root   = dirname(__FILE__);
		$doc_root  = $_SERVER['DOCUMENT_ROOT'];
		$app_root  = dirname($_SERVER['SCRIPT_FILENAME']);
		$site_root = realpath($_SERVER['DOCUMENT_ROOT'].'/../');
		
		// Windows
		if( PHP_OS == 'WINNT' ){
			$op_root   = str_replace( '\\', '/', $op_root   );
			$doc_root  = str_replace( '\\', '/', $doc_root  );
			$app_root  = str_replace( '\\', '/', $app_root  );
			$site_root = str_replace( '\\', '/', $site_root );
		}
		
		$this->SetEnv('class',      __CLASS__    );
		$this->SetEnv('local',      $local       );
	//	$this->SetEnv('op_root',    $op_root     );
		$this->SetEnv('doc_root',   $doc_root    );
		$this->SetEnv('app_root',   $app_root    );
		$this->SetEnv('site_root',  $site_root   );
		$this->SetEnv('admin-ip',   $admin_ip    );
		$this->SetEnv('admin-mail', $admin_mail  );
		$this->SetEnv('newline',    PHP_EOL      );
		
		foreach( $args as $key => $var ){
			$this->SetEnv($key,$var);
		}
	}
	
	/**
	 * Set env-value.
	 * 
	 * @param string $key
	 * @param string|array $var
	 */
	static function SetEnv( $key, $var )
	{
		self::Env( $key, $var, 'set' );
	}

	/**
	 * Get env-value.
	 * 
	 * @param string $key
	 */
	static function GetEnv( $key )
	{
		return self::Env( $key, null, 'get' );
	}

	function InitSession()
	{
		//  start to session.
		if(!session_id()){
			if( headers_sent($file,$line) ){
				$this->StackError("Header has already been sent. Check $file, line no. $line.");
			}else{
				session_start();
			}
		}
		
		/**
		 * @see http://www.glamenv-septzen.net/view/29
		 */
		session_cache_limiter('private_no_expire');

		//  separate session.
		$this->session = &$_SESSION[__CLASS__][get_class($this)];
	}
	
	/**
	 * Set session value.
	 * 
	 * @param string $key
	 * @param string|array $var
	 */
	function SetSession( $key, $var )
	{
		if( is_null($var)){
			unset($this->session[$key]);
		}else{
			$this->session[$key] = $var;
		}
		return $var;
	}

	/**
	 * Get session value.
	 * 
	 * @param string $key
	 */
	function GetSession( $key )
	{		
		if(isset($this->session[$key])){
			return $this->session[$key];
		}else{
			return null;
		}
	}
	
	function SetCookie( $key, $value, $expire=0, $path='/', $domain='', $secure=0, $httponly=true, $class=null )
	{
		$key   = $this->Escape($key);
		$value = $this->Escape($value);
		
		$this->StackLog("[SetCookie] $key=$value");
		
		if( headers_sent() ){
			$this->StackError("already header sent.");
			return false;
		}
		
		if( is_null($value) ){
			$expire = time() -10;
		}else if(!$expire){
			$expire = time() + 60*60*24*365;
		}else if( $expire < time() ){
			$expire += time();
		}
		
		$_key   = $key;
		//$_key   = md5($key);
		$_value = serialize($value);
		
		if (version_compare(PHP_VERSION, '5.2.0') >= 0) {
			//	httponly=true is Only http accsess. javascript cannot access.
			//	However, it is dependent on a browser.
			$io = setcookie( $_key, $_value, $expire, $path, $domain, $secure, $httponly );
		}else{
			$io = setcookie( $_key, $_value, $expire, $path, $domain, $secure );
		}
		
		if( is_null($value) ){
			unset($_COOKIE[$_key]);
		}else if( $io ){
			$_COOKIE[$_key] = $_value;
		}else{
			$this->mark('set cookie is error');
			$this->StackError('SetCookie is fail: key$key'.$value);
		}
		
		return $io;
	}
	
	function GetCookie($key)
	{
		//$key = md5($key);
		if( isset($_COOKIE[$key]) ){
			$value = $_COOKIE[$key];
			$value = unserialize($value);
			$value = $this->Escape($value);
		}else{
			$value = null;
		}
		return $value;
	}
	
	/**
	 * 
	 * @param integer $depth
	 * @param integer $num
	 * @param string  $format
	 */
	function GetCallerLine( $depth=0, $num=1, $format=null )
	{
		// TODO: file system encoding
		$encode_file_system = PHP_OS == 'WINNT' ? 'sjis': 'utf-8';
		
		// init
		$call_line = '';
		$depth++;
		$nl = $this->GetEnv('nl');
		
		// num
		if( $num >= count($back) or $num <= 0 ){
			$num = count($back) -1;
		}
		
		// loop
		for($i=1; $i <= $num; $depth++, $i++){
			
			$func  = $back[$depth]['function'];
			$args  = $back[$depth]['args'];
			$file  = isset($back[$depth]['file'])  ? $back[$depth]['file']:  '';
			$line  = isset($back[$depth]['line'])  ? $back[$depth]['line']: '';;
			$type  = isset($back[$depth]['type'])  ? $back[$depth]['type']:  '';
			$class = isset($back[$depth]['class']) ? $back[$depth]['class']: '';
			
			$line1m = isset($back[$depth-1]['line']) ? $back[$depth-1]['line']: '';
			
			$filefull = $file;
			$function = $func;

			// args controll
			if(count($args) == 0){
				$args = '';
			}else if($func == 'include'){
				// does not process.
				$path = realpath($args[0]);
				$path = self::CompressPath($path);
				$args = self::Escape( $path, $encode_file_system ); // file system's encode is not html encode
				$func = $func.'("$args")';
			}else if(count($args) == 1){
				if(!$args[0]){
					$args = '![.red[null]]';
				}else{
					$str = var_export($args[0],true);
					$str = str_replace(array("\n","\r","\t"), '', $str);
					$str = str_replace("\\'", "'", $str);
					$str = str_replace(",)", ") ", $str);
					$str = str_replace(",  )", ") ", $str);
					$str = $this->Escape($str);
					$args = $str;
				}
			}else{
				$vars = array();
				foreach($args as $var){
					switch(gettype($var)){
						case 'string':
							$vars[] = $this->Escape($var);
							break;
						case 'array':
							$str = var_export($var,true);
							$str = str_replace(array("\n","\r","\t"), '', $str);
							$str = str_replace("\\'", "'", $str);
							$str = str_replace(",)", ") ", $str);
							$str = str_replace(",  )", ") ", $str);
							$vars[] = $this->Escape($str);
							break;
						case 'object':
							$vars[] = get_class($var);
							break;
						default:
							$vars[] = gettype($var);
					}
				}
				$args = implode(', ',$vars);
				$args = str_replace(array("\n","\r"), '', $args);
			}
			
			// __call is empty case
			if(empty($file)){
				$file = __FILE__;
			}
			
			// Path is shortten
			$file = self::CompressPath($file);
			
			// method
			$method = "$class$type$func($args)";
			
			switch(strtolower($format)){
				case 'incident':
					$format = '![.bold[$class$type$func]] [$line1m]' ;
					break;
				
				case 'trace':
					$format = '![div .line [$file: $line]] ![div .method [ $class$type$function($args)]] '.$nl;
					break;
					
				case '':
				case 'null':
					$format = '$file [$line] ';
					break;
			}
			
			$patt = array('/\$filefull/','/\$file/','/\$line1m/','/\$line/','/\$class/','/\$type/','/\$function/','/\$func/','/\$args/','/\$method/');
			$repl = array($filefull, $file, $line1m, $line, $class, $type, $function, $func, $args, $method);
			$call_line .= preg_replace( $patt, $repl, $format );
		}
		
		return $call_line;
	}
	
	/**
	 * 
	 * @param  string $file_path
	 * @return string $file_path
	 */
	function CompressPath( $path )
	{
		// TODO: file system encoding. (Does not support multi language, yet)
		$encode_file_system = PHP_OS == 'WINNT' ? 'sjis': 'utf-8';
		if( PHP_OS == 'WINNT' ){
			$path = str_replace( '\\', '/', $path );
		}
		
		$op_root  = self::GetEnv('op_root');
		$app_root = self::GetEnv('app_root'); // ex. /main.php
		$doc_root = self::GetEnv('doc_root');
		
		//  remove slash (easy-to-read)
		$op_root  = rtrim( $op_root,  '/' );
		$app_root = rtrim( $app_root, '/' );
		$doc_root = rtrim( $doc_root, '/' );
		
		/*
		print "path=$path<br/>";
		print "op_root=$op_root<br/>";
		print "app_root=$app_root<br/>";
		print "doc_root=$doc_root<br/>";
		*/
		
		$patt = array("|^$app_root|","|^$doc_root|","|^$op_root|");
		$repl = array('![.bold[App ROOT : ]]','![.bold[Doc ROOT : ]]','![.bold[OP ROOT : ]]');
		$path = preg_replace( $patt, $repl, $path );
		
		//  easy-to-read. (op:OnePiece.class.php & app:/template/form.phtml)
		$path = preg_replace( '|(]])/([^/]+)$|', '\\1\\2', $path );
		
		return $path;
	}
	
	/**
	 * The File name, Function or Method name, and the number of line which are performed are outputed.
	 * 
	 * @param string  $str
	 * @param boolean $use_get_flag
	 */
	function Mark( $str='', $use_get_flag=false )
	{
		// displayed is only Admin-ip.
		if(!self::admin()){ return; }
		
		// displayed is Admin-ip and flag.
		if( $use_get_flag ){
			if(!Toolbox::UseGetFlag($use_get_flag)){ return; }
		}
		
		// php momory usage 
		$memory_usage = memory_get_usage(true) /1000;
		@list( $mem_int, $mem_dec ) = explode( '.', $memory_usage );
		$memory = sprintf('![ .gray [(%s.![.smaller[%s]] KB)]]', number_format($mem_int), $mem_dec );
		
		//  call line
		$call_line = self::GetCallerLIne();
		
		//  message
		if( is_null($str) ){
			$str = '![ .red [null]]';
		}else
		if( is_bool($str) ){
			$str = $str ? '![ .blue [true]]': '![ .red [false]]';
		}else
		if( $str and !is_string($str) ){
			$str = var_export($str,true);
		}
		
		$nl = self::GetEnv('nl');
		$attr['class'] = array('OnePiece','mark');
		$attr['style'] = array('font-size'=>'9pt','background-color'=>'white');
		$string = self::Html("$nl\t$call_line - $str $memory$nl",'div',$attr);
		if( $this->GetEnv('cli') ){
			$string = strip_tags($string);
		}
		
		print $string;
	}
	
	/**
	 * 
	 * @param string $str
	 * @param string $tag
	 * @param array  $attr
	 * @return string
	 */
	function Html($str, $tag='span', $attr=null)
	{
		$nl    = $this->GetEnv('newline');
		$str   = self::Escape($str);
		$tag   = self::Escape($tag);
		$attr  = self::Escape($attr);
		$str   = self::Wiki2($str, array('tag'=>true) );
		$style = '';
		$class = '';
		
		if(isset($attr['class'])){
			if(is_array($attr['class'])){
				$temp = implode(' ',$attr['class']);
			}else{
				$temp = $attr['class'];
			}
			$class = sprintf('class="%s"', $temp);
		}
		
		if(isset($attr['style'])){
			foreach($attr['style'] as $key => $var){
				$styles[] = "$key:$var;";
			}
			$style = sprintf('style="%s"', implode(' ', $styles));
		}
		
		return sprintf($nl.'<%s %s %s>%s</%s>'.$nl, $tag, $class, $style, $str, $tag );
	}
	
	/**
	 * 
	 * @param string $str
	 * @param string $tag
	 * @param array $attr
	 */
	function P( $str='OnePiece!', $tag='p', $attr=null)
	{
		print self::Html( $str, $tag, $attr );
	}
	
	/**
	 * 
	 * @param string|array $args
	 * @param string $use_get_flag
	 */
	function D( $args, $use_get_flag=false )
	{
		// displayed is only admin-ip.
		if(!self::admin()){ return; }
		
		// displayed is Admin-ip and flag.
		if($use_get_flag){
			if(!Toolbox::UseGetFlag($use_get_flag)){
				return;
			}
		}
		
		$line = self::GetCallerLine();
		if( class_exists('Dump',true) ){
			self::p($line, 'div', array('class' => array('OnePiece','small','bold','mark'), 
			                            'style' => array('color'=>'black',
			                            				 'font-size' => '9pt',
														 'background-color'=>'white'
														)));
			// Dump.class.php include by __autoloader
			@Dump::d($args);
		}else{
			$line = self::Wiki2($line,array('tag'=>true));
			print strip_tags($line);
			print serialize($args);
		}
	}
	
	/**
	 * 
	 * @param string|array $args
	 * @param string $charset
	 */
	static function Escape( $args, $charset='utf-8' )
	{
		switch($type = gettype($args)){
			case 'null':
			case 'NULL':
			case 'integer':
			case 'boolean':
			case 'double':
				break;
			case 'string':
				$args = self::EscapeString($args);
				break;
			case 'array':
				$args = self::EscapeArray($args);
				break;
			case 'object':
				$args = self::EscapeObject($args);
				break;
			default:
				self::p("[".__METHOD__."]undefined type($type)");
		}
		
		return $args;
	}
	
	/**
	 * 
	 * @param string $args
	 * @param string $charset
	 */
	static function EscapeString( &$args, $charset='utf-8' )
	{
		// anti null byte attack
		$args = str_replace("\0", '\0', $args);
		
		/**
		 * htmlentities's double_encoding off funciton is PHP Version 5.2.3 latter.
		 */
		if( version_compare(PHP_VERSION, '5.2.3') >= 0 ){
			$args = htmlentities( $args, ENT_QUOTES, $charset, false );
		}else{
			$args = html_entity_decode( $args, ENT_QUOTES, $charset );
			$args = htmlentities( $args, ENT_QUOTES, $charset );
		}

		return $args;
	}
	
	/**
	 * 
	 * @param array  $args
	 * @param string $charset
	 */
	static function EscapeArray( &$args, $charset='utf-8' )
	{
		$temp = array();
		foreach ( $args as $key => $var ){
			$key = self::Escape( $key, $charset );
			$var = self::Escape( $var, $charset );
			$temp[$key] = $var;
		}
		return $temp;
	}
	
	/**
	 * 
	 * @param array  $args
	 * @param string $charset
	 */
	static function EscapeObject( &$args, $charset='utf-8' )
	{
		$temp = new stdClass();
		foreach ( $args as $key => $var ){
			$key = self::EscapeString( $key, $charset );
			$var = self::Escape( $var, $charset );
			$temp->$key = $var;
		}
		return $temp;
	}
	
	function SetHeader( $str, $replace=null, $code=null )
	{
		$cgi = $this->GetEnv('cgi'); // FastCGI
		
		switch($code){
			case '500':
				if($cgi){
					$str = 'Status: 500 Internal Server Error';
				}else{
					$str = 'HTTP/1.1 500 Internal Server Error';
				}
				break;
			case (preg_match('/^[0-9]+$/',$code) ? true: false):
				
				break;
		}
	
		if(headers_sent()){
			$io = false;
			$this->StackError("already header sent.");
		}else{
			$io = true;
			$str = str_replace( array("\n","\r"), '', $str );
			header( $str, $replace, $code );
		}
		
		return $io;
	}
	
	/**
	 * 
	 * @param string $label
	 */
	function time($label=null){
		static $offset = null;
		
		if( true ){
			list($usec, $sec) = explode(" ", microtime());
			
			$sec = (int)$sec;
			
			if(!$offset){
				$offset  = ((float)$usec + (float)$sec);
				$elapsed = 0.0;
				$lap     = 0.0;
			}else{
				$count   = count($this->laptime)-1;
				$elapsed = ((float)$usec + (float)$sec) - $offset;
				$lap     = $elapsed - $this->laptime[$count]['elapsed'];
			}
		}else{
			/**
			 * I don't know. Mac OS X microtime is bugg???
			 */
			if(!$offset){
				$offset  = microtime(true); // Windows and Linux is OK.
				$elapsed = 0.0;
				$lap     = 0.0;
			}else{
				$count   = count($this->laptime) -1;
				$elapsed = microtime(true) - $offset;
				$lap     = $elapsed - $this->laptime[$count]['elapsed'];
			}
		}
		
		$this->laptime[] = array(
				'label'   => $label,
				'lap'     => $lap,
				'elapsed' => $elapsed,
				);
		
		return "$elapsed ($lap)";
	}
	
	/**
	 * 
	 */
	function PrintTime(){
		if($this->GetEnv('cli')){ return; }
		if(!$this->laptime){ return; }
		if( $this->laptime ){
			self::d($this->laptime);
		}
	}
	
	/**
	 * 
	 * @param array $args
	 */
	function Mail($args)
	{
		if( false and class_exists('_Mail',true) ){
			$this->mark('start Mail','memory');
			$mail = new Mail();
			$mail->Send($args);
			$this->mark('finish Mail','memory');
		}else{
			// optimize
			$lang = $this->GetEnv('lang');
			$char = $this->GetEnv('charset');
			if( $lang and $char ){
				// save
				$save_lang = mb_language();
				$save_char = mb_internal_encoding();
				
				// set
				mb_language($lang);
				mb_internal_encoding($char);
			}
			
			if( is_object($args) ){
				$args = Toolbox::toArray($args);
			}
			
			//  init
			$from	 = isset($args['from'])    ? $args['from']    : self::GetEnv('admin-mail');
			$to      = isset($args['to'])      ? $args['to']      : null;
			$title   = isset($args['title'])   ? $args['title']   : '';
			$subject = isset($args['subject']) ? $args['subject'] : $title;
			$body    = isset($args['body'])    ? $args['body']    : '';
			$message = isset($args['message']) ? $args['message'] : $body;
			$headers = array();
			
			//  subject
			$subject = mb_encode_mimeheader($subject);
			
			// From
			if( is_string($from) ){
				$headers[]  = "From: " . $from;
			}
			// Cc
			if( isset($args['cc']) ){
				if( is_string($args['cc']) ){
					$headers[] = "Cc: " . $args['cc'];
				}else if(is_array($args['cc'])){
					$this->mark('Does not implements yet.');
				}
			}
			// Bcc
			if( isset($args['bcc']) ){
				if( is_string($args['bcc']) ){
					$headers[]  = "Bcc: " . $args['bcc'];
				}else if( is_array($args['bcc']) ){
					$this->mark('Does not implements yet.');
				}
			}
			// X-Mailer
			if( $this->admin() ){
				$headers[] = "X-Mailer: OnePiece-Framework";
			}
			
			//	encording format
			if( $char ){
				$headers[] = "Content-Type: text/plain; charset=$char";
			}else{
				//	$headers[] = "Content-Transfer-Encoding: base64";
			}
			
			$add_header = implode("\n", $headers);
			$add_params = null;
			
			// SMTP server response: 503 5.0.0 Need RCPT (recipient) 
			$add_params = '-f '.$from;
		
			// @todo: I should support multi-byte-language
			$io = mail($to, $subject, $message, $add_header, $add_params );
			
			// recovery
			if( $save_lang and $save_char ){
				mb_language($save_lang);
				mb_internal_encoding($save_char);
			}
			
			return $io;
		}
	}
	
	/**
	 * 
	 * @param unknown_type $file
	 * @param unknown_type $data
	 */
	function Template( $file, $data=null )
	{
		//$this->mark(__METHOD__);
		//$this->mark($file);
		
		//  access is deny, above current directory
		if( $this->GetEnv('allowDoubleDot') ){
			//  OK
		}else if( preg_match('|^\.\./|',$file) ){ 
			$this->StackError("Does not allow parent directory.($file)");
			return false;
		}
		
		//	necessary to convert the path?
		if( file_exists($file) ){
			//  absolute
			$path = $file;
		}else if( file_exists($path = $this->ConvertPath($file)) ){
			//  abstract
		}else if( $dir = $this->GetEnv('template-dir') ){
			// the path is converted.
			$dir  = self::ConvertPath($dir);
			$path = rtrim($dir,'/').'/'.$file;
		}else{
			$path = $file;
		}
		
		//$this->mark($path);
		
		// 2nd check
		if(!file_exists($path)){
			$this->StackError("The template file does not exist.($file, $path)");
			return false;	
		}
		
		// extract array
		if( is_array($data) and count($data) ){
			if(isset($data[0])){
				$this->Mark('Missing? $data is array. (not assoc) / ex. $this->Template("index.phtml",array("test"=>"success")');
			}else{
				extract($data);
			}
		}else if(is_object($data)){
			// object
		}
		
		// read file
		$io = include($path);
		
		return $io;
	}
	
	/**
	 * Convert browser url. (base is document root.)
	 * 
	 * @param string $path
	 * @return string
	 */
	function ConvertURL( $args )
	{
		if( preg_match('|^([a-z][a-z0-9]+):/(.*)|i',$args,$match) ){
			switch($match[1]){
				case 'dot':
					$tmp_root = getcwd() . '/';
					break;
				default:
					$tmp_root = $this->GetEnv( $match[1] . '_root' );
			}
			
			//  Anti Windows
			if( PHP_OS == 'WINNT' ){
				$tmp_root = str_replace( '\\', '/', $tmp_root );
			}
			
			//  create absolute path. 
			$absolute = $tmp_root . $match[2];
		}else{
		//	$absolute = $args;
			return $args;
		}
		
		//  create relative path from document root.
		$doc_root = $this->GetEnv('doc-root');
		//$document_root = str_replace('/', '\\', $_SERVER['DOCUMENT_ROOT'] );
		
		//  replace
		$patt = array(); 
		$patt[] = '|^' . $doc_root . '|i';
		//$patt[] = '|^' . $document_root . '|i';
		$url = preg_replace($patt,'',$absolute);
		
		return '/' . ltrim($url,'/');
	}
	
	/**
	 * Convert server-side full-path.
	 * 
	 * @param  string $path
	 * @return string
	 */
	function ConvertPath( $path )
	{
		if( preg_match('/^(op|site):\//',$path,$match) ){
			$temp = $match[1].'-root';
			if( $root = $this->GetEnv($temp) ){
				$path = str_replace($match[0], $root, $path);
			}else{
				$this->StackError("$temp is not set.");
			}
		}else{
			$url = self::ConvertURL($path);
			$path = $_SERVER['DOCUMENT_ROOT'] .'/'. ltrim($url,'/');
		}
		
		return $path;
	}
	
	function Module($name)
	{
		return Toolbox::Module($name);
	}
	
	function Model($name)
	{
		if( $name == 'Account' ){
			$this->mark();
		}
		
		//  name check
		if(!$name){
			$this->StackError('Model name is empty.');
			return false;
		}
		
		//  already instanced?
		if( isset( $_SERVER['test']['model'][$name] ) ){
			return $_SERVER['test']['model'][$name];
		}
		
		//  include Model_model
		if(!class_exists( 'Model_model', false ) ){
			$path = self::ConvertPath('op:/Model/Model.model.php');
			if(!$io = include_once($path)){
				$msg = "Failed to include the Model_model. ($path)";
				$this->StackError($msg);
				throw new OpException($msg);
			}
		}
		
		//  master
		$path = self::ConvertPath("op:/Model/{$name}.model.php");
		if( $io = file_exists($path) ){
			$io = include_once($path);
		}
		
		//  user
		if(!$io ){
			$model_dir = $this->GetEnv('model-dir');
			$path  = self::ConvertPath("{$model_dir}{$name}.model.php");
			if( $io = file_exists($path) ){
				$io = include_once($path);
			}
		}
		
		//  Could be include?
		if(!$io){
			$msg = "Failed to include the $name_model. ($path)";
			$this->StackError($msg);
			throw new OpModelException($msg);
		}
		
		//  instance of model
		$model_name = $name.'_model';
		if(!$_SERVER['test']['model'][$name] = new $model_name ){
			$msg = "Failed to include the Model_model. ($path)";
			$this->StackError($msg);
			throw new OpModelException($msg);
		}
		
		return $_SERVER['test']['model'][$name];
	}
	
	/* @var $pdo PDO5 */
	private $pdo;
	
	/**
	 * Get PDO5 object
	 * 
	 * @param  $name class name
	 * @return PDO5
	 */
	function PDO( $name=null )
	{
		if(!isset($this->pdo)){
			if( is_null($name) ){
				$name = 'PDO5';
				$op_root = $this->GetEnv('op-root');
				if( $io = file_exists($op_root.'PDO/PDO5.class.php') ){
					include_once('PDO/PDO5.class.php');
				}else{
					$this->StackError("Does not exists file. (PDO/PDO5.class.php)");
					return false;
				}
			}
			if(!$this->pdo = new $name()){
				$this->StackError("Can not create object. ($name)");
				return false;
			}
		}
		
		return $this->pdo;
	}
	
	/* @var $mysql MySQL */
	private $mysql;
	
	/**
	 * Return MySQL Object
	 * 
	 * @param  array|string configuration or configuration file path
	 * @return MySQL $mysql
	 */
	function MySQL($args=null)
	{
		if( isset($this->mysql) ){
			// ok
		}else{
			include_once('SQL/MySQL.class.php');
			$this->mysql = new MySQL( $args, $this );
		}
		return $this->mysql;
	} 
	
	/**
	 * Abstract Form object.
	 * 
	 * @param unknown $args
	 * @return Form5
	 */
	function Form($args='Form5')
	{
		if(!isset($_SERVER[__CLASS__][strtoupper($args)])){
			if(!$_SERVER[__CLASS__][strtoupper($args)] = new $args()){
				return false;
			}
		}
		return $_SERVER[__CLASS__][strtoupper($args)];
	}
	
	/* @var $form Form5 */
	private $form = null;
	
	
	/**
	 *  @var $i18n i18n
	 */
	private $i18n = null;
	
	/**
	 * i18n is translate object.
	 * 
	 * @param  string $name Object name
	 * @return i18n
	 */
	function i18n($name='i18n')
	{
		if( empty($this->i18n) ){
			if(!$this->i18n = new $name()){
				return $this;
			}
		}
		
		return $this->i18n;
	}
	
	/**
	 * 
	 * @param  string $string
	 * @param  array  $options
	 * @return Ambigous <mixed, string|array, stdClass>|unknown
	 */
	static function Wiki2( $string, $options=null )
	{
		if( class_exists('Wiki2Engine',true) ){
			return Wiki2Engine::Wiki2( $string, $options );
		}else{
			return $string;
		}
	}
	
	/**
	 * @var $cache Cache
	 */
	private $cache = null;
	
	function Cache($name='Cache')
	{
		if(!$this->cache){
			if(!include("$name.class.php") ){
				throw new Exception("Include is failed. ($name)");
			}
			if(!$this->cache = new $name() ){
				throw new Exception("Instance object is failed. ($name)");
			}
		}
		return $this->cache;
	}
	
	/*
	function Cache($args=null)
	{
		if(!$this->cache){
			if(!class_exists('Memcache',false)){
				$this->mark('does not install memcache','cache');
				return null;
			}else if( $this->cache = new Memcache() ){
				$host   = @$args['host']   ? $args['host']:  'localhost';
				$port   = @$args['port']   ? $args['port']:  '11211';
				$weight = @$args['weight'] ? $args['weight']: null;
				$io = $this->cache->addServer( $host, $port, $weight );
				$this->mark('addServer='.$io, 'onepiece, cache');
			}else{
				$this->Mark('PHP-Memcache module is not installed?','onepiece, cache');
			}
		}else{
			return $this->cache;
		}
	}
	*/
	
	/*
	function GetCache($key)
	{
		if(!$this->cache){
			if(!$this->Cache()){
				return null;
			}
		}
		return $this->Cache()->Get($key);
	}
	
	function SetCache( $key, $var, $flag=0, $expire=0)
	{
		if(!$this->cache){
			if(!$this->Cache()){
				return null;
			}
		}
		$this->Cache()->Set( $key, $var, (int)$flag, (int)$expire );
	}
	*/
	
	/**
	 * 
	 * 
	 * @param boolen $args
	 */
	function Vivre( $register )
	{		
		if( $register ){
			// 　register
			if($this->GetEnv('vivre')){
				//	Double registration.
				$this->mark("Vivre check is double booking");
			}else if( isset($_SESSION[__CLASS__]['vivre']) ){
				//	unset されていないということは途中でエラーになっている。
				if( $this->admin() ){
					$this->mark("VIVRE!!");
				}else{
					$args = array();
					$args['to']		 = $this->GetEnv('admin-mail');
					$args['subject'] = '[OnePiece] VIVRE ALERT';
					$args['body']	 = 'REQUEST_URI='.$this->server('FULL_REQUEST_URI',1)."\n";
					$args['body']	.= $_SERVER['REMOTE_ADDR']."\n";
					$args['body']	.= gethostbyaddr($_SERVER['REMOTE_ADDR'])."\n";
					$args['body']	.= $_SERVER['HTTP_USER_AGENT']."\n";
					$this->Mail($args);
				}
			}else{
				$_SESSION[__CLASS__]['vivre'] = 1;
			}

			//	Anti double registration.
			$this->SetEnv('vivre',1);
		}else{
			//  release
			unset($_SESSION[__CLASS__]['vivre']);
		}
	}
}

class OpException extends Exception
{
	
}
