<?php
require __DIR__ . '/vendor/autoload.php';

/**
 * Runs the service daemon from a cron job. This is responsible for updating system status to our monitoring software,
 * un-registering expired or dead workers from Redis, and performing any garbage collection routines required by the
 * application.
 *
 * To run as a cron job:  * * * * * (php /var/services/serviceDaemon.php serverID=serverWorkerDaemon > /dev/null 2>&1)
 * To run stand alone and monitor progress: php /var/services/serviceDaemon.php serverID=serverWorkerDaemon monitor=true
 *
 */

try {

    // Set defaults.
    $serverID = null;
    $monitor = false;

    // Parse the CLI input.
    foreach ($argv AS $arg => $value) {
        if ($arg == 0) {
            continue;
        }
        $params = explode('=', $value);
        switch ($params[0]) {
            case 'serverID':
                $serverID = $params[1];
                break;
            case 'monitor':
                $monitor = ($params[1] == 'true') ? true : false;
        }
    }

    if (!$serverID) {
        throw new Exception("No server id set. Aborting worker.");
    }

    // Initialize classes.
    $daemon = new \Service\Daemon($serverID);

    // Get the PID and register the daemon.
    $pid = $daemon->lock($serverID);
    if ($pid) {
        if ($monitor) {
            echo "serviceDaemon.php has started.\nServer: $serverID\nPID: $pid\n";
        }
        // Set the kill timer. This keeps the loop from repeating infinitely, and prevents the dreaded PHP memory leak.
        $killTimer = rand(60 * 60 * 1, (60 * 60) + (60 * 4));
        $timeElapsed = 0;
        $timeStarted = time();

        // Updates the status in Redis. This allows our monitoring application to see what is happening at any given time.
        $daemon->predis->hset('services.status', $serverID, 'Processing');     // Set the daemon status.
        $daemon->predis->hset('services.status.timestamp', $serverID, time());
        $daemon->predis->hset('services.status.killTimer', $serverID, $killTimer);

        // Sends out a socket message to our monitoring application that the daemon has rebooted. Logs the message in
        // Redis to be viewed by the application at a later date. In other applications, I have used a database instead
        // of redis to store these messages, but due to infrastructure restraints use redis here.
        $status = array(
            'message' => 'notification',
            'data' => array(
                'css' => 'alert-info',
                'statusMessage' => 'The service daemon (' . $serverID . ')has rebooted.'
            )
        );
        $daemon->socketSendMessage('update', $status);
        $daemon->predis->hset('services.notifications', time(), json_encode($status));

        // Start the service loop. This loop only lasts as long as the TTL kill timer generated earlier in the script.
        // This prevents memory from getting out of control.
        while ($timeElapsed <= $killTimer) {
            $timeElapsed = time() - $timeStarted;   // Determine how long the script has been running.

            // Clean up the processes. This loops through individual registered services and unlocks those that are
            // no longer running. This helps clear out registered processes that may have quit unexpectedly.
            $daemon->processGarbageCollection();

            // Send out the monitoring heartbeat. This includes sending out system information such as current running
            // scripts, server health and system utilization.
            $daemon->processHeartbeat();

            // Listen for incoming commands. This is currently only used to reboot services from the monitoring
            // application, but can be extended to allow any control input to be sent from the front end.
            // **WARNING** This should be used responsibly, as it is capable of executing system commands from the
            // front end. For this reason, I currently only allow reboot commands to be sent.
            $daemon->listen();

            // If monitor=true is set at runtime, output the current information concerning running services/workers.
            // This is useful for development and troubleshooting, as you can see on the command line what the status
            // is of each worker.
            if ($monitor) {
                system('clear');
                $services = $daemon->predis->hgetall('services.status');
                foreach ($services AS $key => $value) {
                    echo "$key: $value\n";
                }
            }
            // Added a sleep timer to the daemon to keep an insane amount of socket messages from being sent. This can
            // be tweaked depending on your hardware performance / requirements.
            sleep(10);
        }

        // If the script dies naturally, this will un-register the daemon in redis. If the script fails for some reason,
        // the next cron iteration should reboot the script normally, but in the meantime the system monitor will show
        // incorrect. Any fatal error in this script should send an email to the developer to alert of this situation.
        $daemon->unlock($serverID);

        // Send a message to the monitorin application that the service has expired naturally and is rebooting.
        $status = array(
            'message' => 'notification',
            'data' => array(
                'css' => 'alert-info',
                'statusMessage' => 'The service daemon (' . $serverID . ')has expired and is rebooting.'
            )
        );

        $daemon->socketSendMessage('update', $status);
        $daemon->predis->hset('services.notifications', time(), json_encode($status));

    } else {
        // This is only seen if you try to run the daemon from two different command lines using the same ServiceID.
        // When run from a cron job, all output is sent to /dev/null.
        if ($monitor) {
            echo "serviceDaemon is already running on $serverID\n";
        }
    }
    // Kill the script.
    exit;
} catch (Exception $e) {
    // Any errors in this script should be emailed to the developer immediately. If this script goes down, naturally
    // expiring worker scripts may take longer than normal to reboot, causing decreased performance.
    echo "\n" . $e->getMessage() . "\n";
    $service = new \Service\Service();
    $service->handleError($service->path . 'log/error-' . date('Y-m-d', time()) . '.log', $e);
}