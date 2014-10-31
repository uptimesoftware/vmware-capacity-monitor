<?php

include('uptimeDB.php');

$months_of_data = getenv('UPTIME_MONTHS_OF_DATA');
$capacity_buffer = getenv('UPTIME_CAPACITY_BUFFER');
$query_type = getenv('UPTIME_QUERY_TYPE');
$metric_type = strtoupper(getenv('UPTIME_METRIC_TYPE'));
$uptime_hostname = getenv('UPTIME_HOSTNAME');

//first we need to setup our db connection
$db = new uptimeDB;
if ($db->connectDB())
{
	echo "";
}
else
{
 echo "unable to connect to DB exiting";	
 exit(3);
}


//now we need to find our vmware_object_id based on the elements hostname
$get_vmware_object_id_sql = 'select vmware_object_id from vmware_object where vmware_name = "' . $uptime_hostname . '"';
$vmware_obj_results = $db->execQuery($get_vmware_object_id_sql);
$vmware_object_id = $vmware_obj_results[0]['VMWARE_OBJECT_ID'];


$usage_array = array();

if ($query_type == 'Cpu')
{


	$sql = "SELECT 
		s.vmware_object_id, 
		o.vmware_name as NAME,
		date(s.sample_time) as SAMPLE_TIME,
		min(a.cpu_usage) as MIN_CPU_USAGE,
		max(a.cpu_usage) as MAX_CPU_USAGE,
		avg(a.cpu_usage) as AVG_CPU_USAGE,
		a.cpu_total as TOTAL_CAPACITY,
		day(s.sample_time), 
		month(s.sample_time), 
		year(s.sample_time) 
	FROM 
		vmware_perf_aggregate a, vmware_perf_sample s, vmware_object o
	WHERE 
		s.sample_id = a.sample_id AND 
		s.vmware_object_id = o.vmware_object_id AND
		s.sample_time > date_sub(now(),interval  ". $months_of_data . " month) AND
		s.vmware_object_id = $vmware_object_id

	GROUP BY 
		s.vmware_object_id,
		year(s.sample_time),
		month(s.sample_time), 
		day(s.sample_time)";

	$hostCpuResults = $db->execQuery($sql);

	$name = $hostCpuResults[0]['NAME'];
	$column_name = $metric_type . '_CPU_USAGE';
	$capacity = intval($hostCpuResults[0]['TOTAL_CAPACITY']);


	foreach ($hostCpuResults as $index => $row) {
		$sample_time = strtotime($row['SAMPLE_TIME']);
		$x = $sample_time * 1000;

		$data = array($x, floatval($row[$column_name]) );
		array_push($usage_array, $data);
	}

}

elseif ($query_type == 'Mem')
{


	$sql = "SELECT 
		s.vmware_object_id, 
		o.vmware_name as NAME,
		date(s.sample_time) as SAMPLE_TIME,
		min(a.memory_usage) as MIN_MEM_USAGE,
		max(a.memory_usage) as MAX_MEM_USAGE,
		avg(a.memory_usage) as AVG_MEM_USAGE,
		a.memory_total		as TOTAL_CAPACITY,
		day(s.sample_time), 
		month(s.sample_time), 
		year(s.sample_time) 
	FROM 
		vmware_perf_aggregate a, vmware_perf_sample s, vmware_object o
	WHERE 
		s.sample_id = a.sample_id AND 
		s.vmware_object_id = o.vmware_object_id AND
		s.sample_time > date_sub(now(),interval  ". $months_of_data . " month) AND
		s.vmware_object_id = $vmware_object_id

	GROUP BY 
		s.vmware_object_id,
		year(s.sample_time),
		month(s.sample_time), 
		day(s.sample_time)";

	$hostCpuResults = $db->execQuery($sql);

	$name = $hostCpuResults[0]['NAME'];
	$column_name = $metric_type . '_MEM_USAGE';
	$capacity = intval($hostCpuResults[0]['TOTAL_CAPACITY']);


	foreach ($hostCpuResults as $index => $row) {
		$sample_time = strtotime($row['SAMPLE_TIME']);
		$x = $sample_time * 1000;

		$data = array($x, floatval($row[$column_name]) );
		array_push($usage_array, $data);
	}

}


//calculate and output stuff

$buffered_capacity = $capacity * floatval( $capacity_buffer / 100);

$xDeltaTotal = 0;
$yDeltaTotal = 0;

$lastIndex = count($usage_array) - 1;

foreach ($usage_array as $index => $row) {
	if ( $index >= 1)
	{
		$prevIndex = $index - 1;
		$xDelta = $row[1] - $usage_array[ $prevIndex ][1];
		$yDelta = $row[0] - $usage_array[ $prevIndex ][0];

		$xDeltaTotal = $xDeltaTotal + $xDelta;
        $yDeltaTotal = $yDeltaTotal + $yDelta;
	}

}

$xDelta = $xDeltaTotal / $lastIndex;
$yDelta = $yDeltaTotal / $lastIndex;

$lastPoint = $usage_array[$lastIndex];

$last_Xvalue = $lastPoint[1];
$last_Yvalue = $lastPoint[0];


$used_cap = $last_Xvalue;
$capacityLeft = $capacity - $used_cap;


//output the total/used/avail of Real Capacity
echo "total_cap " . $capacity . "\n";
echo "used_cap " . $used_cap . "\n";
echo "avail_cap " . $capacityLeft . "\n";
echo "delta_cap " . round($xDelta , 2, PHP_ROUND_HALF_UP) . "\n";

//figure out the time remaining till we hit both Real Capacity
$timeToGo = $capacityLeft / $xDelta;
$timeToGoInMS = $timeToGo * $yDelta;
$actualTime = $timeToGoInMS + $last_Yvalue;

$timeLeft_in_days = timeInMS_to_days($timeToGoInMS);
echo "time_till_real_cap " . $timeLeft_in_days ."\n" ;

$bufferedCapLeft = $buffered_capacity - $used_cap;
$timeToGo = $bufferedCapLeft / $xDelta;
$timeToGoInMS = $timeToGo * $yDelta;
$actualTime = $timeToGoInMS + $last_Yvalue;

$timeLeft_in_days = timeInMS_to_days($timeToGoInMS);
echo "time_till_buffered_cap "  . $timeLeft_in_days . "\n";

function timeInMS_to_days( $timeInMS)
{
	$seconds = floor($timeInMS / 1000);
	$minutes = floor($seconds / 60);
	$hours = floor($minutes / 60);
	$days = floor($hours / 24);
	$months = floor($days / 30);
	return $days;
}
?>
