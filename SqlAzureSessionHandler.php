<?php
class SqlAzureSessionHandler
{
	private $_conn;
	
	public function __construct($serverId, $username, $password)
	{	
		$connOptions = array("UID"=>$username."@".$serverId, "PWD"=>$password, "Database"=>"SessionsDB");
		$this->_conn = sqlsrv_connect("tcp:".$serverId.".database.windows.net", $connOptions);
		if(!$this->_conn)
		{
			die(print_r(sqlsrv_errors()));
		}

		session_set_save_handler(
								 array($this, 'open'),
                                 array($this, 'close'),
                                 array($this, 'read'),
                                 array($this, 'write'),
                                 array($this, 'destroy'),
                                 array($this, 'gc')
								 );
	}

	
	function __destruct()
	{
		session_write_close(); // IMPORTANT!
	}
 
	public function open()
    {
		return true;
    }

    public function close()
    {
        return true;
    }
    
    public function read($id)
    {
		$sql = "SELECT data
				FROM sessions
				WHERE id = ?";
 
		$stmt = sqlsrv_query($this->_conn, $sql, array($id));
		if($stmt === false)
		{
			die(print_r(sqlsrv_errors()));
		}
		
        if (sqlsrv_has_rows($stmt))
		{
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            return unserialize(base64_decode($row['data']));
        }
		
		return '';
    }

    public function write($id, $data)
    {
    	$serializedData = base64_encode(serialize($data));
		$start_time = time();
		$params = array($id, $start_time, $serializedData);
		$sql = "{call UpdateOrInsertSession(?,?,?)}";
		
		$stmt = sqlsrv_query($this->_conn, $sql, $params);
		if($stmt === false)
		{
			die(print_r(sqlsrv_errors()));
		}
		return $stmt;
    }

    public function destroy($id)
    {	
       	$sql = "DELETE FROM sessions
				WHERE id = ?";
 
		$stmt = sqlsrv_query($this->_conn, $sql, array($id));
		if($stmt === false)
		{
			die(print_r(sqlsrv_errors()));
		}
		 
		return $stmt;
    }
    
    public function gc($lifeTime)
    {
       	$sql = "DELETE FROM sessions WHERE start_time < ?";
		$expired = time() - $lifeTime;
		$stmt = sqlsrv_query($this->_conn, $sql, array($expired));
		if($stmt === false)
		{
			die(print_r(sqlsrv_errors()));
		}
		return $stmt;
    }
}
