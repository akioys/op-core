<?php

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
		}catch( Exception $e ){
			$this->p( $e->getMessage() );
			$this->DoWizard( $config );
		}
	}
	
	function DoWizard( $config )
	{
		//  Get form name.
		$form_name = $this->config()->FormName();
		
		//  Init form config.
		$this->form()->AddForm( $this->config()->MagicForm() );
		
		//  Check secure
		if( $this->form()->Secure($form_name) ){
			
			$database = $config->database;
			$database->user     = $this->form()->GetInputValue('user',$form_name);
			$database->password = $this->form()->GetInputValue('password',$form_name);
			$this->pdo()->Connect( $database );
			
			$this->CreateDatabase($config);
			$this->CreateTable($config);
			$this->CreateColumn($config);
			$this->CreateUser($config);
			$this->CreateAlter($config);
			
		}else{
			$this->form()->Debug($form_name);
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
		foreach( $config->table as $table ){
			$this->d( Toolbox::toArray($table) );
			if( empty($table->database) ){
				$table->database = $config->database->database;
			}
			$io = $this->pdo()->CreateTable($table);
		}
		return $io;
	}
	
	function CreateColumn($config)
	{
		
	}
	
	function CreateUser($config)
	{
		
	}

	function CreateAlter($config)
	{
	
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