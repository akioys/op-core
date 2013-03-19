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
		if(!$this->pdo()->Connect($config->database) ){
			$this->DoWizard( $config );
			return;
		}
		
		try{
			$this->CheckDatabase($config);
			$this->CheckTable($config);
			$this->CheckColumn($config);
			$io = true;
		}catch( Exception $e ){
			$this->p( $e->getMessage() );
			$this->DoWizard( $config );
			$io = false;
		}
		
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
		
	}
	
	function CheckTable($config)
	{
		
	}
	
	function CheckColumn($config)
	{
		
	}
	
	function CreateDatabase($config)
	{
		$io = $this->pdo()->CreateDatabase( $config->database );
		return $io;
	}
	
	function CreateTable($config)
	{
		if(empty($config->table)){
			return true;
		}
		
		foreach( $config->table as $table ){
			if( empty($table->database) ){
				$table->database = $config->database->database;
			}
			$io = $this->pdo()->CreateTable($table);
		}
		return $io;
	}
	
	function CreateColumn($config)
	{
	//	$this->mark(__METHOD__);
	}
	
	function CreateUser($config)
	{
		$config->user->host     = $config->database->host;
		$config->user->user     = $config->database->user;
		$config->user->password = $config->database->password;
		
		$io = $this->pdo()->CreateUser($config->user);
	}

	function CreateGrant($config)
	{
		$this->d( Toolbox::toArray($config) );
		$config->grant->host     = $config->database->host;
		$config->grant->database = $config->database->database;
		$config->grant->user     = $config->database->user;
		
		foreach( $config->table as $table_name => $table ){
			$config->grant->table = $table_name;
			if($io = $this->pdo()->Grant($config->grant)){
				return false;
			}
		}
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

		$database  = new Config();
		$config->database = $database;
		
		return $config;
	}
}
