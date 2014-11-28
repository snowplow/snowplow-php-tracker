<?php
/*
    FileEmitter.php

    Copyright (c) 2014 Snowplow Analytics Ltd. All rights reserved.

    This program is licensed to you under the Apache License Version 2.0,
    and you may not use this file except in compliance with the Apache License
    Version 2.0. You may obtain a copy of the Apache License Version 2.0 at
    http://www.apache.org/licenses/LICENSE-2.0.

    Unless required by applicable law or agreed to in writing,
    software distributed under the Apache License Version 2.0 is distributed on
    an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either
    express or implied. See the Apache License Version 2.0 for the specific
    language governing permissions and limitations there under.

    Authors: Joshua Beemster
    Copyright: Copyright (c) 2014 Snowplow Analytics Ltd
    License: Apache License Version 2.0
*/

namespace Snowplow\Tracker\Emitters;
use Snowplow\Tracker\Emitter;

class FileEmitter extends Emitter{
    // Emitter Parameters
    private $type;
    private $url;
    private $log_dir;
    private $log_file;

    // Worker Parameters
    private $worker = 0;
    private $worker_paths = array();

    /**
     * Creates a File Emitter
     *
     * @param string $uri
     * @param string|null $protocol
     * @param string|null $type
     * @param int|null $workers
     * @param int|float|null $timeout
     * @param int|null $buffer_size
     */
    public function __construct($uri, $protocol = NULL, $type = NULL, $workers = NULL, $timeout = NULL, $buffer_size) {
        $this->type     = $this->getRequestType($type);
        $this->url      = $this->getCollectorUrl($this->type, $uri, $protocol);
        $this->log_dir  = dirname(dirname(__DIR__))."/".self::WORKER_FOLDER;
        $this->log_file = $this->initLogFile();

        // Creates worker directories and starts the background workers
        $this->initWorkers($workers, $timeout);

        $buffer = $buffer_size == NULL ? self::WORKER_BUFFER : $buffer_size;
        $this->setup("curl", false, $buffer);
    }

    /**
     * Sends the events log file to a folder being watched by a worker
     *
     * @param array $buffer
     * @return bool
     */
    public function send($buffer) {
        if (count($buffer) > 0) {
            // Add jsons to the log file.
            foreach ($buffer as $event) {
                fwrite($this->log_file, json_encode($event)."\n");
            }
            fclose($this->log_file);

            // Add the file to a worker folder.
            $pos = $this->getWorkerPos();
            copy($this->log_dir."events.log", $this->worker_paths[$pos]."events-".rand().".log");

            // Reset the log and continue...
            $this->reset();
            return "File Emitter logs have been sent to the '/temp/' folder.";
        }
        return "No events to write.";
    }

    /**
     * Makes a log file into which we can store events
     *
     * @return resource
     */
    private function initLogFile() {
        if (!is_dir($this->log_dir)) {
            mkdir($this->log_dir);
        }
        return fopen($this->log_dir."/events.log","w");
    }

    /**
     * Does the initial setup for the File Consumer workers
     *
     * @param int|null $workers
     * @param int|null $timeout
     */
    private function initWorkers($workers, $timeout) {
        $workers = $workers == NULL ? self::WORKER_COUNT : $workers;

        // Make the log failure directory
        $this->makeDir($this->log_dir."failed-logs/");

        // Create the workers
        for ($i = 0; $i < $workers; $i++) {
            $dir = $this->log_dir."w".$i."/";
            array_push($this->worker_paths, $dir);
            $this->makeDir($dir);
            $this->makeWorker($i, $timeout);
        }
    }

    /**
     * Creates a background worker
     * - Checks its folder for log files to consume and send
     * - When it is out of files it will wait until it times out and then close
     *
     * @param int $worker_num
     * @param int|null $timeout
     */
    private function makeWorker($worker_num, $timeout) {
        // Make sure we are in the correct directory level to execute our command
        chdir(dirname(dirname(__DIR__)));

        // Grab worker settings from Constants class
        $timeout = $timeout    == NULL   ? self::WORKER_TIMEOUT : $timeout;
        $window  = $this->type == "POST" ? self::WORKER_WINDOW_POST : self::WORKER_WINDOW_GET;
        $buffer  = $this->type == "POST" ? self::WORKER_BUFFER_POST : self::WORKER_BUFFER_GET;

        // Make our worker startup command
        $cmd = "php Worker.php";
        $cmd.= " --file_path ".self::WORKER_FOLDER."w".$worker_num."/";
        $cmd.= " --url ".$this->url;
        $cmd.= " --type ".$this->type;
        $cmd.= " --timeout ".$timeout;
        $cmd.= " --window ".$window;
        $cmd.= " --buffer ".$buffer;

        // Execute command in the background and return
        $this->execInBackground($cmd);
    }

    /**
     * Returns the worker that is next to be assigned an events log.
     *
     * @return int
     */
    private function getWorkerPos() {
        if ($this->worker < count($this->worker_paths)) {
            return $this->worker++;
        }
        else {
            return $this->worker = 0;
        }
    }

    /**
     * Resets the events.log file to empty
     */
    private function reset() {
        $this->log_file = fopen($this->log_dir."/events.log", "w");
    }

    /**
     * Creates a new directory
     * - If that directory does not already exist
     *
     * @param string $dir
     */
    private function makeDir($dir) {
        if (!is_dir($dir)) {
            mkdir($dir);
        }
    }

    /**
     * Creates a command which does not wait for any response.
     * - Essentially sends any response into the abyss
     * - Makes the background process non blocking
     *
     * @param $cmd
     */
    private function execInBackground($cmd) {
        if (substr(php_uname(), 0, 7) == "Windows") {
            pclose(popen("start /B ".$cmd, "w"));
        }
        else {
            exec($cmd." > /dev/null &");
        }
    }

    /**
     * Disables debug mode
     * - Function is here to prevent errors when passing a global debug shutdown
     */
    public function turnOfDebug() {
        return "File Emitter does not have a debug mode yet!";
    }

    // File Return Functions
    /**
     * Returns the collector url
     *
     * @return string
     */
    public function returnUrl() {
        return $this->url;
    }

    /**
     * Returns the type that the emitter is using
     *
     * @return null|string
     */
    public function returnType() {
        return $this->type;
    }

    /**
     * Returns the file path
     * - Inclusive of the root path
     *
     * @return null|string
     */
    public function returnLogDir() {
        return $this->log_dir;
    }

    /**
     * Returns the root path of the project
     *
     * @return string
     */
    public function returnRootPath() {
        return dirname(dirname(__DIR__));
    }

    /**
     * Returns the amount of workers the emitter has
     *
     * @return int
     */
    public function returnWorkerCount() {
        return $this->worker;
    }

    /**
     * Returns an array of current worker paths
     *
     * @return array
     */
    public function returnWorkerPaths() {
        return $this->worker_paths;
    }
}
