<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

define('BACKUP_DIR', './system/database_backup/');
define('TOTAL_FILES_TO_RETAIN', 10);

class DBBackup  {
		
		protected $host;
		protected $db_name;
		protected $username;
		protected $password;
		protected $interval_option = "days";
		protected $interval_option_length = 1; //backup every $interval scheduled for example for 2 hour as a default
		protected $table = 'db_backup';
		private $compression = TRUE;
		public $mysqli;
		protected $optimize_interval = '28 days';
		





	function __construct()
	{

		//run table script
		
		$this->load->helper('file');

		$this->load->dbutil();
		$this->create_sql();

		if (!file_exists(BACKUP_DIR)) {
		    mkdir(BACKUP_DIR, 0777, true);
		}

		$this->mysqli = new mysqli();
		

	}


		/**
	 * __get
	 *
	 * Enables the use of CI super-global without having to define an extra variable.
	 *
	 * I can't remember where I first saw this, so thank you if you are the original author. -Militis
	 *
	 * @access	public
	 * @param	$var
	 * @return	mixed
	 */
	public function __get($var)
	{
		return get_instance()->$var;
	}







	function set_host($host)
	{	
		$this->host = $host;

	}


	function set_db_name($db_name)
	{	
		$this->db_name = $db_name;

	}	


	function set_username($username)
	{	
		$this->username = $username;

	}			


	function set_password($password)
	{	
		$this->password = $password;

	}	




	
	function set_compression($compression)
	{	
		$this->host = $host;

	}	

	public function set_interval_option($interval_option)
	{
		$this->interval_option = $interval_option;
	}

	function set_interval_option_length($interval_option_length)
	{
		$this->interval_option_length = $interval_option_length;
	}



	public function get_summary()
	{
		$summary_setting = array('host' => $this->host , 'username' => $this->username, 'password' => $this->password, 'database' => $this->db_name,
							'table' => $this->table, 'compression' => $this->compression, 'interval_option'=> $this->interval_option,
							'interval_option_length' => $this->interval_option_length );
		return $summary_setting;
	}


	public function backupDB()
	{
		$this->optimize_database();

		$lastRecord = $this->getLastRecord();

		if($lastRecord->id != NULL)
		{
			$lastRecord->timestamp;
			$total_interval = $this->countTotalInterval($lastRecord->timestamp);

			//backup if time more than interval total 
			if($total_interval > $this->interval_option_length)
			{
				$this->_makeBackeUpDB();

				
				$total_available_files = $this->countRecord();
				if($total_available_files >= TOTAL_FILES_TO_RETAIN + 1)
				{
					$firstRecord = $this->getFirstRecord();
					$firstRecord->id;

					//delete file first then delete record
					$file_to_delete = $firstRecord->timestamp.'.gz';
					$this->delete_file(BACKUP_DIR.$file_to_delete);
					$this->_delete($firstRecord->id);


				}
			}





		}
		else {
			return $this->_makeBackeUpDB();
		}
	

		
		
			 
				
	}


	protected function _makeBackeUpDB()
	{
		ini_set('memory_limit', '256M');
		set_time_limit(0);
		ignore_user_abort(true);

		$timestamp_now = time();
			
		// Backup your entire database and assign it to a variable
		$backup =& $this->dbutil->backup(); 
		
		$backup_report =  write_file(BACKUP_DIR.$timestamp_now.'.gz', $backup);

		 //save last backup history
		 $form_data = array(					
					'timestamp' => $timestamp_now, 
					'type' => 'backup',					
					);		
		
		return $this->_save($form_data);

		
		//return $backup_report;
	}



	public function optimize_database()
	{

		ini_set('memory_limit', '256M');
		set_time_limit(0);
		ignore_user_abort(true);

		$n = explode(" ", $this->optimize_interval);
		$optimizedInterval = $n[0];

		$lastRecord = $this->getLastRecord('optimize');

		if($lastRecord->id == NULL) //first optimize
		{
			$this->dbutil->optimize_database();

			$form_data = array(					
					'timestamp' => time(), 
					'type' => 'optimize',					
					);		
			
			return $this->_save($form_data);

		}

		else {
			$total_interval = $this->countTotalInterval($lastRecord->timestamp);

			//backup if time more than interval total 
			if($total_interval > $optimizedInterval)
			{

				$this->dbutil->optimize_database();

				$form_data = array('timestamp' => time());

				
				return $this->_update($form_data, $lastRecord->id);


			}


		}

		

	}



	
	function manual_backup()
	{
		$now = date('Y-m-d');
			
		// Backup your entire database and assign it to a variable
		 $backup =& $this->dbutil->backup(); 

		// Load the file helper and write the file to your server
		 $this->load->helper('file');
			 
			 
					
		$this->load->helper('download');
		force_download($now.'_manual.gz', $backup);
					
		
	}
	
	
	function manual_optimize()
	{
		
	
		return  $this->dbutil->optimize_database();
	
	
	}
	
	
	
	




	public function countTotalInterval($timestamp1)
	{
		/*$d1 = new DateTime();
		
		$d2 = new DateTime();
		
		$diff = $d1->diff($d2);*/

		//echo $diff->format("%y years, %m months, %d days, %h hours, %m minutes, %s seconds");
		//echo "<br>";
		

		$interval_option = $this->interval_option;

		$date1 = new DateTime();
		$date1->SetTimestamp($timestamp1);
		$date2 = new DateTime();

		$diff = $date2->diff($date1);

		$years = $diff->y;
		$month = $diff->m;
		$days = $diff->d;
		$hours = $diff->h;
		$minutes = $diff->i;
		$seconds = $diff->s;

		$sum_of_month = $month + ($years * 12);
		$sum_of_days = $days + ($sum_of_month * 30);
		$sum_of_hours = $hours + ($sum_of_days * 24);
		$sum_of_minutes = $minutes + ($sum_of_hours * 60);
		$sum_of_seconds = $seconds + ($sum_of_minutes * 60);
		
		
		switch ($interval_option) {
				case 'days':
		       return  $sum_of_days;
		        break;
		    case 'hours':
		       return  $sum_of_hours;
		        break;
		    case 'minutes':
		        return  $sum_of_minutes;
		        break;
		    case 'seconds':
		        return  $sum_of_seconds;
		        break;
		}
	}







	protected function countBackupRecord()
	{
		$sql = "SELECT count(id) as jumlah from $this->table where type = 'backup'";
		return $this->_get_query($sql);

	}





	protected function create_sql()
	{
	
		if (!$this->db->table_exists($this->table))
		{
		    $time = time();			
			$sql = "CREATE TABLE IF NOT EXISTS `".$this->table."` (
						`id` INT(11) NOT NULL AUTO_INCREMENT,
						`timestamp` VARCHAR(200) NOT NULL DEFAULT '0',
						`type` VARCHAR(50) NOT NULL DEFAULT '0',
						`time_log` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
						PRIMARY KEY (`id`)
					);";

			
					
	
		$this->db->query($sql);
		$this->optimize_database();
			
		} 

	

	}



	public function getLastRecord($type='backup')
	{
		$type = ($type == 'backup') ? 'backup' : 'optimize';
		$sql = 'select id, timestamp from db_backup where type = "'.$type.'"  order by id desc limit 1';

		$return =  $this->_get_query($sql);

		return $return; 


	}

	protected function getFirstRecord($type='backup')
	{
		$type = ($type == 'backup') ? 'backup' : 'optimize';
		$sql = 'select id, timestamp from db_backup where type = "'.$type.'" limit 1';

		$return =  $this->_get_query($sql);

		return $return; 


	}



	protected function countRecord($type='backup')
	{
		$type = ($type == 'backup') ? 'backup' : 'optimize';
		$sql = 'select count(id) as jumlah from db_backup where type = "'.$type.'"';

		$return =  $this->_get_query($sql);

		if($return)
		{
			$return  =  $return->jumlah;
		}

		return $return;
	}

	protected function checkMaxRecord($max_backup)
	{
		$max_backup = $this->max_backup;
	}






// ==================================================================
//
// DB manipulation related
//
// ------------------------------------------------------------------

	private function _get($table=0)
	{
		$table || $table = $this->table;
		$query = $this->db->get($table);
		if ($query->num_rows() > 0)
		{
			return $query;
		}
			return FALSE;

	}


	private function _get_query($sql)
	{
		 $query = $this->db->query($sql);
		if ($query->num_rows() > 0)
		{
			if($query->num_rows == 1) {
				return $query->row();	
			} 
			else {
				return $query;
			}
		}
			return FALSE;
		
	}

	private function _get_querys($sql)
	{
		 $query = $this->db->query($sql);
		if ($query->num_rows() > 0)
		{
			return $query;
		}
			return FALSE;
		
	}



	private function _save($data, $table=0)
	{
		$table || $table = $this->table;

		 $this->db->insert($table, $data);

		if ($this->db->affected_rows() >= '1')
		{
			
			return TRUE;
			
		} 

		
		return FALSE; // FALSE = "";
		
	}

	private function _update($form_data, $id)
	{
		
		$this->db->where('id', $id);
		$query = $this->db->update($this->table, $form_data); 


		if ($this->db->affected_rows() >= '1')
		{
			
			return TRUE;
		
		}
		
		return FALSE; // FALSE = "";
		
	}
	

	private function _delete($id=0,$table=FALSE)
	{
		$table || $table = $this->table;

		 $this->db->where('id', $id);
		 $this->db->delete($table);

		if ($this->db->affected_rows() >= '1')
		{
		
			return TRUE;
	
		} 

		
		return FALSE; // FALSE = "";
		
	}





	//MYSQLI METHOD ********************************* NOT FINISHED YET



	function connect()
	{
		$mysqli = $this->mysqli;
		$mysqli->connect($this->host, $this->username, $this->password, $this->db_name);
	    if ($mysqli->connect_error) {
	        return FALSE;
	    }

	    return TRUE;


	}




	function closeConnection()
	{
		$mysqli = $this->mysqli;
		$mysqli->connect($this->host, $this->username, $this->password, $this->db_name);
	    if ($mysqli->connect_error) {
	        return FALSE;
	    }

	    return TRUE;

	}

	public function optimize_mysql_database() {
		 
		 $mysqli = $this->mysqli;
		 $this->connect();

		 $resultSet = $mysqli->query("SHOW TABLES");
		 while ($eachTable = $resultSet->fetch_row() ) 
		 { 
		 		$mysqli->query("OPTIMIZE TABLE ".$eachTable[0]);
		 } 

		 return TRUE;
	}


	function backup_mysql_database($optimize = TRUE)
	{
	    $mtables = array();  $params = array();

	    $time_log = date('d-m-Y--h-i-s');


	    $contents = "-- Time: `".$time_log."` --\n";
	    $contents .= "-- Database: `".$this->db_name."` --\n";
	   
	    $mysqli = $this->mysqli;
	  	  	
	  	if($optimize)
	  	{
	  		$this->optimize_database();
	  	}

	  	$this->connect();
	   
	    $results = $mysqli->query("SHOW TABLES");
	  
	   while($row = $results->fetch_array()){
	       
	            $mtables[] = $row[0];
	       
    	}
	  

	    foreach($mtables as $table){
	        $contents .= "-- Table `".$table."` --\n";
	       
	        $results = $mysqli->query("SHOW CREATE TABLE ".$table);
	        while($row = $results->fetch_array()){
	            $contents .= $row[1].";\n\n";
	        }

	        $results = $mysqli->query("SELECT * FROM ".$table);
	        $row_count = $results->num_rows;
	        $fields = $results->fetch_fields();
	        $fields_count = count($fields);
	       
	        $insert_head = "INSERT INTO `".$table."` (";
	        for($i=0; $i < $fields_count; $i++){
	            $insert_head  .= "`".$fields[$i]->name."`";
	                if($i < $fields_count-1){
	                        $insert_head  .= ', ';
	                    }
	        }
	        $insert_head .=  ")";
	        $insert_head .= " VALUES\n";       
	               
	        if($row_count>0){
	            $r = 0;
	            while($row = $results->fetch_array()){
	                if(($r % 400)  == 0){
	                    $contents .= $insert_head;
	                }
	                $contents .= "(";
	                for($i=0; $i < $fields_count; $i++){
	                    $row_content =  str_replace("\n","\\n",$mysqli->real_escape_string($row[$i]));
	                   
	                    switch($fields[$i]->type){
	                        case 8: case 3:
	                            $contents .=  $row_content;
	                            break;
	                        default:
	                            $contents .= "'". $row_content ."'";
	                    }
	                    if($i < $fields_count-1){
	                            $contents  .= ', ';
	                        }
	                }
	                if(($r+1) == $row_count || ($r % 400) == 399){
	                    $contents .= ");\n\n";
	                }else{
	                    $contents .= "),\n";
	                }
	                $r++;
	            }
	        }
	    }
	   
	 
	    $backup_file_name = "".time()."";
	         
	    $fp = fopen(BACKUP_DIR.$backup_file_name ,'w+');

	 	$result = fwrite($fp, $contents);


	    if($this->gzCompressFile(BACKUP_DIR.$backup_file_name));
	    

	    fclose($fp);
	    $this->delete_file(BACKUP_DIR.$backup_file_name);

	}


	protected function delete_file($file_complete_dir)
	{
		$fp = fopen($file_complete_dir ,'w+');
		fclose($fp);
		if (file_exists($file_complete_dir)) {
       		return  unlink($file_complete_dir);
   		 }
		
	}


	protected function gzCompressFile($source, $level = 9){ 
		    $dest = $source . '.gz'; 
		    $mode = 'wb' . $level; 
		    $error = false; 
		    if ($fp_out = gzopen($dest, $mode)) { 
		        if ($fp_in = fopen($source,'rb')) { 
		            while (!feof($fp_in)) 
		                gzwrite($fp_out, fread($fp_in, 1024 * 512)); 
		            fclose($fp_in); 
		        } else {
		            $error = true; 
		        }
		        gzclose($fp_out); 
		    } else {
		        $error = true; 
		    }
		    if ($error)
		        return false; 
		    else
		        return TRUE; 
	}



	//********************END MYSQLI METHOD 

}

/* End of file Template.php */
/* Location: ./system/application/libraries/Template.php */