<?php

$ticker = $argv[1];

include('db_connect.php');

$sql = "select interval, max(time_index) from price_history_{$ticker} group by interval;";
$results = pgsql_select($sql,$db);
#print_r($result);
sort($results);

$MAXCHILDREN = 20;
foreach($results as $result){

	//run intervals in parallel
	$pid = pcntl_fork();

	if ($pid == -1) die ("Could not fork.");
	if ($pid > 0) {
                echo "Parent: fork, child pid is $pid " . PHP_EOL;
                $CHILDREN[] = $pid;
	}elseif($pid == 0){
		//run child
		$interval = $result['interval'];
        	$starttime = $result['max'];
        	echo "Interval: $interval, Starttime: $starttime".PHP_EOL;

        	$cmd = "php tvc_scraper_db.php $ticker $interval $starttime";
        	echo $cmd . PHP_EOL;
        	system($cmd);		

		//once scraper is done for the interval we can write to output file
		$writecmd = "php chart_price_history_db.php $ticker $interval";
		echo $writecmd . PHP_EOL;
		system($writecmd);	
	
		exit("child complete for interval: $interval");		
	}
	// Collect children and wait
	while (($c = pcntl_wait($status, WNOHANG OR WUNTRACED)) > 0) {
		echo "Collected child - $c" . PHP_EOL;
		remove_thread($CHILDREN, $c);
	}
	if (sizeof($CHILDREN) >= $MAXCHILDREN) {
		echo "Maximum forks reached.  Waiting..." . PHP_EOL;
		if (($c = pcntl_wait($status, WUNTRACED)) >0) {
			echo "Waited for child - $c" . PHP_EOL;
			remove_thread($CHILDREN, $c);
		}
	}

}//end foreach

// Collect all children before we proceed
while (($c = pcntl_wait($status, WUNTRACED)) > 0) {
	echo "Child finished - " . $c . PHP_EOL;
        remove_thread($CHILDREN, $c);
}
echo "scraping complete" . PHP_EOL;

?>
