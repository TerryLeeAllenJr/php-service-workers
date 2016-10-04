<?php
require __DIR__.'/vendor/autoload.php';

/**
 * This script is an example of calling a worker script and can be instantiated multiple times. This script is used
 * to parse incoming editorial wires, record the new file into our file list, and move the file from the /temp directory
 * to a location available to our clients.
 * ex. * * * * * (php /var/services/serviceIntake.php content=scripts serviceID=Intake-Scripts > /dev/null 1>&1)
 * ex. * * * * (php /var/services/serviceIntake.php content=outlooks serviceID=Intake-Outlooks > /dev/null 2>&1)
 * ex. * * * * (php /var/services/serviceIntake.php content=advisories serviceID=Intake-Advisories > /dev/null 2>&1)
 *
 * To view these script in action:
 *   php /var/services/serviceIntake.php content=[CONTENT] serviceID=[SERVICE_ID] monitor=true
 */
$contentTypes = array('scripts','outlooks','advisories');

try{

    // Set defaults and Parse the CLI input.
    $monitor = $content = $serviceID = false;
    foreach ($argv AS $arg => $value) {
        if ($arg == 0 ) {continue;}
        $params = explode('=',$value);
        switch ($params[0]){
            case 'content':
                    // This specific worker requires a content type.
                    $content = strtolower($params[1]);
                break;
            case 'serviceID':
                $serviceID = $params[1];
            case 'monitor':
                $monitor = ($params[1] == 'true') ? true : false;
        }
    }

    // Do not allow the script to continue if
    if( !$serviceID) { throw new Exception('serviceIntake.php: serviceID was not set.'); }
    if( !$content || !in_array($content,$contentTypes) ) {
        throw new Exception('serviceIntake.php: content was not set or is incorrect. '); }

    // Initialize classes.
    $intake = new \Service\Intake( $serviceID );

    // Register the script as a service. Will return false if the script is already running.
    $pid = $intake->lock( $serviceID );
    if($pid) {
        if($monitor) { echo "serviceIntake.php:$content:$serviceID is now running on $pid\n"; }

        // Set the kill timer and update all status info.
        $killTimer = rand( 60*60*1 , (60*60)+(60*4) );
        $timeElapsed = 0;
        $timeStarted = time();

        $intake->predis->hset( 'services.status', $serviceID, 'Idle');     // Set the daemon status.
        $intake->predis->hset( 'services.status.timestamp', $serviceID, time() );
        $intake->predis->hset( 'services.status.killTimer', $serviceID, $killTimer );


        $status = array(
            'message' => 'notification',
            'data'    => array(
                'css' => 'alert-info',
                'statusMessage' => '<span class=\"glyphicon glyphicon-exclamation-sign\" aria-hidden=\"true\"></span>
                    The intake service ('.$serviceID.')has rebooted.'
            )
        );

        $intake->socketSendMessage('update', $status );
        $intake->predis->hset('services.notifications', time(), json_encode($status) );


        while( $timeElapsed <= $killTimer ) {
            $timeElapsed = time() - $timeStarted;   // Determine how long the script has been running.
            $intake->process($content);     // Call Intake class to do the heavy lifting. All work gets done here!
        }
        $intake->unlock( $serviceID );

        $intake->predis->hset( 'services.status', $serviceID, 'Rebooting');     // Set the daemon status.

        $status = array(
            'message' => 'notification',
            'data'    => array(
                'css' => 'alert-info',
                'statusMessage' => '<span class=\"glyphicon glyphicon-exclamation-sign\" aria-hidden=\"true\"></span>
                    The intake service ('.$serviceID.') has expired and is rebooting.'
            )
        );

        $intake->socketSendMessage('update', $status );
        $intake->predis->hset('services.notifications', time(), json_encode($status) );


    }else{ if($monitor){echo "serviceIntake.php: $serviceID is already running.\n";} }
    exit;

}catch (Exception $e) {
    echo "\n".$e->getMessage()."\n";
    $service = new \Service\Service();
    $service->handleError($service->path.'log/error-'.date('Y-m-d',time()).'.log',$e);
}