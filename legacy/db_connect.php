<?php
 
$host        = "host=127.0.0.1";
$port        = "port=5432";
$dbname      = "dbname = dojidoge";
$credentials = "user = postgres password=aaa";

$db = pg_connect( "$host $port $dbname $credentials"  );

/*
$sql = "select * from price_history limit 5;";
$result = pgsql_select$sql,$db);
print_r($result);
*/

/*
pg_close($db);
*/

function pgsql_exec($sql,$db=false){
	if(!$db){
                global $db;
        }
	echo $sql . PHP_EOL;
        if(!$db) {
                exit("Error : Unable to open database\n");
        } else {
                //echo "Opened database successfully\n";
        }

	$resource = pg_query($db,$sql);
        if(!$resource){
                echo pg_last_error($db);
                exit("query could not execute\n");
        }else{
		//echo "query executed successfully".PHP_EOL;
	}

}

function pgsql_select($sql,$db=false){
	
	if(!$db){
		global $db;
	}

	if(!$db) {
        	echo "Error : Unable to open database\n";
	} else {
        	echo "Opened database successfully\n";
	}	

	$resource = pg_query($db,$sql);
	if(!$resource){
		echo pg_last_error($db);
		exit;
	}

	$result_array = array();
        while( $result = pg_fetch_assoc($resource)){
		$result_array[] = $result;
	}
	return $result_array;

}

function remove_thread(&$Array, $Element) {
	for ($i = 0; $i < sizeof($Array); $i++) {
		// Found the element to remove
		if ($Array[$i] == $Element){
			unset($Array[$i]);
			$Array = array_values($Array);
			break;
		}
	}
}
 
?>
