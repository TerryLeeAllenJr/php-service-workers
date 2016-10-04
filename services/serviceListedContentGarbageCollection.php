<?php
require __DIR__.'/vendor/autoload.php';

/**
 * This worker is used to remove any old content from our redis listing.
 */

try{

    // Set defaults and Parse the CLI input.
    $serviceID = null;
    $monitor = false;

    foreach ($argv AS $arg => $value) {
        if ($arg == 0 ) {continue;}
        $params = explode('=',$value);
        switch ($params[0]){
            case 'serviceID':
                $serviceID = $params[1];
                break;
            case 'monitor':
                $monitor = ($params[1] == 'true') ? true : false;
        }
    }

    if ( !$serviceID ) { throw new Exception("No server id set. Aborting worker."); }

    // Initialize classes.
    $service = new \Service\Intake( $serviceID );
    $pid = $service->lock( $serviceID );
    if($pid) {
        if($monitor){ echo "serviceDaemon.php has started.\nServer: $serverID\nPID: $pid\n";}
        // Set the kill timer and update all status info.
        $killTimer = rand( 60*60*1 , (60*60)+(60*4) );
        $timeElapsed = 0;
        $timeStarted = time();

        $service->predis->hset( 'services.status', $serviceID, 'Processing');     // Set the daemon status.
        $service->predis->hset( 'services.status.timestamp', $serviceID, time() );
        $service->predis->hset( 'services.status.killTimer', $serviceID, $killTimer );

        while( $timeElapsed <= $killTimer ) {
            $timeElapsed = time() - $timeStarted;   // Determine how long the script has been running.
            $service->removeEditorialContentFromRedis();
            sleep(5);
        }
        $service->unlock( $serviceID );
    }else{
        if($monitor){echo "serviceListContent is already running on $serviceID\n";}
    }
    exit;
}catch (Exception $e) {
    echo "\n".$e->getMessage()."\n";
    $service = new \Service\Service();
    $service->handleError($service->path.'log/error-'.date('Y-m-d',time()).'.log',$e);
}