<?php
/**
 * Inherit the NewWorld.
 * 
 * Don't edit NewWorld
 * Please create your original App class. 
 * 
 * @author Tomoaki Nagahara
 *
 */

include_once('NewWorld5.class.php');

class App extends NewWorld5
{
	function Init()
	{
		parent::Init();
	}
	
	/**
	 * @var ConfigMgr
	 */
	private $cmgr = null;
	
	/**
	 * 
	 * @param  string $cmgr
	 * @return ConfigMgr
	 */
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
	
	function SetControllerName( $var )
	{
		return $this->SetEnv('controller-name', $var);
	}
	
	function SetSettingName( $var )
	{
		return $this->SetEnv('setting-name', $var);
	}
	
	function SetModelDir( $var )
	{
		return $this->SetEnv('model-dir', $var);
	}

	function SetModuleDir( $var )
	{
		return $this->SetEnv('module-dir', $var);
	}
	
	function SetLayoutDir( $var )
	{
		$this->SetEnv('layout-root',$this->ConvertURL($var));
		return $this->SetEnv('layout-dir', $var);
	}
	
	function SetLayoutName( $var )
	{
		return $this->SetEnv('layout', $var);
	}
	
	function SetLayoutPath( $var )
	{
		return $this->SetEnv('layout', $var);
	}
	
	function GetTemplateDir( $var )
	{
		return $this->GetEnv('template-dir');
	}

	function SetTemplateDir( $var )
	{
		return $this->SetEnv('template-dir', $var);
	}
	
	function SetHtmlPassThrough( $var )
	{
		return $this->SetEnv('HtmlPassThrough', $var);
	}
	
	function SetTitle( $var )
	{
		return $this->SetEnv('title', $var);
	}
	
	function Title()
	{
		print $this->GetEnv('title');
	}
	
	function SetDoctype( $var )
	{
		$this->SetEnv('doctype',$args);
	}
	
	function Doctype($doctype=null)
	{
		if(!$doctype){
			$doctype = $this->GetEnv('doctype');
		}
		
		switch($doctype){
			case '<?xml version="1.0" encoding="UTF-8"?>':
				break;
				
			case 'xhtml':
				$doctype = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN">';
				break;
				
			case 'html':
				$doctype = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN">';
				break;
				
			default:
				$doctype = '<!DOCTYPE html>';
		}
		print $doctype;
	}

	function SetLang( $var )
	{
		$this->SetEnv('lang',$args);
	}
	
	function Lang()
	{
		print $this->GetEnv('lang');
	}

	function SetCharset( $var )
	{
		$this->SetEnv('charset',$args);
	}
	
	function Charset( $args=null )
	{
		print $this->GetEnv('charset');
	}
	
	function AddKeyword( $var )
	{
		$this->AddKeywords( $var );
	}
	
	function AddKeywords( $var )
	{
		$keywords = $this->GetEnv('keywords');
		$keywords.= ", $var";
		$this->SetEnv('keywords',$keywords);
	}
	
	function SetKeyword( $var )
	{
		$this->SetEnv('keywords',$args);
	}
	
	function SetKeywords( $var )
	{
		$this->SetEnv('keywords',$args);
	}
	
	function Keywords()
	{
		print $this->GetEnv('keywords');
	}

	function SetDescription( $var )
	{
		$this->SetEnv('description',$args);
	}
	
	function Description()
	{
		print $this->GetEnv('description');
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
/*
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
	
	function SetPageCurrent($page)
	{
		if( $page >= 1 ){
			$this->page->current = $page;
		}
	}
	
	function SetPageRecordsPer($rpp = 20 )
	{
		$this->page->per = $rpp;
	}
	
	function SetPageRecordsMax($count)
	{
		$this->page->max = $count;
	}
}
*/

/**
 * Abstract class is inherit.
 * 
 * This is a proposal, A design is your freedom. 
 * Thus, please create a class by yourself. 
 * 
 * @author Tomoaki Nagahara
 *
 */
/*
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
*/
