<?php
/*
    SocketEmitter.php

    Copyright (c) 2014-2022 Snowplow Analytics Ltd. All rights reserved.

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
    License: Apache License Version 2.0
*/

namespace Snowplow\Tracker\Emitters;
use Snowplow\Tracker\Emitter;
use Snowplow\Tracker\Emitters\RetryRequestManager;
use Exception;

class SocketEmitter extends Emitter {

    // Emitter Parameters

    private $uri;
    private $ssl;
    private $type;
    private $timeout;
    private $debug;
    private $requests_results;
    private $max_retry_attempts;
    private $retry_backoff_ms;
    private $server_anonymization;

    // Socket Parameters

    private $socket_failed = false;
    private $socket;

    /**
     * Creates a Socket Emitter
     *
     * @param string $uri - Collector URI to be used for the request
     * @param bool|null $ssl - If the collector is using SSL
     * @param string|null $type - Type of request to be made (POST || GET)
     * @param float|int|null $timeout - Timeout for the socket connection
     * @param int|null $buffer_size - Number of events to buffer before making a POST request to collector
     * @param bool|null $debug - If debug is on
     * @param int|null $max_retry_attempts - The maximum number of times to retry a request. Defaults to 1.
     * @param int|null $retry_backoff_ms - The number of milliseconds to backoff before retrying a request. Defaults to 100ms.
     * @param bool|null $server_anonymization - Whether to enable Server Anonymization and not collect the IP or Network User ID. Defaults to false.
     */
    public function __construct($uri, $ssl = NULL, $type = NULL, $timeout = NULL, $buffer_size = NULL, $debug = NULL, $max_retry_attempts = NULL, $retry_backoff_ms = NULL, $server_anonymization = false) {
        $this->type    = $this->getRequestType($type);
        $this->uri     = $uri;
        $this->ssl     = $ssl == NULL ? self::DEFAULT_SSL : (bool) $ssl;
        $this->timeout = $timeout == NULL ? self::SOCKET_TIMEOUT : $timeout;
        $this->max_retry_attempts = $max_retry_attempts;
        $this->retry_backoff_ms = $retry_backoff_ms;
        $this->server_anonymization = $server_anonymization;

        // If debug is on create a requests_results array
        if ($debug === true) {
            $this->debug = true;
            $this->requests_results = array();
        }
        else {
            $this->debug = false;
        }
        $buffer = $buffer_size == NULL ? self::SOCKET_BUFFER : $buffer_size;
        $this->setup("socket", $debug, $buffer);
    }

    /**
     * Sends all the events in the buffer to the HTTP Socket.
     *
     * @param array $buffer
     * @return bool $res
     */
    public function send($buffer) {
        if (count($buffer) > 0) {
            $uri = $this->uri;
            $type = $this->type;
            $socket_made = $this->makeSocket();

            if (is_bool($socket_made) && $socket_made) {
                if ($type == "POST") {
                    $data = $this->getPostRequest($this->batchUpdateStm($buffer));
                    $body = $this->getRequestBody($uri, $data, $type);

                    // Send requests to the socket
                    $res = $this->makeRequest($body);
                    return $res;
                }
                else {
                    $res = "";
                    $res_ = "";
                    foreach ($buffer as $event) {
                        $data = http_build_query($this->updateStm($event));
                        $body = $this->getRequestBody($uri, $data, $type);

                        // Send request to the socket
                        $res = $this->makeRequest($body);
                        if (!is_bool($res)) {
                            $res_.= "Error: Socket write failed\n".$res;
                        }
                        $this->makeSocket();
                    }
                    fclose($this->socket);

                    // If we have had any errors return these
                    if ($res_ != "") {
                        $res = $res_;
                    }
                    return $res;
                }
            }
            else {
                return "Error: Socket could not be created\n".$socket_made;
            }
        }
        return "No events to write";
    }

    /**
     * Writes requests to the socket
     * - If Retry is set to True, will attempt a second Write.
     *
     * @param string $data - Data that we are sending to the socket.
     * @param bool $retry - If we want to allow the function to make a second attempt.
     * @return bool - Returns if write was successful.
     */
    private function makeRequest($data, $retry_request_manager = NULL) {
        $bytes_written = 0;
        $bytes_total = strlen($data);
        $closed = false;
        $res_ = "";

        if ($retry_request_manager == NULL) {
            $retry_request_manager = new RetryRequestManager($this->max_retry_attempts, $this->retry_backoff_ms);
        }

        // Try to send data while bytes still have to be written to the socket
        while (!$closed && $bytes_written < $bytes_total) {
            try {
                $written = fwrite($this->socket, substr($data, $bytes_written));
            }
            catch (Exception $e) {
                $res_.= "Error: fwrite exception - ".$e."\n";
                $closed = true;
            }
            if (!isset($written) || $written === false) {
                $closed = true;
            }
            else {
                $bytes_written += $written;
            }
        }

        $status_code = 0;

        if (!$closed) {
            $res = fread($this->socket, 2048);
            $status_code = $this->parseStatusCode($res);

            // If debug is on store the request result
            if ($this->debug) {
                $this->storeRequestResults($res, $data);
            }
        }

        fclose($this->socket);

        // If the socket could not be written attempt again
        if (($closed && $retry_request_manager->shouldRetryFailedRequest()) ||
            $retry_request_manager->shouldRetryForStatusCode($status_code)) {
            $retry_request_manager->incrementRetryCount();
            $retry_request_manager->backoff();

            $socket_made = $this->makeSocket();
            if (is_bool($socket_made) && $socket_made) {
                return $this->makeRequest($data, $retry_request_manager);
            }
            else {
                return "Error: Socket could not be created\n".$socket_made."\n".$res_;
            }
        } else if ($closed) {
            return "Error: socket cannot be written after retry\n".$res_;
        } else if (!$retry_request_manager->isGoodStatusCode($status_code)) {
            return "Error: collector responded with status code ".$status_code.", won't retry";
        }

        return true;
    }

    /**
     * Creates a new socket connection to the collector.
     * - If the socket has previously failed do not allow another attempt
     *
     * @return bool|resource - Returns a socket resource or false if it fails.
     */
    private function makeSocket() {
        if ($this->socket_failed) {
            return "Error: socket cannot be created";
        }

        $protocol = $this->ssl ? "ssl" : "tcp";
        $port     = $this->ssl ? 443 : 80;

        try {
            $socket = pfsockopen($protocol."://".$this->uri, $port, $errno, $errstr, $this->timeout);
            if ($errno != 0) {
                $this->socket_failed = true;
                return "Error: socket creation failed on error number - ".$errno;
            }
            $this->socket = $socket;
            return true;
        }
        catch (Exception $e) {
            $this->socket_failed = true;
            return "Error: socket exception - ".$e;
        }
    }

    /**
     * Builds a Request which will be sent to the socket.
     *
     * @param string $uri - Collector URI to be used for the request
     * @param string|array $data - Data to be included in the Request
     * @param string $type - Type of request to be made (POST || GET)
     * @return string - Returns the request body
     */
    private function getRequestBody($uri, $data, $type) {
        if ($type == "POST") {
            $req = "POST http://".$uri.self::POST_PATH." ";
            $req.= "HTTP/1.1\r\n";
            $req.= "Host: ".$uri."\r\n";
            $req.= "Content-Type: ".self::POST_CONTENT_TYPE."\r\n";
            $req.= "Content-length: ".strlen($data)."\r\n";
            if ($this->server_anonymization) $req.= self::SERVER_ANONYMIZATION.": *\r\n";
            $req.= "Accept: ".self::POST_ACCEPT."\r\n\r\n";
            $req.= $data."\r\n\r\n";
        }
        else {
            $req = "GET http://".$uri.self::GET_PATH."?".$data." ";
            $req.= "HTTP/1.1\r\n";
            $req.= "Host: ".$uri."\r\n";
            if ($this->server_anonymization) $req.= self::SERVER_ANONYMIZATION.": *\r\n";
            $req.= "Query: ".$data."\r\n";
            $req.= "\r\n";
        }
        return $req;
    }

    /**
     * Compiles events from buffer into a single string.
     *
     * @param array $buffer
     * @return string - Returns a json_encoded string with all of the events to be sent.
     */
    private function getPostRequest($buffer) {
        $data = json_encode(array("schema" => self::POST_REQ_SCHEMA, "data" => $buffer));
        return $data;
    }

    /**
     * Disables debug mode for the emitter
     * - If deleteLocal is true it will also empty
     *   the local cache of stored request codes and
     *   the associated payloads.
     * - Will then trigger a function in the base
     *   emitter class to clean out the physical
     *   debug records.
     *
     * @param bool $deleteLocal - Empty results array
     */
    public function turnOffDebug($deleteLocal) {
        $this->debug = false;
        if ($deleteLocal) {
            $this->requests_results = array();
        }

        // Switch debug off in Base Emitter Class
        $this->debugSwitch($deleteLocal);
    }

    // Socket Return Functions

    /**
     * Returns the collectors uri
     *
     * @return mixed
     */
    public function returnUri() {
        return $this->uri;
    }

    /**
     * Returns if the collector is using an ssl connection
     *
     * @return bool|null
     */
    public function returnSsl() {
        return $this->ssl;
    }

    /**
     * Returns the emitter timeout
     *
     * @return float|null
     */
    public function returnTimeout() {
        return $this->timeout;
    }

    /**
     * Returns the request type the emitter is using
     *
     * @return null|string
     */
    public function returnType() {
        return $this->type;
    }

    /**
     * Returns the socket resource
     *
     * @return resource|null
     */
    public function returnSocket() {
        return $this->socket;
    }

    /**
     * Returns if the socket has failed or not
     *
     * @return bool
     */
    public function returnSocketIsFailed() {
        return $this->socket_failed;
    }

    // Debug Functions

    /**
     * Returns the results array which contains all response codes and body payloads.
     *
     * @return array
     */
    public function returnRequestResults() {
        return $this->requests_results;
    }

    private function parseStatusCode($res) {
        $contents = explode("\n", $res);
        $status = explode(" ", $contents[0], 3);
        return $status[1];
    }

    /**
     * Sends the results string from the request and then gets the return code.
     *
     * @param string $res
     * @param string $data - Raw POST body we are writing to the socket
     */
    private function storeRequestResults($res, $data) {
        // Get the Response code...
        $contents = explode("\n", $res);
        $status = explode(" ", $contents[0], 3);
        $array["code"] = $status[1];

        // Store the Body Payload
        $contents = explode("\n", $data);
        if ($this->type == "POST") {
            $array["data"] = $contents[count($contents)-3];
        }
        else {
            $data = substr($contents[2], 7, -1);
            parse_str($data, $output);
            $array["data"] = json_encode($output);
        }

        // Push the response code and body payload into the results array
        array_push($this->requests_results, $array);
    }
}
