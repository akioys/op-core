<?php

class Model_OpUser extends Model_Model
{
	private $table_name = 'op_user';
	const TABLE_OP_USER			 = 'op_user';
	const TABLE_OP_USER_INFO	 = 'op_user_info';
	const TABLE_OP_USER_AGENT	 = 'op_user_agent';
	
	private $isFirstVisit = false;
	private $isReVisit    = false;
	
	function Init($config=null)
	{
		parent::Init($config);
		$this->Selftest();
		$this->InitOpUserId();
		$this->InitOpUserInfo();
		$this->InitOpUserAgent();
	}
	
	function Config($name='ConfigOpUser')
	{
		return parent::Config($name);
	}
	
	function Selftest()
	{
		$config = ConfigOpUser::Selftest();
		$wz = new Wizard();
		$wz->selftest($config);
	}
	
	function InitOpUserId()
	{
		if( $this->GetSession('op_user_id') ){
			return true;
		}
		
		//  onepiece uniq id
		$op_uniq_id = $this->GetCookie('op-uniq-id');
		
		//  get config
		$select = $this->config()->select($this->table_name);
		$select->where->op_uniq_id = $op_uniq_id;
		$select->limit = 1;
		
		//  get record
		$record = $this->pdo()->select($select);
		if( isset($record['user_id']) ){

			//  Re-visit
			$this->isReVisit = true;
			
			//  have already registration
			$user_id = $record['user_id'];
		}else{
			//  First Registration
			$insert = $this->config()->insert($this->table_name);
			$insert->set->op_uniq_id = $op_uniq_id;
			if(!$user_id = $this->pdo()->insert($insert)){
				return false;
			}

			//  first-visit
			$this->isFirstVisit = true;
		}
		
		$this->SetSession('op_user_id',$user_id);
	}
	
	function InitOpUserInfo()
	{
		if(!$user_id = $this->GetOpUserId()){
			return false;
		}
		
		//  First visit
		if( $this->isFirstVisit ){
			$insert = $this->config()->insert( self::TABLE_OP_USER_INFO );
			$insert->set->user_id = $user_id;
			$this->pdo()->insert($insert);
		}
		
		// Re visit
		if( $this->isReVisit ){
			$update = $this->config()->update( self::TABLE_OP_USER_INFO );
			$update->set->visits = '+1';
			$update->where->user_id = $user_id;
			$update->limit = 1;
			$this->pdo()->update($update);
		}
	}
	
	function InitOpUserAgent()
	{
		$key = 'op_ua_id';
		
		if( $op_ua_id = $this->GetSession($key) ){
			return;
		}
		
		if( $op_ua_id = $this->GetCookie($key) ){
			$this->SetSession($key,$op_ua_id);
			return;
		}
		
		$ua = $_SERVER['HTTP_USER_AGENT'];
		
		$insert = $this->config()->insert( self::TABLE_OP_USER_AGENT );
		$insert->set->user_agent = $ua;
		$id = $this->pdo()->insert($insert);
		
		if( $id ){
			$this->SetSession($key,$id);
			$this->SetCookie($key,$id);
		}
	}
	
	function GetOpUserId()
	{
		return $this->GetSession('op_user_id');
	}
	
	function GetStatus()
	{
		
	}
	
	function SetMessage($message)
	{
		return $this->SetSession('message',$message);
	}
	
	function GetMessage()
	{
		return $this->GetSession('message');
	}
	
	function GetNickName()
	{
		return 'guest';
	}
}

class ConfigOpUser extends ConfigModel
{
	static function database()
	{
		$config = parent::database();
		$config->user     = 'op_model_opuser';
		return $config;
	}
	
	static function Selftest()
	{
		//  Get config
		$config = new Config();
		$config->database = self::database();
		
		//  Tables (op_user)
		$table_name = 'op_user';
		$config->table->{$table_name}->table   = $table_name;
		$config->table->{$table_name}->comment = 'This is wizard test.';
			
			//  Columns
			$column_name = 'user_id';
			$config->table->{$table_name}->column->{$column_name}->name = $column_name;
			$config->table->{$table_name}->column->{$column_name}->ai   = true;
			
			$column_name = 'op_uniq_id';
			$config->table->{$table_name}->column->{$column_name}->name = $column_name;
			$config->table->{$table_name}->column->{$column_name}->type = 'text';
			
			//  created, updated, deleted
			$config->table->{$table_name}->column->merge(parent::Column());

			
		//  Tables (op_user_info)
		$table_name = 'op_user_info';
		$config->table->{$table_name}->table   = $table_name;
		$config->table->{$table_name}->comment = 'This is wizard test.';
				
			//  Columns
			$column_name = 'user_id';
			$config->table->{$table_name}->column->{$column_name}->name = $column_name;
			$config->table->{$table_name}->column->{$column_name}->ai   = true;
				
			$column_name = 'visits';
			$config->table->{$table_name}->column->{$column_name}->name = $column_name;
			$config->table->{$table_name}->column->{$column_name}->type = 'int';
				
			//  created, updated, deleted
			$config->table->{$table_name}->column->merge(parent::Column());
			

		//  Tables (op_user_agent)
		$table_name = 'op_user_agent';
		$config->table->{$table_name}->table   = $table_name;
		$config->table->{$table_name}->comment = 'This is wizard test.';
			
			//  Columns
			$column_name = 'user_id';
			$config->table->{$table_name}->column->{$column_name}->name = $column_name;
			$config->table->{$table_name}->column->{$column_name}->ai   = true;
			
			$column_name = 'user_agent';
			$config->table->{$table_name}->column->{$column_name}->name = $column_name;
			$config->table->{$table_name}->column->{$column_name}->type = 'text';
			
			//  created, updated, deleted
			$config->table->{$table_name}->column->merge(parent::Column());
				
		$config->d();
		
		return $config;
	}
}
