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
		if(!isset($this->dml)){
			if(!class_exists('DML5',false)){
				include_once('PDO/DML5.class.php');
			}			
			$conf['driver'] = $this->driver;
			$this->dml = new DML5( $conf, $this->pdo );
		}
		return $this->dml;
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

	function Query( $qu, $key=null )
	{
		$this->qu($qu);
				
		if( $st = $this->pdo->query($this->qu) ){
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
							$this->mark( $this->Qu() );
							$this->d($return);
							$return = false;
						}
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
	
	function Quick( $string, $config=null)
	{
		list( $left, $value ) = explode('=', trim($string) );
		
		if( strpos( $left, '<') ){
			list( $column, $location ) = explode('<-', trim($left) );
		}else{
			$location = $left;
			$column = null;
		}
		//$this->mark($string);
		//$this->mark("column=$column, location=$location, value=$value");
		
		//  generate define
		$locations = array_reverse( explode('.', trim($location) ) );
		$target   = isset($locations[0]) ? $locations[0]: null;
		$table    = isset($locations[1]) ? $locations[1]: null;
		$database = isset($locations[2]) ? $locations[2]: null;
		$host     = isset($locations[3]) ? $locations[3]: null;
		
		//  create columns
		$columns = explode(',',str_replace(' ', '', $column));
		
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
		$config->where->$target = $value;
		
		//  get record
		$record = $this->Select($config);
		if( $record === false ){
			$this->qu('Quick-Select is failed');
			return false;
		}
		
		//  return all
		if( !$column or $limit != 1 ){
			return $record;
		}
		
		//  return one
		if( count($columns) === 1 ){
			return isset($record[$columns[0]]) ? $record[$columns[0]]: null;
		}
		
		//  return many
		foreach( $record as $key => $var ){
			$return[$key] = $var; 
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
		if( $st = $this->pdo->query($qu) ){
			//  num rows
			$num = $st->rowCount();
		}else{
			//  failed
			$num = false;
			$temp = $this->pdo->errorInfo();
			$this->StackError("{$temp[2]} : {$this->qu}");
		}
		
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
		if( $st = $this->pdo->query($this->qu) ){
			//  num rows
			$num = $st->rowCount();
		}else{
			//  failed
			$num = false;
			$temp = $this->pdo->errorInfo();
			$this->StackError("{$temp[2]} : {$this->qu}");
		}
		
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
