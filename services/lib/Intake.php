<?php

namespace Service;

/**
 * Class Intake
 * @package Service
 *
 * PLEASE NOTE: This is a rude code example showing how a worker can be created that leverages the Service class.
 * This code is not production ready, nor is it my best work. In the future I plan to make this code DRY.
 *
 * This class is responsible for taking uploaded files from their /temp position, recording them into Redis and moving
 * them to a new location available to our subscribers. This class extends Service, which allows multiple instances
 * of this worker to be spun up to handle a greater load of intake files. This is only an example of a type of work that
 * can be performed using a worker. Other classes that extend Service can be built to handle thumbnail creation from
 * video, crunch large format images to smaller, send emails, or any other repetitive tasks better handled as a background
 * task. Using Redis and socket.io, real-time updates can be provided to users during the process.
 */
class Intake extends Service {

    private $serviceID;
    public function __construct( $serviceID, $runtimeConfig = array() ){
        parent::__construct( $runtimeConfig );
        $this->serviceID = $serviceID;
    }

    /**
     * @param $content
     * Setter for processing the different content types we provide.
     */
    public function process($content){
        try {
            switch( $content ) {
                case 'scripts':
                    $this->processScripts();
                    break;
                case 'outlooks':
                    $this->processOutlooks();
                    break;
                case 'advisories':
                    $this->processAdvisories();
                    break;
            }
        }catch( \Exception $e ){
            $this->handleError($this->path.'log/error-'.date('Y-m-d',time()).'.log',$e);
        }
    }

    /**
     * @throws \Exception
     * Processes scripts.
     */
    private function processScripts(){

        // Move 2NSML files.
        echo "Moving 2NSML files...\n";
        $nsml2 = $this->getFiles($this->config['SERVER']['PATH']['SCRIPTS_TEMP']."2nsml/");
        foreach ( $nsml2 AS $fileName ){
            echo " -> Moving $fileName...";
            $this->predis->hset( 'services.status', $this->serviceID, 'Working');
            $this->moveFile(
                $this->config['SERVER']['PATH']['SCRIPTS_TEMP'].'2nsml/'.$fileName,
                $this->config['SERVER']['PATH']['SCRIPTS'].'2nsml/'.$fileName
            );
            echo "DONE!!\n";
            $this->predis->hset( 'services.status', $this->serviceID, 'Idle');
        }

        // Move 3NSML files.
        echo "Moving 3NSML files...\n";
        $nsml3 = $this->getFiles( $this->config['SERVER']['PATH']['SCRIPTS_TEMP']."3nsml/");
        foreach ( $nsml3 AS $fileName ){
            echo " -> Moving $fileName...";
            $this->predis->hset( 'services.status', $this->serviceID, 'Working');
            $this->moveFile(
                $this->config['SERVER']['PATH']['SCRIPTS_TEMP'].'3nsml/'.$fileName,
                $this->config['SERVER']['PATH']['SCRIPTS'].'3nsml/'.$fileName
            );
            echo "DONE!!\n";
            $this->predis->hset( 'services.status', $this->serviceID, 'Idle');
        }

        // Move XML files.
        echo "Moving XML files...\n";
        $xml = $this->getFiles($this->config['SERVER']['PATH']['SCRIPTS_TEMP']."xml/");
        foreach ( $xml AS $fileName ){
            echo " -> Moving $fileName...";
            $this->predis->hset( 'services.status', $this->serviceID, 'Working');
            $this->moveFile(
                $this->config['SERVER']['PATH']['SCRIPTS_TEMP'].'xml/'.$fileName,
                $this->config['SERVER']['PATH']['SCRIPTS'].'xml/'.$fileName.".xml"
            );
            echo "DONE!!\n";
            $this->predis->hset( 'services.status', $this->serviceID, 'Idle');
        }

    }
    private function processOutlooks(){

        // Move 2NSML files.
        $nsml2 = $this->getFiles($this->config['SERVER']['PATH']['OUTLOOKS_TEMP']."2nsml/");
        foreach ( $nsml2 AS $fileName ){
            $this->predis->hset( 'services.status', $this->serviceID, 'Working');
            $this->moveFile(
                $this->config['SERVER']['PATH']['OUTLOOKS_TEMP'].'2nsml/'.$fileName,
                $this->config['SERVER']['PATH']['OUTLOOKS'].'2nsml/'.$fileName
            );
            $this->predis->hset( 'services.status', $this->serviceID, 'Idle');
        }

        // Move 3NSML.
        $nsml3 = $this->getFiles( $this->config['SERVER']['PATH']['OUTLOOKS_TEMP']."3nsml/");
        foreach ( $nsml3 AS $fileName ){
            $this->predis->hset( 'services.status', $this->serviceID, 'Idle');
            $this->predis->hset( 'services.status', $this->serviceID, 'Working');
            $this->moveFile(
                $this->config['SERVER']['PATH']['OUTLOOKS_TEMP'].'3nsml/'.$fileName,
                $this->config['SERVER']['PATH']['OUTLOOKS'].'3nsml/'.$fileName
            );
            $this->predis->hset( 'services.status', $this->serviceID, 'Idle');
        }

        // Move XML files.
        $nsml2 = $this->getFiles($this->config['SERVER']['PATH']['OUTLOOKS_TEMP']."xml/");
        foreach ( $nsml2 AS $fileName ){
            $this->predis->hset( 'services.status', $this->serviceID, 'Working');
            $this->moveFile(
                $this->config['SERVER']['PATH']['OUTLOOKS_TEMP'].'xml/'.$fileName,
                $this->config['SERVER']['PATH']['OUTLOOKS'].'xml/'.$fileName.".xml"
            );
            $this->predis->hset( 'services.status', $this->serviceID, 'Idle');
        }
    }
    private function processAdvisories(){

        // Move 2NSML files.
        $nsml2 = $this->getFiles($this->config['SERVER']['PATH']['ADVISORIES_TEMP']."2nsml/");
        foreach ( $nsml2 AS $fileName ){
            $this->predis->hset( 'services.status', $this->serviceID, 'Working');
            $this->moveFile(
                $this->config['SERVER']['PATH']['ADVISORIES_TEMP'].'2nsml/'.$fileName,
                $this->config['SERVER']['PATH']['ADVISORIES'].'2nsml/'.$fileName
            );
            $this->predis->hset( 'services.status', $this->serviceID, 'Idle');
        }

        //Process 3NSML files.
        $nsml3 = $this->getFiles( $this->config['SERVER']['PATH']['ADVISORIES_TEMP']."3nsml/");
        foreach ( $nsml3 AS $fileName ){
            $this->predis->hset( 'services.status', $this->serviceID, 'Working');
            $this->moveFile(
                $this->config['SERVER']['PATH']['ADVISORIES_TEMP'].'3nsml/'.$fileName,
                $this->config['SERVER']['PATH']['ADVISORIES'].'3nsml/'.$fileName
            );
            $this->predis->hset( 'services.status', $this->serviceID, 'Idle');
        }

        // PROCESS ADDED 06.06.2015 per Matt and Brian.
        // Move XML files.
        $nsml2 = $this->getFiles($this->config['SERVER']['PATH']['ADVISORIES_TEMP']."xml/");
        foreach ( $nsml2 AS $fileName ){
            $this->predis->hset( 'services.status', $this->serviceID, 'Working');
            $this->moveFile(
                $this->config['SERVER']['PATH']['ADVISORIES_TEMP'].'xml/'.$fileName,
                $this->config['SERVER']['PATH']['ADVISORIES'].'xml/'.$fileName.".xml"
            );
            $this->predis->hset( 'services.status', $this->serviceID, 'Idle');
        }
    }

    private function getFiles( $path ){
        if( !is_dir( $path )){ throw new \Exception(
            "Intake::getfiles() $path is not a directory. Could not list files for intake. ");
        }
        $files = array();
        $handle = opendir($path);

        while (false !== ($entry = readdir($handle))) {
            if ($entry != "." && $entry != ".." && !is_dir($path.$entry)) {
                $fileTime = filemtime($path.$entry);
                if (!is_readable($path.$entry) || abs( time() - filemtime($path.$entry)) < 30) { continue; }
                array_push($files,$entry);
            }
        }
        closedir($handle);
        return $files;
    }

    private function copyFile( $source, $dest ){
        $this->predis->hset( 'services.status', $this->serviceID, "Copying file...");

        if ( \copy( $source, $dest ) ) {
            file_put_contents(
                $this->config['SERVER']['PATH']['LOG'].'transfer-'.date('Y-m-d',time()).'.log',
                date('Y-m-d H:i:s',time()).': Copied '.$source."->".$dest."\n",
                FILE_APPEND
            );
        }else{
            throw new \Exception("Intake::copyFile() $source -> $dest" );
        }
    }

    private function moveFile( $source, $dest ){
        $this->predis->hset( 'services.status', $this->serviceID, "Copying file...");

        if ( \rename( $source, $dest ) ) {
            file_put_contents(
                $this->config['SERVER']['PATH']['LOG'].'transfer-'.date('Y-m-d',time()).'.log',
                date('Y-m-d H:i:s',time()).': Moved '.$source."->".$dest."\n",
                FILE_APPEND
            );
        }else{
            throw new \Exception("Intake::moveFileFile() $source -> $dest" );
        }
    }

    public function scanEditorialContent(){

        $directories = array(
            'scripts' => $this->config['SERVER']['PATH']['SCRIPTS'].'xml/',
            'outlooks' => $this->config['SERVER']['PATH']['OUTLOOKS'].'xml/',
            'advisories' => $this->config['SERVER']['PATH']['ADVISORIES'].'xml/'
        );

        foreach($directories AS $contentType => $directory){

            if( !is_dir( $directory )){
                throw new \Exception( "Intake::scanEditorialContent() $directory is not a directory.");
            }

            $handle = opendir( $directory );

            while (false !== ($entry = readdir( $handle ) )) {

                if ($entry != "." && $entry != ".." && !is_dir( $directory.$entry )) {

                    // If the file is unreadable, skip it. This may be because the script caught the file at the moment
                    // a record was created, but before it was done processing. Garbage collection will get this file later.
                    if ( !is_readable($directory.$entry)) { continue; }

                    if( $xml = simplexml_load_file( $directory.$entry) ) {
                        $json = json_encode($xml);
                        // Test if there is already an entry for the item.
                        if( $redisEntry = $this->predis->hget('content.'.$contentType,$entry )){
                            // If there is an entry, test if it is equal to the current file. This will keep the script
                            // from constantly writing to the Redis server.
                            if(serialize(json_decode($redisEntry,true) === serialize(json_decode($json,true)) )){ continue; }
                        }
                        // Write the data to Redis for API consumption.
                        $this->predis->hset( 'content.'.$contentType, $entry, $json );

                        $this->socketSendMessage('newContent',
                            array(
                                'message' => 'newContent',
                                'data' => array(
                                    'timestamp'     => time(),
                                    'contentType'   => $contentType,
                                    'fileName'      => $entry,
                                    'content'       => $json
                                )
                            ));

                    }else{
                        throw new \Exception("Intake::scanEditorialContent() could not parse {$directory}{$entry} as XML.");
                    }
                }
            }
            closedir($handle);
        }

    }

    public function removeEditorialContentFromRedis(){

        $directories = array(
            'scripts' => $this->config['SERVER']['PATH']['SCRIPTS'].'xml/',
            'outlooks' => $this->config['SERVER']['PATH']['OUTLOOKS'].'xml/',
            'advisories' => $this->config['SERVER']['PATH']['ADVISORIES'].'xml/'
        );
        foreach($directories AS $contentType => $directory){
            $currentlyListed = $this->predis->hgetall('content.'.$contentType);
            foreach( $currentlyListed AS $fileName => $content ) {
                if( !file_exists($directory.$fileName )) { $this->predis->hdel('content.'.$contentType,$fileName);}
            }
        }

    }

}