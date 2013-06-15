<?

require_once ('aws-sdk-for-php/sdk.class.php');
/*
 * Login 
 */ 

$elasticComputeCloud = new AmazonEC2(array(
    'key' => '',
    'secret' => '',
));

/*
 * Get instances
 */

$reservations = $elasticComputeCloud->describe_instances();
/*
 * Print all instances
 */

foreach ($reservations->body->reservationSet->item as $key => $instances) 
{
    foreach ($instances->instancesSet->item as $instance) 
    {
        /*
         * Get instance details
         */

        $n = array ('Name' => '',);
        $id = "{$instance->instanceId}";

        if ($instance->tagSet->item)
        {
            foreach ($instance->tagSet->item as $key => $item)
            {
                $n["{$item->key}"] = "{$item->value}";
            }
        }

        /* 
         * Print image id
         */

        $name = $n['Name'] == '' ? "[{$instance->imageId}]" : $n['Name'];
        printf 
        (
            "%-20s %10s(%2s) %s %s %15s\n", 
            $name, 
            $instance->instanceState->name, 
            $instance->instanceState->code, 
            $instances->reservationId, 
            $id, 
            $instance->privateIpAddress 
        );
	}
}
/*
 * Input from user
 */ 
fwrite(STDOUT, "What instance you want to stop?: ");
$name = trim(fgets(STDIN));
     
/*
 * Stop it
 */ 
$response = $elasticComputeCloud->stop_instances($name);
/*
 * Detach Volume 
 */ 

$volume = $elasticComputeCloud->describe_instance_attribute($name, 'blockDeviceMapping');
$ebs_count = count($volume->body->blockDeviceMapping->item);
$i = 0;
while ($i < $ebs_count) {
	$array  = $volume->body->blockDeviceMapping->item[$i]->ebs->volumeId;
	echo "$array \n";
	$detaching = $elasticComputeCloud->detach_volume($array);

	/*
	 * Snapshot 
	 */ 
	$snapping= $elasticComputeCloud->create_snapshot($array, array(
    		//'Description' => $array 
	));

	/*
	 * Terminate 
	 */ 
	$terminating = $elasticComputeCloud->terminate_instances($name);
	$i++;
}

$instType = $elasticComputeCloud->describe_instance_attribute($name, 'instanceType');
$instanceType = $instType->body->instanceType->value->to_array();
//$devMap = $elasticComputeCloud->describe_instance_attribute($name, 'blockDeviceMapping');
$deviceMapping = $volume->body->blockDeviceMapping->item->ebs->volumeId->to_array();

print_r($deviceMapping);
/*
 * Creo una nuova instanza da stoppare per attaccare i device vecchi
 */
$response = $elasticComputeCloud->run_instances('ami-d0f89fb9', 1, 1, array(
                'InstanceType' => $instanceType[0],
                'BlockDeviceMapping' => array (
			'DeviceName'  => '/dev/sda1',
			'Ebs'	      => array (
				'VolumeSize' => '8'
			)	
        )));
/*
 * Stop the instance just created
 */
$new_inst_id = $response->body->instancesSet->item->instanceId->to_array();
print_r($new_inst_id);
sleep (60);
$stop_new = $elasticComputeCloud->stop_instances($new_inst_id[0]);
print_r($stop_new);

/*
 * Detach 
 */ 
$volume = $elasticComputeCloud->describe_instance_attribute($new_inst_id, 'blockDeviceMapping');
$ebs_count = count($volume->body->blockDeviceMapping->item);

$i = 0;
while ($i < $ebs_count) {
	$array  = $volume->body->blockDeviceMapping->item[$i]->ebs->volumeId;
	$detaching = $elasticComputeCloud->detach_volume($array);
	$i++;
print_r($detaching);
}


?>
