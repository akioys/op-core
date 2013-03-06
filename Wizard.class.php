<?php
/**
 * 
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * 
 */
class Wizard extends OnePiece5
{
	private $config = null;
	
	function Config()
	{
		if(!$this->config){
			$this->config = new WizardConfig();
		}
		return $this->config;
	}
	
	function Selftest( $config )
	{
		$this->model('Log')->Set("Start selftest.");
		
		if(!$this->pdo()->Connect($config->database) ){
			$this->DoWizard( $config );
			return;
		}
		
		try{
			$this->CheckDatabase($config);
			$this->CheckTable($config);
		//	$this->CheckColumn($config);
			$io = true;
		}catch( Exception $e ){
			$this->p( $e->getMessage() );
			$this->DoWizard( $config );
			$io = false;
		}
		
		$this->d($this->model('Log')->Get());
		
		return $io;
	}
	
	function DoWizard( $config )
	{
		//  Get form name.
		$form_name = $this->config()->FormName();
		
		//  Init form config.
		$this->form()->AddForm( $this->config()->MagicForm() );
		
		//  Check secure
		if( $this->form()->Secure($form_name) ){
			
			$database = Toolbox::Copy( $config->database );
			$database->user     = $this->form()->GetInputValue('user',$form_name);
			$database->password = $this->form()->GetInputValue('password',$form_name);
			
			//  Remove database name. (only connection, If not exists database.)
			unset($database->database);
			
			//  Connect to administrator account.
			if(!$io = $this->pdo()->Connect( $database ) ){
				$database->d();
			}else{
				$this->model('Log')->Set("Connect {$database->user} account.");
			}
			
			$this->CreateDatabase($config);
			$this->CreateTable($config);
			$this->CreateColumn($config);
			$this->CreateUser($config);
			$this->CreateGrant($config);
			
		}else{
		//	$this->form()->Debug($form_name);
		}
		
		//  Print form.
		$this->PrintForm();
	}
	
	function PrintForm()
	{
		//  Get input decorate.
		$decorate = $this->config()->InputDecorate();
		
		//  Print form.
		$form_name = $this->config()->FormName();
		$this->form()->Start($form_name);
		foreach ( array('user','password','submit') as $input_name ){
			printf(
				$decorate,
				$this->form()->GetLabel($input_name),
				$this->form()->GetInput($input_name),
				$this->form()->GetError($input_name)
			);
		}
		$this->form()->Finish($form_name);
	}
	
	function CheckDatabase($config)
	{
		//  Start
		$this->model('Log')->Set('START: '.__FUNCTION__);
		
		//  Get database list.
		$db_name = $config->database->database;
		$db_list = $this->pdo()->GetDatabaseList($config->database);
		
		//  Check database exists.
		$io = array_search( $db_name, $db_list);
		if( $io === false){
			$me = "Database is does not exists. ($db_name)"; 
			throw new OpException($me);
		}

		//  Finish
		$this->model('Log')->Set('FINISH: '.__FUNCTION__);
		return true;
	}
	
	function CheckTable($config)
	{
		//  Start
		$this->model('Log')->Set('START: '.__FUNCTION__);
		
		//  Get table list.
		$table_list = $this->pdo()->GetTableList($config->database);
	//	$this->d($table_list);
		
		foreach( $config->table as $table_name => $table ){
			$io = array_search( $table_name, $table_list);
			if( $io === false ){
				$me = "Does not find table. ($table_name)";
				throw new OpException($me);
			}else{
				$this->CheckColumn( $config, $table_name );
			}
		}

		//  Finish
		$this->model('Log')->Set('FINISH: '.__FUNCTION__);
		return true;
	}
	
	function CheckColumn( $config, $table_name )
	{
		//  Start
		$this->model('Log')->Set('START: '.__FUNCTION__);
		
		$columns = Toolbox::toArray($config->table->$table_name->column);
		$structs = $this->pdo()->GetTableStruct( $table_name );
		$diff = array_diff_key( $columns, $structs );
		
		if( count($diff) ){
			$join = join(', ', array_keys($diff) );
			$me = "Does not match column. ($join)";
			throw new OpException($me);
		}
		
		//  Check detail
		foreach( $columns as $column_name => $column ){
			$this->d($column);
		}

		//  Finish
		$this->model('Log')->Set('FINISH: '.__FUNCTION__);
		return true;
	}
	
	function CreateDatabase($config)
	{
		//  Start
		$this->model('Log')->Set('START: '.__FUNCTION__);
		
		//  Create database
		$io = $this->pdo()->CreateDatabase( $config->database );

		//  Finish
		$this->model('Log')->Set('FINISH: '.__FUNCTION__." ($io)");
		return $io;
	}
	
	function CreateTable($config)
	{
		//  Start
		$this->model('Log')->Set('START: '.__FUNCTION__);
		
		if(empty($config->table)){
			return true;
		}
		
		foreach( $config->table as $table ){
			if( empty($table->database) ){
				$table->database = $config->database->database;
			}
			$io = $this->pdo()->CreateTable($table);
		}
		
		//  Finish
		$this->model('Log')->Set('FINISH: '.__FUNCTION__." ($io)");
		return $io;
	}
	
	function CreateColumn($config)
	{
		//  Start
		$this->model('Log')->Set('START: '.__FUNCTION__);
		
		$this->model('Log')->Set('Does not implements yet.');
		
		//  Finish
		$this->model('Log')->Set('FINISH: '.__FUNCTION__." ($io)");
		return $io;
	}
	
	function CreateUser($config)
	{
		//  Start
		$this->model('Log')->Set('START: '.__FUNCTION__);
		
		//  Inist
		$config->user->host     = $config->database->host;
		$config->user->user     = $config->database->user;
		$config->user->password = $config->database->password;
		
		//  Check user exists.
		$list = $this->pdo()->GetUserList();
		if( array_search( $config->user->user, $list ) !== false ){
			$this->model('Log')->Set("New user {$config->user->user} is already exists.");
			return true;
		}
		
		//  Create user
		$io = $this->pdo()->CreateUser($config->user);
		
		//  Finish
		$this->model('Log')->Set('FINISH: '.__FUNCTION__." ($io)");
		return $io;
	}

	function CreateGrant($config)
	{
		//  Start
		$this->model('Log')->Set('START: '.__FUNCTION__);
		
		//  Init
		//$this->d( Toolbox::toArray($config) );
		$config->grant->host     = $config->database->host;
		$config->grant->database = $config->database->database;
		$config->grant->user     = $config->database->user;
		
		//  Create grant
		foreach( $config->table as $table_name => $table ){
			$config->grant->table = $table_name;
			if( $io = $this->pdo()->Grant($config->grant) ){
				$me = "Grant is failed. ($table_name)";
				throw new OpException($me);
			}
		}
		
		//  Finish
		$this->model('Log')->Set('FINISH: '.__FUNCTION__." ($io)");
		return $io;
	}
}

class WizardConfig extends ConfigMgr
{
	function FormName()
	{
		return 'op_magic_form';
	}
	
	function MagicForm()
	{
		$config = new Config();
		
		//  form name
		$config->name = $this->FormName();
		
		//  user
		$input_name = 'user';
		$config->input->$input_name->label = 'User';
		$config->input->$input_name->name  = $input_name;
		$config->input->$input_name->value = 'root';
		$config->input->$input_name->validate->required = true;
		
		//  password
		$input_name = 'password';
		$config->input->$input_name->label = 'Password';
		$config->input->$input_name->name  = $input_name;
		$config->input->$input_name->type  = 'password';
		
		//  submit
		$input_name = 'submit';
		$config->input->$input_name->label = '';
		$config->input->$input_name->name  = $input_name;
		$config->input->$input_name->type  = 'submit';
		$config->input->$input_name->value = 'Submit';
		
		return $config;
	}
}

class WizardHelper extends OnePiece5
{
	/**
	 * Create base config
	 * 
	 * @param string $user_name
	 * @param string $password
	 * @param string $host_name
	 * @param string $database_name
	 * @param string $table_name
	 * @param string $driver
	 * @param string $charset
	 */
	static function GetBase( $user_name, $password, $host_name, $database_name, $table_name, $driver='mysql', $charset='utf8' )
	{
		//  init
		$database->driver   = $driver;
		$database->host     = $host_name;
		$database->user     = $user_name;
		$database->password = $password;
		$database->database = $database_name;
		$database->charset  = $charset;

		$config = new Config();
		$config->database = $database;
		
		return $config;
	}
}
