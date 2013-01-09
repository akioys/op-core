<?php


class DDL extends OnePiece5
{
	private $pdo = null;
	private $driver = null;
	
	function SetPDO( $pdo )
	{
		$this->pdo = $pdo;
		$this->driver = 'mysql';
	}
	
	function GetCreateDatabase( $args )
	{
		//	IF NOT EXIST
		$if_not_exist = 'IF NOT EXISTS';
		
		//	データベース名
		$database = PDO5::Quote($args['database'], $this->driver );
		
		//	COLLATE
		if( isset($args['collate']) ){
			$collate = 'COLLATE '.$args['collate'];
		}else{
			//	default
			$collate = 'COLLATE utf8_general_ci';
		}
		
		//	CHARACTER SET
		if( isset($args['character']) ){
			$character = 'CHARACTER SET '.$args['character'];
		}else{
			//	default
			if(	$collate  == 'COLLATE utf8_general_ci'){
				$character = 'CHARACTER SET utf8';
			}else{
				$character = '';
			}
		}
		
		//	文字コードの設定があれば（必ずある）
		$default = 'DEFAULT';
		
		//	queryの作成
		$query = "CREATE DATABASE {$if_not_exist} {$database} {$default} {$character} {$collate}";
		
		return $query;
	}
	
}
