<?php
namespace Service;

/**
 * Class Daemon
 * @package Service
 * This class is responsible for running the service daemon on the server. This daemon is responsible for both updating 
 * the current status on each of our worker scripts, as well as handling the garbage collection and archiving of out of
 * date files. Additionally, the daemon listens for encrypted command send from our internal admin page, allowing the IT
 * department to reboot hung workers manually should something happen.
 */
class Daemon extends Service
{

    private $serverID;

    /**
     * @param array $serverID
     * @param array $runtimeConfig
     * @throws \Exception
     * Constructs the parent using the server ID and runtime config supplied by the command line.
     */
    public function __construct($serverID, $runtimeConfig = array())
    {
        parent::__construct($runtimeConfig);
        $this->serverID = $serverID;
    }

    /**
     * Updates the system status stored in Redis on each iteration of the daemon.
     */
    public function processHeartbeat()
    {
        try {
            $this->socketSendMessage('update',
                array(
                    'message' => 'updateSystemStats',
                    'data' => array(
                        'server' => array(
                            'mem' => $this->getMemoryUsage(),
                            'cpu' => $this->getProcessorLoad(),
                            'hdd' => $this->getHDDInfo(),
                            'name' => $this->serverID
                        ),
                        'services' => $this->getRegisteredServiceInfo()
                    )
                ));
        } catch (\Exception $e) {
            $this->handleMinorError($this->path . 'log/error.log', $e);
        }
    }

    /**
     * Cleans up processes and status data for services that have terminated or rebooted.
     */
    public function processGarbageCollection()
    {
        // Loop through each registered service and determine if it is running.
        foreach ($this->predis->hgetall('services.processes') AS $serviceID => $pid) {
            // If an entry does not exist or is not running, remove it' data so that it is not reported incorrectly.
            if (!$pid || !posix_getpgid($pid)) {
                $this->unlock($serviceID);
            }
        }
    }

    /**
     * @param $path
     * @throws \Exception
     * Moves files older than the TTL set in the Global config file to it's respective archive directory.
     */
    public function archiveFiles($path)
    {

        if (!is_dir($path)) {
            throw new \Exception(
                "Daemon::archiveFiles() $path is not a directory. Could not list files. ");
        }
        $handle = opendir($path);
        while (false !== ($entry = readdir($handle))) {
            if ($entry != "." && $entry != ".." && !is_dir($path . $entry)) {
                $elapsedTime = abs(time() - filemtime($path . $entry));

                if ($elapsedTime > $this->config['CONTENT']['HOURS_TO_KEEP']) {
                    if (!\rename($path . $entry, $path . 'archive/' . $entry)) {
                        throw new \Exception("Daemon::archiveFiles() Could not move " . $path . $entry);
                    }
                    $this->log(
                        $this->config['SERVER']['PATH']['LOG'] . 'archive-' . date('Y-m-d', time()),
                        date('Y-m-d H:i:s', time()) . ': ' . $path . $entry);
                }

            }
        }
        closedir($handle);
    }

    /**
     * @return bool
     * Listens for IO coming from the user and parses it. Currently this is only used to reboot specific services from
     * our backend, but will be extended in the future to allow other operations as needed.
     */
    public function listen()
    {
        while ($io = json_decode($this->predis->lpop('io'), false)) {
            switch ($io->command) {

                case 'reboot':
                    $command = "kill " . $io->data;

                    if (!system($command)) {
                        $status = array(
                            'message' => 'notification',
                            'data' => array(
                                'css' => 'alert-warning',
                                'statusMessage' =>
                                    '<span class=\"glyphicon glyphicon-exclamation-sign\" aria-hidden=\"true\"></span>
                                        Reboot message sent.'
                            )
                        );
                        $this->socketSendMessage('update', $status);
                    } else {

                    }
                    break;
            }
        }
    }

}