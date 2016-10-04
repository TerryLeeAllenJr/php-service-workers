# php-service-workers
This is a code example using PHP, NodeJS, socket.io and Redis to build PHP service workers that process data in the background using PHP cron jobs. 

## Synopsis

Creates an extendable method for running PHP workers in the background using PHP.

* Each worker leverages Service/Service to run a in a continuous loop called from the command line or cron job.

..* At runtime, a TTL randomly generated to kill the script and a ramdom interval, letting the cron job will reboot the script on it's next iteration. This keeps memory leaks to a minimum. 
* Services depend on a unique ServiceID supplied at runtime, which registers the ServiceID + current system PID with Redis. 
..* This keeps a long running script from being called multiple times from the command line or cron job.
..* To run multiple instances of the same worker as separate "pipes," simply change the ServiceID at runtime.
* Provides a Service/Daemon class, which extends Service/Service:
..* Provides status for any registered services/workers.
..* Provides system status and server health for monitoring.
..* Provides garbage collection and automatic un-registering when a worker expires.
