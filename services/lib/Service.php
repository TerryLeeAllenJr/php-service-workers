<?php
namespace Service;

use \ElephantIO\Client;
use \ElephantIO\Engine\SocketIO\Version1X;

/**
 * Class Service
 * @package Service
 * This class is responsible for managing multiple instances of each worker running on the server. Each service is
 * typically a continuous loop run from a cron job that repeats every minute. To avoid multiple identical services
 * running simultaneously as well as the notorious PHP memory leaks, each service is supplied with a Service ID at
 * run time. Service IDs and the current linux PID are stored in Redis, registering the worker. When a new cron job is
 * started, the script first looks it the current Service ID is running, and will kill the script is true.
 *
 * Garbage collection is handled by the Daemon worker, which looks at the current list of registered worker, and checks
 * the server to see if the PID is still running. If not, the worker is un-registered in Redis, allowing the cron to spin
 * up a new PID on it's next pass.
 */
class Service
{

    public $predis;
    public $timer;
    public $path;
    protected $developer;
    protected $dbh;
    protected $elephant;

    protected $config;
    private $runtimeConfig;

    /**
     * @param array $runtimeConfig
     * @throws \Exception
     * Sets up all required public dependencies. Parses the runtime config provided by the command line.
     */
    public function __construct($runtimeConfig = array())
    {
        try {

            // Set the system configuration. This is using the .ini files in services/lib/config/
            $this->config = \Service\Config::getConfig();
            $this->path = $this->config['SERVER']['PATH']['SERVICES'];

            // Set runtime vars. These determine which database, redis, and node servers are connected.
            // This is accomplished by using the defined runtime value as a key for the configuration variable.
            $this->runtimeConfig = array_merge(
                array(
                    'database' => false,
                    'redis' => 'LOCAL',
                    'nodejs' => 'LOCAL'
                ),
                $runtimeConfig
            );

            // Sets up the database connection if called for from the command line.
            if ($this->runtimeConfig['database']) {
                $database = $this->config['DATABASE'][$this->runtimeConfig['database']];

                $this->dbh = new \PDO(
                    'mysql:host=' . $database['HOST'] . ';dbname=' . $database['DATABASE'] . ';port=' . $database['PORT'],
                    $database['USER'],
                    $database['PASS']
                );
                $this->dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            }


            $predisConfig = array(
                'scheme' => 'tcp',
                'host' => $this->config['REDIS'][$this->runtimeConfig['redis']],
                'port' => $this->config['REDIS']['PORT']
            );

            if (!$this->predis = new \Predis\Client($predisConfig)) {
                throw new \Exception("Unable to connect to the Redis Server.");
            }

        } catch (Exception $e) {
            $this->handleError($this->path . 'log/error-' . date('Y-m-d', time()) . '.log', $e);
        }
    }

    /**
     * @param $message
     * @param $data
     * Sends a message to socket.io using Elephant.io
     */
    public function socketSendMessage($message, $data)
    {
        $socketURL = 'http://' . $this->config['NODEJS'][$this->runtimeConfig['nodejs']] . ":" . $this->config['NODEJS']['PORT'];
        $socket = new Client(new Version1X($socketURL));
        $socket->initialize();
        $socket->emit($message, $data);
        $socket->close();
    }

    /**
     * @param $serviceID
     * @return \stdClass
     * Gets the current information from Redis given the provided serviceId.
     */
    public function getServiceInfo($serviceID)
    {
        $data = new \stdClass();
        $data->serviceID = $serviceID;
        $data->pid = $this->predis->hget('services.processes', $serviceID);
        $data->status = $this->predis->hget('services.status', $serviceID);
        $data->timestamp = $this->predis->hget('services.status.timestamp', $serviceID);
        $data->threshold = $this->predis->hget('services.status.threshold', $serviceID);
        $data->killTimer = $this->predis->hget('services.status.killTimer', $serviceID);

        return $data;
    }

    /**
     * @return \stdClass
     * Gets the data for all currently registered services running.
     */
    public function getRegisteredServiceInfo()
    {
        $processes = $this->predis->hgetall('services.processes');
        $services = new \stdClass();
        foreach ($processes AS $serviceID => $pid) {
            $services->{$serviceID} = $this->getServiceInfo($serviceID);
        }

        return $services;
    }

    /**
     * @param $processID - The unique process id. Used to reference a process inside of the Redis hash.
     * @return string
     * Checks the current status of a service using the linux PID.
     */
    public function checkService($processID)
    {
        $pid = $this->predis->hget('services.processes', $processID);
        if (!$pid || !posix_getpgid($pid)) {
            $this->predis->hset('services.status', $processID, 'Rebooting');
        }

        return $this->predis->hget('services.status', $processID);
    }

    /**
     * @param $service_id
     * @return bool|int
     * Checks to see if a service is currently running on the system. If a PID is found using the same Service ID, it
     * will return false. Otherwise a new service will be registered using the current scripts PID, locking the service
     * and preventing duplicate services from being opened via the cron job.
     */
    public function lock($service_id)
    {
        // Check if a process for this id has been registered and return if it is.
        if ($pid = $this->predis->hget('services.processes', $service_id)) {
            /*  If a PID is still registered, then either the script is running or it quit unexpectedly.
                If it is still running, then return false to keep the script from running redundantly, otherwise
                return the current PID to register the script. */
            if (posix_getpgid($pid)) {
                return false;
            }
        }
        /* Register the process and return the PID if a process for this service_id has not been registered,
           or if it was registered but is not running. */
        $pid = getmypid();
        $this->predis->hset('services.processes', $service_id, $pid);

        return $pid;
    }

    /**
     * @param $serviceID
     * This Deletes all Redis info and un-registers the process. This allows the script to die, and will be re-registered
     * on it's next call from the cron job.
     */
    public function unlock($serviceID)
    {
        $this->predis->hdel('services.processes', $serviceID);
        $this->predis->hdel('services.status', $serviceID);
        $this->predis->hdel('services.status.threshold', $serviceID);
        $this->predis->hdel('services.status.timestamp', $serviceID);
        $this->predis->hdel('services.status.killTimer', $serviceID);

        return;
    }

    /**
     * @param $log - The path to the log file.
     * @param \Exception $e
     * Sends an error to the developer when an error occurs. This feeds into our Redmine instance populating a trouble
     * ticket and alerting the team to an issue.
     */
    public function handleError($log, \Exception $e)
    {

        $content = date('Y-m-d H:i:s ', time()) . $e->getMessage() . "\n";
        file_put_contents($log, $content, FILE_APPEND);

        $mailHasBeenSent = ($this->predis->get('error.mail.sent')) ? true : false; // Prevents multiple emails being sent.
        if (!$mailHasBeenSent) {
            foreach ($this->config['SERVER']['DEVELOPER'] AS $email) {
                $message = "There was an error with the Services\n\n" . var_dump($e);
                mail($email, 'VOD Services Error', $message);
                $this->predis->set('error.mail.sent', 'true');
                $this->predis->expire('error.mail.sent', 60 * 60 * 4);
            }
        }
    }

    /**
     * @param $log - The path to the log file.
     * @param \Exception $e
     * This logs minor errors to the log configured in config.ini settings.
     */
    public function handleMinorError($log, \Exception $e)
    {
        $content = date('Y-m-d H:i:s ',time()).$e->getMessage()."\n";
        file_put_contents( $log, $content, FILE_APPEND );
    }

    /**
     * Kills the whols redis instance, and resets all php scripts currently running as the current user.
     * This will cause any messages currently in the queue to be lost. Only use in an emergency. Should have been called
     * hcf.
     */
    public function resetServices()
    {
        $this->predis->flushall();
        system('killall -9 php -u ' . get_current_user());
    }

    /**
     * Un-registers and kills a specific service.
     */
    public function killService($serviceID)
    {
        $pid = $this->predis->hget('services.processes', $serviceID);
        if ($pid) {
            $this->unlock($serviceID);
            system('kill -9 ' . $pid);
        }
    }

    /**
     * @return float
     * Gets the current memory utilization for the server. Used in our monitoring application.
     */
    protected function getMemoryUsage()
    {
        $free = shell_exec('free');
        $free = (string)trim($free);
        $free_arr = explode("\n", $free);
        $mem = explode(" ", $free_arr[1]);
        $mem = array_filter($mem);
        $mem = array_merge($mem);
        $memory_usage = $mem[2] / $mem[1] * 100;

        return $memory_usage;
    }

    /**
     * @return mixed
     * Gets the current processor load. Used in our monitoring application.
     */
    protected function getProcessorLoad()
    {
        $load = sys_getloadavg();

        return $load[0];
    }

    /**
     * @return float
     * Gets HDD utilization. Used in our monitoring application.
     */
    protected function getHDDInfo()
    {
        return round((disk_free_space('/') / disk_total_space('/')) * 100, 2);
    }

    /**
     * @param string $name
     * @return int
     * Gets current process count by name. We are currently using pre-fork apache, which created a separate httpd
     * process for each connected user.
     */
    protected function getProcessCountByName($name = 'httpd')
    {
        return intval(shell_exec("ps -C $name  --no-headers | wc -l"));
    }

    /**
     * @param $log
     * @param $content
     * @return int
     * Adds a record to the log.
     */
    protected function log($log, $content)
    {
        return file_put_contents($log, $content, FILE_APPEND);
    }

}