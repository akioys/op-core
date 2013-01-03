<?php

include_once('OnePiece5.class.php');

/**
 * The NewWorld is the new world.
 * 
 * NewWorld's job is dispatched to index.php, only.
 * before index.php is your like. 
 * 
 * - 2012-06-xx NewWorld5
 * - 2011-01-xx NewWorld
 * - 2010-01-xx NewWorld0
 * 
 * 1st Attend system is NewWorld0 - 2010-12
 * 2nd Attend system is replace at NewWorld - 2011-09
 * 
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 *
 */
abstract class NewWorld5 extends OnePiece5
{
	/**
	 * routing table
	 * 
	 * @var array
	 */
	private $isInit     = null;
	private $isDispatch = null;
	private $routeTable = null;
	private $content    = null;
	
	function __construct($args=array())
	{
		//  output is buffering.
		$io = ob_start();
		$io = parent::__construct($args);
		$this->StackLog('START');
		
		//  init
		$this->Init();
		
		//  
		return $io;
	}
	
	function __destruct()
	{
		//  Called dispatch?
		if(!$this->isDispatch){
			$this->StackError('App has not dispatched. Please call $app->Dispatch();');
		}
		
		//  Called Init?
		if(!$this->isInit){
			$this->StackError('App has not call init. Please call $app->Init(){ parent::Init(); };');
		}
		
		ob_end_flush();
		$io = parent::__destruct();
		return $io;
	}
	
	function Init()
	{
		$this->isInit = true;
		
		$this->GetEnv('doctype','html');
		$this->GetEnv('title','The NewWorld is the new world');
	}
	
	/**
	 * Setup route table
	 * 
	 * @param string $request_uri
	 * @param array  $route
	 */
	function SetRoute($request_uri, $route)
	{
		@list( $path, $query_string ) = explode('?',$request_uri);
		$route = $this->Escape($route);
		$this->routeTable[md5($path)] = $route;
	}
	
	/**
	 * 
	 * 
	 * @param string $request_uri
	 * @return multitype:
	 */
	function GetRoute($request_uri=null)
	{
		// controller file name
		if(!$controller = $this->GetEnv('controller-name')){
			$this->StackError('Does not set controller-name. Please call $app->SetEnv("controller-name","index.php");');
			return false;
		}
		
		// get request uri
		if(!$request_uri){
			$request_uri = $_SERVER['REQUEST_URI'];
		}
		
		// separate query
		list( $path, $query_string ) = explode('?',$request_uri.'?');
		$full_path = $_SERVER['DOCUMENT_ROOT'] . $path;
		//$this->mark($full_path);
		
		// Does path exist?
		if( $route = @$this->routeTable[md5($path)] ){
			return $route;
		}
		
		//  Real file is target of path through.
		if( preg_match('/\/([-_a-z0-9]+)\.(html|css|js)$/i',$path,$match) ){
			
			//  file extension
			$extension = $match[2];
			
			//  access file name
			$file_name = $match[1].'.'.$match[2];
			
			//  current path is App path.
			$app_root = getcwd();
			
			//  document root path
			$doc_path = $_SERVER['DOCUMENT_ROOT'];
			
			//  create app path
			if( preg_match("|^$app_root(.+)|", $full_path, $match) ){
				$app_path = $match[1];
			}else if( preg_match("|^$doc_path(.+)|", $full_path, $match) ){
				$app_path = $match[1];
			}
			
			
			/*
			preg_match("|^$doc_path(.+)$file_name|",$full_path,$match);
			$this->d($match);
			$app_path = $match[1];
			*/
			
			$route = array();
			$route['app_root'] = $app_root;
			$route['fullpath'] = $full_path;
//			$route['path'] = dirname($path);
			$route['path'] = dirname($app_path);
			$route['file'] = $file_name;
			$route['args'] = null;
			$route['pass'] = true;
			$route['ctrl'] = null;
			$route = $this->Escape($route);
			
			//  Simple controller search
			$temp = $route['path'];
			foreach( array_reverse( explode('/',$route['path'].'/') ) as $dir ){
				$temp = preg_replace( "|/$dir$|", '', $temp);
				if( file_exists( getcwd() . $temp .'/'. $controller ) ){
					$route['ctrl'] = $temp;
					break;
				}
			}
			
			//  file exists path
			$real_path = rtrim($app_root,'/').'/'.trim($route['path'],'/').'/'.ltrim($route['file'],'/');
			
			/*
			$this->d($route);
			$this->mark($app_path);
			$this->mark('real_path='.$real_path);
			*/
			
			//  
			if( file_exists($real_path) ){
				
			//	$this->mark();
				
				switch( strtolower($extension) ){
					case 'html':
						if( $this->GetEnv('HtmlPassThrough') ){
							return $route;
						}else{
							$this->mark("![.red[HtmlPassThrough is off. please \$app->SetEnv('HtmlPassThrough',true);]]");
						}
						break;
						
					case 'css':
						$this->doCss($route);
						exit(0);
						
					case 'js':
						$this->doJs($route);
						exit(0);
					default:
						$this->mark("![.red[Does not match extension. ($extension)]]");
				}
			}else{
			//	$this->mark();
			}
		}
		
		// separate query
		list( $path, $query_string ) = explode('?',$request_uri.'?');
		
		// create absolute path
		$absolute_path = $_SERVER['DOCUMENT_ROOT'] . $path;
		
		// execute dir
//		chdir( dirname($_SERVER['SCRIPT_FILENAME']) );
		
		//$app_root = getcwd();
		$app_root = $this->GetEnv('AppRoot');
		
		//	absolute from current dir
		$file_path = preg_replace("|$app_root|",'',$absolute_path);
		
		/*
		$this->mark($absolute_path);
		$this->mark( getcwd() );
		$this->mark(dirname($_SERVER['SCRIPT_FILENAME']));
		$this->mark($app_root);
		$this->mark($file_path);
		*/
		
		//	search controller
		$dirs = explode( '/', rtrim($file_path,'/') );
		$args = array();
		while( count($dirs) ){
			
			$file_name = rtrim($app_root,'/').'/'.trim(join('/',$dirs)).'/'.$controller;
		//	$io = file_exists($file_name) ? 'true': 'false';
		//	$this->mark("file_name = $file_name, dir = $dir, io = $io");
			
			if( file_exists($file_name) ){
				break;
			}
			
			$args[] = array_pop($dirs);
		}
		
		// anti nortice error
		if(!count($args)){
			$args[0] = null;
		}
		
		//  build
		$route['path'] = join('/',$dirs);
		$route['file'] = $controller;
		$route['args'] = array_reverse($args);
		//$this->d($route);
		
		//  escape
		$route = $this->Escape($route);
		
		return $route;
	}
	
	function HtmlPassThrough()
	{
		
	}
	
	function Dispatch($route=null)
	{
		// Deny two time dispatch
		if( $this->isDispatch ){
			$this->StackError("Dispatched two times. (Dispatched only one time.)");
			return;
		}else{
			$this->isDispatch = true;
		}
		
		// if route is emtpy, get route.
		if(!$route){
			if(!$route = $this->GetRoute()){
				return false;
			}
		}
		
		// route info
		$this->SetEnv('route',$route);
		
		// settigns
		$this->doSettings($route);
		
		// controller root
		$app_root = $this->GetEnv('AppRoot');
		$ctrl = isset($route['ctrl']) ? $route['ctrl']: $route['path'];
		$ctrl_root = $app_root . $ctrl;
		$this->SetEnv('Ctrl-Root',$ctrl_root);
		
		// change dir
		$chdir = rtrim($app_root,'/') .'/'. trim($route['path'],'/');
		chdir( $chdir );
		
		//  content
		$this->doContent();
		
		//  layout
		$this->doLayout();
		
		//  
		if( $this->content ){
			$this->p('![ .big .bold .red [ Lost <?php $this->Content(); ?> ]]');
			print $this->content;
			$this->content = '';
		}
		
		return true;
	}
	
	function doContent()
	{	
		//  route
		if(!$route = $this->GetEnv('route')){
			$this->StackError('Empty route.');
			return false;
		}
		
		//  contrller file path
		$path = getcwd().'/'.$route['file'];
		
		//  content
		try{
			$this->content  = ob_get_contents(); ob_clean();
			$this->content .= $this->GetTemplate($path);
		}catch( Exception $e ){
			$this->StackError($e->getMessage());
		}
		
		return true;
	}
	
	function doSettings($route)
	{
		/**
		 * Search begins from AppRoot.
		 * settings-file is looked for forward Dispatch-dir, from AppRoot
		 */
		 
		//  Get settings file name.
		if(!$settings = $this->GetEnv('settings-name')){
			return true;
		}
		
		//  Get app root.
		$app_root = $this->GetEnv('AppRoot');
		$app_root = rtrim( $app_root, '/');
		
		//  Search settings file, and execute settings.
		$save_dir = getcwd();
		foreach(explode('/', '/'. $route['path']) as $dir){
			$dirs[] = $dir;
			$path = $app_root.join('/',$dirs)."/$settings";
			
			if( file_exists($path) ){
				chdir( dirname($path) );
				$io = include($path);
			}
		}
		
		//  Recovery current directory.
		chdir($save_dir);
		
		return true;
	}
	
	function doLayout()
	{
		//  check the layout is set. 
		if( $layout = $this->GetEnv('layout') ){
			//	layout has been set.
		}else{
			//  does not set layout.
			print $this->content;
			if( $this->admin() ){
				$this->mark("![ .gray [Hint: layout uses \$app->SetEnv('layout','app:/path/to/your/self')]]");
			}
			return;
		}
		
		//  get controller name
		$controller = $this->GetEnv('controller-name');
		
		//  check the layout-directory is set.
		if( $layout_dir = $this->GetEnv('layout-dir') ){
			//  layout has been set.
			$layout_dir = $this->ConvertPath($layout_dir);
			$path = rtrim($layout_dir,'/') .'/'. $layout .'/'. $controller;
		}else{
			$path = $this->ConvertPath($layout) .'/'. $controller;
		}
		
		//  for debug
		if( 0 ){
			$temp['controller'] = $controller;
			$temp['layout']     = $layout;
			$temp['layout_dir'] = $layout_dir.' ('.$this->GetEnv('layout-dir').')';
			$temp['app-root']   = $this->GetEnv('app-root');
			$temp['proj-root']  = $this->GetEnv('proj-root');
			$temp['site-root']  = $this->GetEnv('site-root');
			$temp['ConvertPath']= $this->ConvertPath($layout) . ", layout=$layout";
			$temp['path']       = $path;
			$this->d($temp);
		}
		
		//  include controller
		if( file_exists($path) ){
			//  OK
			include($path);
		}else{
			//  NG
			print $this->content;
			$this->StackError("does not exists layout controller.($path)",'layout');
			return;
		}
		
		//  layout directory
		$layout_dir = dirname($path) . '/';
		
		//  do layout
		foreach($_layout as $var_name => $file_name){
			$path = $layout_dir . $file_name;
			if( file_exists($path) ){
				ob_start();
				$this->mark($path,'layout');
				include($path);
				${$file_name} = ob_get_contents();
				${$var_name}  = & ${$file_name};
				ob_end_clean();
			}else{
				$this->StackError("does not exists layout file.($path)");
			}
		}
		
		print ${$file_name};
	}
	
	function doCss($route)
	{
		//  Init garbage code. 
		ob_clean();
		
		//  Print headers.
		header("Content-Type: text/css");
		header("X-Content-Type-Options: nosniff");
		
		//  Full path of file.
		$path = $_SERVER['DOCUMENT_ROOT'].$route['path'].'/'.$route['file'];
		
		//  Change cli mode.
		$this->SetEnv('cli',true);
		
		//  Execute.
		$this->template( $path );
		exit(0);
	}
	
	function doJs($route)
	{
		$this->SetEnv('cli',true);
		exit(0);
	}
	
	function GetTemplate( $file, $args=null )
	{
		// ob_start is stackable
		if( ob_start() ){
			$this->template( $file, $args );
			$temp = ob_get_contents();
			$io   = ob_end_clean();
		}else{
			$this->StackError("ob_start failed.");
		}
		
		return $temp;
	}
	
	function Header( $str, $replace=null, $code=null ){
	
		if( null === $replace ){
			switch($str){
				case 'hoge':
					$replace = false;
					break;
				default:
					$replace = true;
			}
		}
	
		if( headers_sent() ){
			$io = false;
			$this->StackError("already header sent.");
		}else{
			$io = true;
			$str = str_replace( array("\n","\r"), '', $str );
			header( $str, $replace, $code );
		}
	
		return $io;
	}
	
	function Location( $url, $exit=true )
	{
		$url = $this->ConvertUrl($url);
	
		$location = $this->GetSession('Location');
		if( $url === $location['referer'] ){
			$this->StackError("Redirect is roop. ($url)");
			return false;
		}
	
		$io = $this->Header("Location: " . $url);
		if( $io ){
			$location['message'] = 'Do Location!!' . date('Y-m-d H:i:s');
			$location['post']	 = $_POST;
			$location['get']	 = $_GET;
			$location['referer'] = $_SERVER['REQUEST_URI'];
			$this->SetSession( 'Location', $location );
			if($exit){
				$this->Vivre(false);
				$this->__destruct();
				exit(0);
			}
		}
		return $io;
	}
	
	function Doctype( $args=null )
	{
		if( $args ){
			$this->SetEnv('doctype',$args);
		}else{
			switch($this->GetEnv('doctype')){
				case 'xhtml':
					break;
				case 'html, 4.01, strict':
					break;
				case 'html':
				default:
					$doctype = 'html';
			}
			print $doctype;
		}
	}
	
	function Lang( $args=null )
	{
		if( $args ){
			$this->SetEnv('lang',$args);
		}else{
			print $this->GetEnv('lang');
		}
	}
	
	function Charset( $args=null )
	{
		if( $args ){
			$this->SetEnv('charset',$args);
		}else{
			print $this->GetEnv('charset');
		}
	}
	
	function Keywords( $args=null )
	{
		if( $args ){
			$this->SetEnv('keywords',$args);
		}else{
			print $this->GetEnv('keywords');
		}
	}
	
	function Description( $args=null )
	{
		if( $args ){
			$this->SetEnv('description',$args);
		}else{
			print $this->GetEnv('description');
		}
	}
	
	function Title( $args=null )
	{
		if( $args ){
			$this->SetEnv('title',$args);
		}else{
			print $this->GetEnv('title');
		}
	}
	
	function GetContent()
	{
		return $this->content;
	}
	
	function Content()
	{
		print $this->content;
		$this->content = '';
	}
	
	function GetArgs()
	{
		$route = $this->GetEnv('route');
		return $route['args'];
	}
	
	function GetRequest( $keys=null, $method=null )
	{
		return Toolbox::GetRequest( $keys, $method );
	}
	
	function NotFound()
	{
		if( $page = $this->GetEnv('NotFound') ){
			return $this->template($page);
		}else{
			$this->StackError('Does not set env "NotFound" page path. Please call $this->SetEnv("NotFound").');
		}
	}
}

/**
 * Inherit the NewWorld.
 * 
 * Don't edit NewWorld
 * Please create your original App class. 
 * 
 * @author Tomoaki Nagahara
 *
 */
class App extends NewWorld5
{
	private $cmgr = null;
	function Config( $cmgr=null )
	{
		if( $cmgr ){
			$this->cmgr = $cmgr;
		}

		return $this->cmgr;
	}
	
	function GetAction()
	{
		if(!$action = $this->GetEnv('action') ){
			//  Does not undefine.
			$args = $this->GetArgs();
			$action = $args[0] ? $args[0]: 'index';
		}
		return $action;
	}
}

/**
 * Create NewWorld uses action class.
 * 
 * This class is proposal.
 * You should design the action freely. (OnePiece is free!!)
 * 
 * @author Tomoaki Nagahara
 *
 */
abstract class NewWorld5Action extends OnePiece5
{
	function Init()
	{
		
	}
	
	function GetArgs()
	{
		$route = $this->GetEnv('route');
		
		return @$route['args'];
	}
	
	function GetAction()
	{
		$args = $this->getArgs();
		
		return @$args[0];
	}
	
	function Start()
	{
		$this->p('Please create a "Start" method, to inherited class.');
	}
	
	function doIndex()
	{
		$this->p('Please create a "doIndex" method, to inherited action class.');
	}
	
	function doError( $str )
	{
		if(!$str){
			$this->StackError('Error message is empty.');
			exit;
		}
		
		$this->p($str);
		$this->StackError($str);
		exit;
	}
	
	function HtmlPassThrough()
	{
		$this->mark();
		
		if(!$this->GetEnv('HtmlPassThrough')){
			if( preg_match('|\.html$|',$_SERVER['REDIRECT_URL']) ){
				$this->mark('$this->GetEnv("HtmlPassThrough") is false.');
			}
		}
		
		$route = $this->GetEnv('route');
		$path = join( '/', $route['args']);
		if(!preg_match('|\.html$|',$path)){
			return false;
		}
		
		if(!file_exists($path)){
			return false;
		}
		
		$io = $this->template('./'.$path);
		
		return $io;
	}
	
	function Pager($separater=null)
	{
		return Pagination($separater);
	}
	
	function Paging($separater=null)
	{
		return Pagination( $separater );
	}
	
	function Pagination( $separater = ' | ' )
	{
		$page_current = @$this->page->current ? $this->page->current: 1;
		$page_num = ceil($this->page->max / $this->page->per);
		
		for( $i = 1; $i<=$page_num ; $i++ ){
			if( $i == $page_current ){
				$join[] = sprintf('<span class="pagination paginationCurrent">%s</span>', $i, $i );
			}else{
				$join[] = sprintf('<a href="./%s" class="pagination">%s</a>', $i, $i );
			}
		}
		
		print join( $separater, $join );
	}
	
	/**
	 * Set current page
	 * 
	 * @param integer $rpp
	 */
	function SetPageCurrent($page)
	{
		if( $page >= 1 ){
			$this->page->current = $page;
		}
	}
	
	/**
	 * Set Records per page.
	 * 
	 * @param integer $rpp
	 */
	function SetPageRecordsPer($rpp = 20 )
	{
		$this->page->per = $rpp;
	}
	
	/**
	 * Set to records count by SQL
	 * 
	 * @param integer $rpp
	 */
	function SetPageRecordsMax($count)
	{
		$this->page->max = $count;
	}
}

/**
 * Abstract class is inherit.
 * 
 * This is a proposal, A design is your freedom. 
 * Thus, please create a class by yourself. 
 * 
 * @author Tomoaki Nagahara
 *
 */
class myAction extends NewWorld5Action
{
	function Init()
	{
		parent::Init();
	}
	
	function Start( $_args=null )
	{
		$action = $this->GetAction();
		$args   = $this->GetArgs();
		$action = $action ? $action : 'index';
		$method = 'do'.ucfirst(strtolower($action));
		if(!method_exists($this, $method)){
			$method = 'doIndex';
		}
		
		// debug information
		$this->mark('class='.$this->born[1]['class'], 'action');
		$this->mark('action='.$action, 'action');
		$this->mark('method='.$method, 'action');
		$this->mark('args='.serialize($args), 'action');
		
		// HTML file is direct output
		if( $io = $this->HtmlPassThrough() ){
			return $io;
		}
		
		return $this->{$method}($_args);
	}
}
