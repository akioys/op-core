<?php
/**
 * 
 * 
 * @author Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 *
 */
class Model_File extends Model_Model
{
	function Get( $path='./' )
	{
		$path = rtrim( $path, '/' ) . '/';
		$dir = opendir($path);
		while( $name = readdir($dir) ){
			
			$pos = strpos( $name, '.');
			if( $pos === 0 ){
				continue;
			}
			
			$is_dir = is_dir($path.$name);
			$ext = !$is_dir ? array_pop(explode('.',$name)) : null;
			
			$file = array();
			$file['dir']  = $is_dir;
			$file['name'] = $name;
			$file['ext']  = $ext;
			
			$files[] = $file;
		}
		
		return $files;
	}
}
