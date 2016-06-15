<?php

/*
    Emitter.php

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

namespace Snowplow\Tracker;
use ErrorException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Emitter extends Constants {

    // Emitter Parameters

    private $buffer_size;
    private $buffer = array();

    // Debug Parameters

    private $debug_mode;

    // Logger

    private $logger;

    /**
     * Setup emitter parameters
     * - Stores the emitter sub-class object
     * - Sets the emitter buffer size
     * - Sets debug mode
     *
     * @param bool $debug
     * @param int $buffer_size
     */
    public function setup($debug, $buffer_size) {
        $this->buffer_size = $buffer_size;

        // Set error handler to catch warnings
        $this->warning_handler();

        if ($debug === true) {
            $this->debug_mode = true;
        }
        else {
            $this->debug_mode = false;
        }

        // Restore error handler back to default
        restore_error_handler();
    }

    /**
     * Set logger if passed, or create a logger stub
     * @param LoggerInterface|null
     */
    protected function setupLogger($logger) {
        if (is_null($logger)) {
            $this->logger = new NullLogger();
        } else {
            $this->logger = $logger;
        }
    }

    protected function returnLogger() {
        return $this->logger;
    }

    /**
     * Sends the buffer to the configured emitter for sending
     *
     * @param array $buffer - The array of events that are ready for sending
     * @param bool $curl_send - Boolean logic needed to ascertain whether or not
     *                          we are going to start the curl emitter
     */
    private function flush($buffer, $curl_send = false) {
        if (count($buffer) > 0) {
            $res = $this->send($buffer, $curl_send);

            // Set error handler to catch warnings
            $this->warning_handler();

            if (is_bool($res) && $res) {
                $success_string = "Payload sent successfully\nPayload: ".json_encode($buffer)."\n\n";
                if ($this->debug_mode) {
                    $this->returnLogger()->debug($success_string);
                }
            }
            else {
                $error_string = $res."\nPayload: ".json_encode($buffer)."\n\n";
                if ($this->debug_mode) {
                    $this->returnLogger()->error($error_string);
                }
            }
            $this->buffer = array();

            // Restore error handler back to default
            restore_error_handler();
        }
    }

    /**
     * Pushes the event payload into the emitter buffer
     * When buffer is full it flushes the buffer.
     *
     * @param array $final_payload - Takes the Trackers Payload as a parameter
     */
    public function addEvent($final_payload) {
        array_push($this->buffer, $final_payload);
        if (count($this->buffer) >= $this->buffer_size) {
            $this->flush($this->buffer);
        }
    }

    /**
     * Sends all events in the buffer to the collector
     */
    public function forceFlush() {
        $this->flush($this->buffer, true);
    }

    /**
     * Turns off debug_mode for the emitter
     * - Closes and deletes the log resource
     *
     * @param bool $deleteLocal - Delete all local information
     */
    public function debugSwitch($deleteLocal) {
        if ($this->debug_mode === true) {

            // Turn off debug_mode
            $this->debug_mode = false;
        }
    }

    /**
     * Returns the Collector URL
     *
     * @param string $type - The type of request we will be making to the collector
     * @param string $uri - Collector URI
     * @param string $protocol - What protocol we are using for the collector
     * @return string
     */
    public function getCollectorUrl($type, $uri, $protocol) {
        $protocol = $protocol == NULL ? self::DEFAULT_PROTOCOL : $protocol;
        if ($type == "POST") {
            return $protocol."://".$uri.self::POST_PATH;
        }
        else {
            return $protocol."://".$uri.self::GET_PATH;
        }
    }

    /**
     * Returns the request type that the emitter will use
     * - Makes sure that we cannot return an invalid type
     *   as the type determines many facets of the emitters
     * - If there is an invalid type OR NULL it will always
     *   be the default type == POST
     */
    public function getRequestType($type) {
        switch ($type) {
            case "POST" : return $type;
            case "GET"  : return $type;
            default     : return self::DEFAULT_REQ_TYPE;
        }
    }

    /**
     * Creates a new directory if the supplied directory path does
     * not exists already.
     *
     * @param string $dir - The directory we want to make
     * @return bool|string - Boolean describing if the creation was a success
     */
    public function makeDir($dir) {
        try {
            if (!is_dir($dir)) {
                mkdir($dir);
            }
            return true;
        } catch (ErrorException $e) {
            return $e->getMessage();
        }
    }

    /**
     * Attempts to return an opened file resource that can be written to
     * - If the file does not exist will attempt to make the file
     *
     * @param string $file_path - The path to the file we want to write to
     * @return string|resource - Either a file resource or a false boolean
     */
    public function openFile($file_path) {
        try {
            return fopen($file_path, "w");
        } catch (ErrorException $e) {
            return $e->getMessage();
        }
    }

    /**
     * Attempts to close an open file resource
     *
     * @param resource $file_path - The path to the file we want to close
     * @return bool|string - Whether or not the close was a success
     */
    public function closeFile($file_path) {
        try {
            fclose($file_path);
            return true;
        } catch (ErrorException $e) {
            return $e->getMessage();
        }
    } 

    /**
     * Attempts to copy a file to a new directory
     *
     * @param string $path_from - The path to the file we want to copy
     * @param string $path_to - The path which we want to copt the file to
     * @return bool|string - Whether or not the copy was a success
     */
    public function copyFile($path_from, $path_to) {
        try {
            copy($path_from, $path_to);
            return true;
        } catch (ErrorException $e) {
            return $e->getMessage();
        }
    }

    /**
     * Attempts to delete a file
     *
     * @param string $file_path - The path of the file we want to delete
     * @return 
     */
    public function deleteFile($file_path) {
        try {
            unlink($file_path);
            return true;
        } catch (ErrorException $e) {
            return $e->getMessage();
        }
    }

    /**
     * Attempts to write a string to a file
     *
     * @param resource $file_path - The path of the file we want to write to
     * @param string $content - The content we want to write into the file
     * @return bool|string - If the write was successful or not
     */
    public function writeToFile($file_path, $content) {
        try {
            fwrite($file_path, $content);
            return true;
        } catch (ErrorException $e) {
            return $e->getMessage();
        }
    }

    // Return Functions

    /**
     * Returns the buffer_size
     *
     * @return int
     */
    public function returnBufferSize() {
        return $this->buffer_size;
    }

    /**
     * Returns the events buffer
     *
     * @return array
     */
    public function returnBuffer() {
        return $this->buffer;
    }

    /**
     * Returns a boolean of if debug mode is on or not
     *
     * @return bool
     */
    public function returnDebugMode() {
        return $this->debug_mode;
    }
}
