<?php

include('db_connect.php');
//ES for now, we can turn this into a parameter
if(isset($argv[1])){
        $symbol = $argv[1];
}else{
	exit("Missing argument 1: ticker/symbol");
}

if(isset($argv[2])){
	$custom_resolution = $argv[2];
}else{
	exit("Missing argument 2: interval/resolution");
}

if(isset($argv[3])){
        $custom_starttime = $argv[3];
}else{
        exit("Missing argument 3: custom_starttime");
}

if(isset($argv[4])){
        $custom_endtime = $argv[4];
}else{
        $custom_endtime = null;
}

$data_array = scrape_data($symbol, $custom_resolution, $custom_starttime, $custom_endtime);
//print_r($data_array['data']);
$actual_starttime = $data_array['actual_starttime'];
$intended_endtime = $data_array['intended_endtime'];
echo "ACTUAL STARTTIME: " . $actual_starttime . PHP_EOL;
echo "INTENDED ENDTIME: " . $intended_endtime . PHP_EOL;
write_output_sql($symbol,$custom_resolution,$data_array['data'],$actual_starttime,$intended_endtime);

//if there is no new data then we are done, otherwise keep scraping until all data is obtained
if($data_array['latest'] != ''){
	$continuation_starttime = $data_array['latest'];
	//if there's more stuff, then loop
	$data_array_1 = array();
	$data_array_1['data'] = "";
	$data_array_1['latest'] = "";
	$x = 2;
	while(true){
		${'data_array_' . $x} = scrape_data($symbol, $custom_resolution, $continuation_starttime, $custom_endtime);
		echo "ITERATION $x " . PHP_EOL;
		if(${'data_array_' . $x}['data'] != ''){
			write_output_sql($symbol,$custom_resolution,${'data_array_' . $x}['data'],$actual_starttime,$intended_endtime);
		}
		if(${'data_array_' . $x}['latest'] != '' && ${'data_array_' . $x}['latest'] != ${'data_array_' . ($x-1)}['latest']){
			$continuation_starttime = ${'data_array_' . $x}['latest'];
			echo "CONTINUATION STARTTIME: " . $continuation_starttime . PHP_EOL;
			$previous_continuation_starttime = ${'data_array_' . ($x-1)}['latest'];
			echo "PREV CONTINUATION STARTTIME: " . $previous_continuation_starttime . PHP_EOL;			    
		}else{
                        if(${'data_array_' . $x}['latest'] == ${'data_array_' . ($x-1)}['latest']){
                                echo "CONTINUATION STARTTIME IS SAME AS LAST ITERATION, NO MORE DATA" . PHP_EOL;
                        }
			break;
		}
		$x++;
	}//end while
}//end main if

echo PHP_EOL . "DONE";


//functions
//this function returns a $clean_data array which can be used to write to a file
function scrape_data($custom_symbol, $custom_resolution, $custom_starttime, $custom_endtime){
	#cdn snapshot scraper
	$url = "http://tvc.forexpros.com";
	$ch = curl_init();
	$timeout = 5;
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
	$html = curl_exec($ch);
	curl_close($ch);

	# Create a DOM parser object
	$dom = new DOMDocument();

	# Parse the HTML from Google.
	# The @ before the method call suppresses any warnings that
	# loadHTML might throw because of invalid HTML in the page.
	@$dom->loadHTML($html);

	# Iterate over all the <a> tags
	foreach($dom->getElementsByTagName('iframe') as $link) {
			# Show the <a href>
			$cdn_link = $link->getAttribute('src');
	}

	echo "SOURCE URL: " . $cdn_link . PHP_EOL;
	$cdn_link_arr = parse_url($cdn_link);
	//print_r($cdn_link_arr);

	echo PHP_EOL;
	//custom carrier modification
	$get_carrier_string = $cdn_link_arr['query'];
	parse_str($get_carrier_string, $get_carrier_array);
	//print_r($get_carrier_array);

	//STORE PARAMS FROM ORIGINAL URL
	$carrier = $get_carrier_array['carrier'];
	$endtime = $get_carrier_array['time'];
	echo "URL TIME IS: $endtime" . PHP_EOL;
	//1209600 seconds in two weeks
	//$starttime = $endtime - 480 * 60 * 60;
	//as of 1531073041 7/8, the data goes back to 6/25 (6/24 21:59:00)
	//$starttime = $endtime - 1209600;
	//$starttime = "1529884800";
	//num of seconds in 24 hours 24*60*60
	//$starttime = $endtime - 72*60*60;
	//$starttime = "1529884800";
	//1997-09-01
	//default starttime if none given
	$starttime = "873072000";
	//$starttime = "1530835200";
	//$starttime = "1530835200";

	//MODIFY PARAMS in the carrier_array for rebuild, e.g. pair_ID, interval, time
	if($custom_symbol != ''){
		$get_carrier_array['pair_ID'] = $custom_symbol;
	}
	if($custom_resolution != ''){
		$get_carrier_array['interval'] = "$custom_resolution";
	}
	//default starttime is 873072000
	if($custom_starttime != ''){
		$starttime = $custom_starttime;
	}
	//default endtime is the time the carrier was scraped from the page (close to now())
	if($custom_endtime != ''){
		$endtime = $custom_endtime;
	}
	//END MODIFY PARAMS

	//STORE MODIFIED PARAMS
	$symbol = $get_carrier_array['pair_ID'];
	$resolution = $get_carrier_array['interval'];

	echo PHP_EOL;
	//CONSTRUCT MODIFIED URL
	//cdn_link_arr example
	/*
	[scheme] => http
	[host] => tvc-invdn-com.akamaized.net
	[path] => /web/1.12.21/index58-prod.html
	*/
	$url_prefix = $cdn_link_arr['scheme'] . '://' . $cdn_link_arr['host'] . $cdn_link_arr['path'];
	$url_suffix = http_build_query($get_carrier_array);

	$working_url = $url_prefix . "?" . $url_suffix;
	echo "MODIFIED URL: " . $url_prefix . "?" . $url_suffix . PHP_EOL;

	echo PHP_EOL;
	//CONSTRUCT DATA URL
	$datafeed_url = "http://tvc4.forexpros.com/" . $carrier . "/" . $endtime . "/1/1/8/history?symbol=" . $symbol . "&resolution=" . $resolution . "&from=" . $starttime . "&to=" . $endtime;

	echo "DATAFEED URL: " . $datafeed_url . PHP_EOL; 

	//GET DATA
	$url = $datafeed_url;
	$ch = curl_init();
	$timeout = 5;
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
	$html = curl_exec($ch);
	curl_close($ch);

	//convert json to assoc array (true flag required in json_decode())
	$json_data = json_decode($html,true);
	//print_r($json_data);
	//example prints
	//t = time index
	/*
	echo "TIME: " . $json_data['t']['0'] . PHP_EOL;
	//o = Open
	echo "OPEN: " . $json_data['o']['0'] . PHP_EOL;
	//h = High
	echo "HIGH: " . $json_data['h']['0'] . PHP_EOL;
	//l = Low
	echo "LOW: " . $json_data['l']['0'] . PHP_EOL;
	//c = Close
	echo "CLOSE: " . $json_data['c']['0'] . PHP_EOL;
	*/
	//v = volume. no data for ES
	//vo = volume option? no data
	//s = status. status msg for pull
	//unset the ones that are empty
	if($json_data['s'] == "no_data"){
		exit("NO DATA " . PHP_EOL);
	}
	unset($json_data['v']);
	unset($json_data['vo']);
	unset($json_data['s']);

	//let's convert this data into an array where the time value is the array index
	//example
	//data['1530835200']['o'] is open price for time 1530835200
	$clean_data = array();
	foreach($json_data as $key => $value_arr){
		echo "KEY IS: " . $key . PHP_EOL;
		if($key == "t"){
			//set up timeindex array keys
			foreach ($value_arr as $value) {
                                $clean_data[$value] = array();
			}
			$clone_clean_data = $clean_data;
			$clone_clean_data2 = $clean_data;
		}else{
		//for non-time arrays, place them into the $clean_data array. Since we're iterating over the t, c, o, h, l keys in this order, the t array will be created in time for the other arrays
			$i = 0;
			foreach($clean_data as $timeindex => $emptyvalue){
				//if($value_arr[$i] != ''){
					$clean_data[$timeindex][$key] = $value_arr[$i];
				//}else{
					//remove all empty timeindex
				//	unset($clean_data[$timeindex]);
				//}
				$i++;
			}

		}
		//print_r($value_arr);
	}

	echo PHP_EOL;
	//print_r($clean_data);
	//print_r($clone_clean_data);
	echo PHP_EOL;
	reset($clone_clean_data);
	end($clone_clean_data2);
	$earliest = key($clone_clean_data);
	$latest = key($clone_clean_data2);
	echo "EARLIEST timestamp: " . $earliest . " vs STARTTIME: " . $starttime . PHP_EOL;
	echo "LATEST timestamp: " . $latest . " vs ENDTIME: " . $endtime . PHP_EOL;
	//prepare output array
	$output = array();
	$output['data'] = $clean_data;
	$output['actual_starttime'] = $earliest;
	$output['intended_endtime'] = $endtime;
	if($endtime > ($latest + 60)){
		//trigger another run that starts with $earliest and attempts $endtime
		echo "ENDTIME wanted is later than the latest timestamp. We need to run another batch with latest as the custom starttime" . PHP_EOL; 	
		$output['latest'] = $latest;
	}else{
		$output['latest'] = null;
	}
		
	return $output;

}//end function

function write_output_sql($symbol,$resolution,$clean_data,$starttime,$endtime){
        //output to postgres db for storage
        //print_r($clean_data);
	if($clean_data != ''){
		
		$ticker = $symbol;
		$interval = $resolution;
		$query = "create table if not exists import_{$ticker}_{$interval} (like import_8839_{$interval} including all);";
		pgsql_exec($query);
		#$query = "create table if not exists price_history_{$ticker} (like price_history_8839);";
		#pgsql_exec($query);

		//empty the import table before adding new data
		$query = "truncate table import_{$ticker}_{$interval};";
                pgsql_exec($query); 		

		foreach($clean_data as $timeindex => $values){
                //      echo "TIME : " . $timeindex . " O: " . $values['o'] . ", H: " . $values['h'] . ", L: " . $values['l'] . ", C: " .  $values['c'] .  PHP_EOL;
                        $rowstring = $timeindex . "," . $values['o'] . "," . $values['h'] . "," . $values['l'] . "," . $values['c'] . PHP_EOL;
			//insert row into import table
			$query = "insert into import_{$ticker}_{$interval} (time_index, open, high, low, close) select $timeindex, ${values['o']}, ${values['h']}, ${values['l']}, ${values['c']}";
                        pgsql_exec($query);
                }
	
		//insert the interval's data from the import table to the final table
		$query = "insert into price_history_{$ticker} (ticker, interval, time_index, open, high, low, close) select $ticker, '$interval', time_index, open, high, low, close from import_{$ticker}_{$interval} on conflict do nothing;";
		pgsql_exec($query);

		//update linked list time index columns
		$cmd = "php update_linked_list.php $ticker $interval";
		echo $cmd . PHP_EOL;
		system($cmd);
	}
}//end function


?>
