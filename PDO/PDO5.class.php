<?php

class PDO5 extends OnePiece5
{
	private $pdo = null;
	private $dcl = null;
	private $ddl = null;
	private $dml = null;
	private $qu	 = null;
	private $qus = array();
	private $isConnect	 = null;
	private $driver		 = null;
	private $host		 = null;
	private $user		 = null;
	private $database	 = null;
	private $charset	 = null;
	
	function DML( $name=null )
	{
		if( empty($this->dml) ){
			if(!class_exists('DML',false)){
				$io = include_once('PDO/DML.class.php');
				if(!$io){
					throw new Exception("Include failed.(PDO/DML.class.php)");
				}
			}
				
			//  Init
			$this->dml = new DML();
			$this->dml->SetPDO( $this->pdo, $this->driver );
			$this->dml->InitQuote($this->driver);
		}
		return $this->dml;
	}
	
	function DDL( $name=null )
	{
		if( empty($this->ddl) ){
			if(!class_exists('DDL',false)){
				$io = include_once('PDO/DDL.class.php');
				if(!$io){
					throw new Exception("Include failed.(PDO/DDL.class.php)");
				}
			}
			
			//  Init
			$this->ddl = new DDL();
			$this->ddl->SetPDO( $this->pdo, $this->driver );
		}
		return $this->ddl;
	}
	
	function DCL( $name=null )
	{
		if( empty($this->dcl) ){
			if(!class_exists('DCL',false)){
				$io = include_once('PDO/DCL.class.php');
				if(!$io){
					throw new Exception("Include failed.(PDO/DCL.class.php)");
				}
			}
			
			//  Init
			$this->dcl = new DCL();
			$this->dcl->SetPDO( $this->pdo, $this->driver );
		}
		return $this->dcl;
	}
	
	function Qu($qu=null)
	{
		if( $qu ){
			$this->qu = $qu;
			$this->qus[] = $qu;
		}else{
			$qu = $this->qu;
			$this->qu = '';
		}
		
		return $qu;
	}
	
	function Qus()
	{
		return $this->qus;
	}
	
	function GetQuote( $driver )
	{
		switch( strtolower($driver) ){
			case 'mysql':
				$ql = $qr = '`';
				break;
		}
		return array($ql,$qr);
	}
	
	function Quote( $var, $driver )
	{
		list( $ql, $qr ) = self::GetQuote($driver);
		
		if( is_array($var) ){
			foreach( $var as $tmp ){
				$safe[] = $this->Quote($tmp);
			}
		}else if( strpos($var,'.') ){
			$temp = explode('.',$var);
			$this->d($temp);
			$safe = $ql.trim($temp[0]).$qr.'.'.$ql.trim($temp[1]).$qr;
		}else{
			$safe = $ql.trim($var).$qr;
		}
		return $safe;
	}
	
	function Query( $qu, $key=null )
	{
		//  Check PDO object
		if(!$this->pdo instanceof PDO ){
			$this->StackError("PDO is not instanced.");
			return false;
		}
		
		//  Save query.
		$this->qu($qu);
		
		//  Execute
		if( $st = $this->pdo->query( $this->qu ) ){
			//  success
			if( $st instanceof PDOStatement ){
				switch($key){
					case 'count':
						$return = $st->fetch(PDO::FETCH_ASSOC);
						if( isset($return['COUNT(*)']) ){
							$return = $return['COUNT(*)'];
						}else if( isset($return['COUNT()']) ){
							$return = $return['COUNT()'];
						}else{
							//$this->mark( $this->Qu() );
							//$this->d($return);
							$return = false;
						}
						break;
					
					case 'update':
						$return = $st->rowCount();
						break;
						
					default:
						$return = $st->fetchAll(PDO::FETCH_ASSOC);
				}
			}else{
				$this->d($st);
			}
		}else{			
			//  failed
			$return = false;
			$temp = $this->pdo->errorInfo();
			$this->StackError("{$temp[2]} : {$this->qu}");
		}

		return $return;
	}
	
	function ConvertCharset( $config )
	{
		$charset = isset($config->charset) ? $config->charset: $this->GetEnv('charset');
		
		switch( $charset ){
			case 'utf8':
				break;
				
			case 'utf-8':
				$charset = 'utf8';
				break;
		
			case 'sjis':
			case 'shift-jis':
			case 'shift_jis':
				$charset = PHP_OS === 'WINNT' ? 'sjis-win': 'sjis';
				break;
					
			case 'euc-jp':
				$charset = PHP_OS === 'WINNT' ? 'eucjpms': 'ujis';
				break;
		}

		return $charset;
	}
	
	function isConnect()
	{
		return $this->isConnect;
	}
	
	function Connect( $config )
	{
		if( is_array($config) ){
			$config = Toolbox::toObject($config);
		}
		
		//  init
		$this->driver   = $config->driver;	 // $conf['driver']   ? $conf['driver']:   null;
		$this->host     = $config->host;	 // $conf['host']     ? $conf['host']:     null;
		$this->user     = $config->user;	 // $conf['user']     ? $conf['user']:     null;
		$password       = $config->password; // $conf['password'] ? $conf['password']: null;
		$this->database = $config->database; // $conf['database'] ? $conf['database']: null;
		$this->charset  = $config->charset;	 // $conf['charset']  ? $conf['charset']:  $this->GetEnv('charset');
		
		$dsn = "{$this->driver}:dbname={$this->database};host={$this->host}";
		$options = array();
		
		try {
			if(!$this->pdo = new PDO( $dsn, $this->user, $password, $options )){
				$this->StackError("Can not connect database. ($key)");
				return false;
			}
		}catch( PDOException $e){
			$this->StackError( $e->GetMessage() );
			return false;
		}
		
		//  charset
		$charset = $this->ConvertCharset($config);
		if( $this->query("SET NAMES $charset") === false ){
			return;
		}
		
		//  connected flag
		$this->isConnect = true;
		
		return true;
	}
	
	function Database( $name )
	{
		return $this->query("USE $name") !== false ? true: false;
	}
	
	function GetDatabaseList($config=null)
	{
		//  init config
		if(!is_array($config)){
			$config = Toolbox::toArray($config);
		}
		
		//  Which driver
		switch($this->driver){
			case 'mysql':
				$qu = 'SHOW DATABASES';
				break;
			default:
				$this->mark("Does not implements yet. ({$this->driver})");
		}
		
		return $this->query($qu);
	}
	
	function GetTableList($config=null)
	{
		//  init config
		if(!is_array($config)){
			$config = Toolbox::toArray($config);
		}
		
		//  database
		$database = isset($config['database']) ? $config['database']: $this->database;
		
		//  like
		$like = isset($config['like']) ? $config['like']: null;
		
		//  select database
		if( isset($config->database) ){
			if(!$this->Database($config->database)){
				return false;
			}
		}
		
		//  create qu
		$qu = "SHOW TABLES FROM `$database` $like ";
		
		//  get table list
		if( $records = $this->query($qu) ){
			foreach( $records as $i => $temp ){
				foreach( $temp as $n => $table_name ){
					$list[] = $table_name;
				}
			}
		}
		
		return $list;
	}
	
	function GetTableStruct($config)
	{
		//  init config
		if(!is_array($config)){
			$config = Toolbox::toArray($config);
		}
		
		//  database
		if(isset($config['database'])){
			$this->Database($config['database']);
		}
		
		//  table
		if(isset($config['table'])){
			$table = $config['table'];
		}else{
			$this->StackError("Empty table name.");
			return false;
		}
		
		//  create query
		$qu = "SHOW FULL COLUMNS FROM $table";
		
		//  get table struct
		if(!$records = $this->query($qu) ){
			return false;
		}
		
		//  length
		foreach( $records as $record ){
			$record = array_change_key_case( $record, CASE_LOWER );
			$name = $record['field'];
			if(preg_match('/([a-z]+)\(([0-9]+)\)/',$record['type'],$match)){
				$record['type']   = $match[1];
				$record['length'] = $match[2];
			}
			$struct[$name] = $record;
		}
		
		return $struct;
	}

	function Quick( $string, $config=null)
	{
		//  Get value
		//list( $left, $value ) = explode('=', trim($string) );
		if( preg_match('/(.+)[^><=]([=<>]{1,2})(.+)/', $string, $match) ){
			$left  = $match[1];
			$ope   = $match[2] == '=' ? null: $match[2].' ';
			$value = $match[3];
		//	$this->d($match);
		}else{
			$this->StackError("Format error. ($string)");
			return false;
		}
		
		//  Get column
		if( strpos( $left, '<') ){
			list( $column, $location ) = explode('<-', trim($left) );
		}else{
			$location = $left;
			$column = null;
		}
		//$this->mark("column=$column, location=$location, ope=$ope, value=$value");
	
		//  generate define
		$locations = array_reverse( explode('.', trim($location) ) );
		$target   = isset($locations[0]) ? $locations[0]: null;
		$table    = isset($locations[1]) ? $locations[1]: null;
		$database = isset($locations[2]) ? $locations[2]: null;
		$host     = isset($locations[3]) ? $locations[3]: null;
	
		//  create columns
		if( $column ){
			$columns = explode(',',str_replace(' ', '', $column));
		}else{
			$columns = null;
		}
	
		//  create value
		$value = trim($value);
		$value = trim($value,"'");
	
		//  create limit, offset, order
		$limit  = isset($config->limit)  ? $config->limit:  1;
		$offset = isset($config->offset) ? $config->offset: null;
		$order  = isset($config->order)  ? $config->order:  null;
	
		//  create config
		$config = new Config();
		$config->host     = $host;
		$config->database = $database;
		$config->table    = $table;
		$config->column   = $columns;
		$config->limit    = $limit;
		$config->offset   = $offset;
		$config->order    = $order;
		$config->where->$target = $ope.$value;
	
		//  get record
		$record = $this->Select($config);
		if( $record === false ){
			//$this->qu('Quick-Select is failed');
			//$this->d( Toolbox::toArray($config) );
			return false;
		}
		
		//  return all
		if( !$column or $limit != 1 ){
			return $record;
		}
	
		//  return one
		if( count($columns) === 1 ){
		//	return isset($record[$columns[0]]) ? $record[$columns[0]]: null;
			return array_shift($record);
		}
	
		//  return many
		foreach( $record as $key => $var ){
		//	$return[$key] = $var; // default is not index key.
			$return[] = $var;
		}
	
		return $return;
	}
	
	function CreateDatabase( $conf )
	{
		//  object to array
		if(!is_array($conf)){
			$conf = Toolbox::toArray($conf);
		}
				
		//  get select query
		if(!$qu = $this->ddl()->GetCreateDatabase($conf)){
			return false;
		}
		
		//  execute
		$io = $this->query($qu);
		
		return $io;
	}
	
	function CreateTable( $conf )
	{
		//  object to array
		if(!is_array($conf)){
			$conf = Toolbox::toArray($conf);
		}
		
		//  get select query
		if(!$qu = $this->ddl()->GetCreateTable($conf)){
			return false;
		}
		
		//  execute
		$io = $this->query($qu);
		
		return $io;
	}
	
	function CreateUser( $conf )
	{
		//  object to array
		if(!is_array($conf)){
			$conf = Toolbox::toArray($conf);
		}
		
		//  get select query
		if(!$qu = $this->ddl()->GetCreateUser($conf)){
			return false;
		}
		
		//  execute
		$io = $this->query($qu);
		
		return $io;
	}
	
	function Grant( $conf )
	{
		//  object to array
		if(!is_array($conf)){
			$conf = Toolbox::toArray($conf);
		}
	
		//  get select query
		if(!$qu = $this->dcl()->GetGrant($conf)){
			return false;
		}
	
		//  execute
		$io = $this->query($qu);
	
		return $io;
	}
	
	/**
	 * 
	 */
	function Count( $conf )
	{
		//  object to array
		if(!is_array($conf)){
			$conf = Toolbox::toArray($conf);
		}
		
		//  added count aggregate
		if( empty($conf['agg']['count']) ){
			$conf['agg']['count'] = '*';
		}
		
		//  get select query
		if(!$qu = $this->dml()->GetSelect($conf)){
			return false;
		}
		
		//  execute
		if(!$count = $this->query($qu,'count')){
			return false;
		}
		
		return $count;
	}
	
	function Select( $conf )
	{
		if(!$this->pdo){
			return false;
		}
		
		//  object to array
		if(!is_array($conf)){
			$conf = Toolbox::toArray($conf);
		}

		//  get select query
		if(!$qu = $this->dml()->GetSelect($conf)){
			return false;
		}

		//  execute
		if(($records = $this->query($qu)) === false ){
			return false;
		}
		
		//  if limit is 1
		if( isset($conf['limit']) and $conf['limit'] == 1){
			if( isset($records[0]) ){
				return $records[0];
			}
		}
		
		//  return to records.
		return $records;
	}
	
	function Insert( $conf )
	{
		if(!$this->pdo){
			return false;
		}
		
		//  object to array
		if(!is_array($conf)){
			$conf = Toolbox::toArray($conf);
		}
		
		//  get query
		if(!$qu = $this->dml()->GetInsert($conf)){
			return false;
		}
		
		//  execute
		if(!$this->query($qu) === false){
			return false;
		}
		
		//  new id
		$id = $this->pdo->lastInsertId(/* $name */);
			
		return $id;
	}
	
	function Update($conf)
	{
		if(!$this->pdo){
			return false;
		}
		
		//  object to array
		if(!is_array($conf)){
			$conf = Toolbox::toArray($conf);
		}
		
		//  get query
		if(!$qu = $this->dml()->GetUpdate($conf)){
			return false;
		}
		
		//  execute
		$num = $this->query($qu,'update');
		
		return $num;
	}
	
	function Delete( $conf )
	{
		if(!$this->pdo){
			return false;
		}
		
		//  object to array
		if(!is_array($conf)){
			$conf = Toolbox::toArray($conf);
		}
		
		//  get query
		if( $this->qu = $this->dml()->GetDelete($conf)){
			$this->qus[] = $this->qu;
		}else{
			return false;
		}
		
		//  execute
		$num = $this->query($qu,'update');
		
		return $num;
	}
	
	function Transaction()
	{
		$this->pdo->beginTransaction();
	}
	
	function Rollback()
	{
		$this->pdo->rollBack();
	}
	
	function Commit()
	{
		$this->pdo->commit();
	}
}
