<?php
/*
    CurlEmitter.php

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

class CurlEmitter extends Emitter {

    // Emitter Parameters

    private $type;
    private $url;
    private $debug;
    private $requests_results;
    private $debug_payloads;
    private $server_anonymization;

    // Curl Specific Parameters

    private $final_payload_buffer = array();
    private $curl_buffer = array();
    private $curl_limit;
    private $rolling_window;
    private $curl_timeout;

    /**
     * Constructs an async curl emitter.
     *
     * @param string $uri - Collector URI
     * @param string|null $protocol - What protocol we are using for the collector
     * @param string|null $type - The type of request we will be making to the collector
     * @param int|null $buffer_size - Emitter buffer size
     * @param bool $debug - Debug mode
     * @param int|null $curl_timeout - Maximum time the request is allowed to take, in seconds
     * @param bool|null $server_anonymization - Whether to enable Server Anonymization and not collect the IP or Network User ID. Defaults to false.
     */
    public function __construct($uri, $protocol = NULL, $type = NULL, $buffer_size = NULL, $debug = false, $curl_timeout = NULL, $server_anonymization = false) {
        $this->type           = $this->getRequestType($type);
        $this->url            = $this->getCollectorUrl($this->type, $uri, $protocol);
        $this->curl_limit     = $this->type == "POST" ? self::CURL_AMOUNT_POST : self::CURL_AMOUNT_GET;
        $this->rolling_window = $this->type == "POST" ? self::CURL_WINDOW_POST : self::CURL_WINDOW_GET;
        $this->curl_timeout   = $curl_timeout;

        $this->server_anonymization = $server_anonymization;

        // If debug is on create a requests_results array
        if ($debug === true) {
            $this->debug = true;
            $this->debug_payloads = array();
            $this->requests_results = array();
        }
        else {
            $this->debug = false;
        }
        $buffer = $buffer_size == NULL ? self::CURL_BUFFER : $buffer_size;
        $this->setup("curl", $debug, $buffer);
    }

    /**
     * Push event buffers into curls and store them
     * - Wait until we have an allotted amount of curls before executing
     * - Or force the execution of the curl emitter
     *
     * @param $buffer - An array of events we are going to convert into curl resources
     * @param bool $curl_send - Whether or not we are going to send the buffered curl
     *                          objects before we reach the limit
     * @return bool|string - Either true or an error string
     */
    public function send($buffer, $curl_send = false) {
        $type = $this->type;
        $debug = $this->debug;

        // If the sent buffer contains events...
        if (count($buffer) > 0) {
            if ($type == "POST") {
                array_push($this->final_payload_buffer, $buffer);
            }
            else {
                foreach ($buffer as $event) {
                    array_push($this->final_payload_buffer, $event);
                }
            }
        }

        if (count($this->final_payload_buffer) >= $this->curl_limit) {
            return $this->mkCurlRequests($this->final_payload_buffer, $type, $debug);
        }
        else if ($curl_send === true) {
            if (count($this->final_payload_buffer) > 0) {
                return $this->mkCurlRequests($this->final_payload_buffer, $type, $debug);
            }
            else {
                return "Error: No curls to send.";
            }
        }
        return "Error: Still adding to the curl buffer; count ".count($this->final_payload_buffer)." - limit ".$this->curl_limit;
    }

    /**
     * Makes curl requests from payloads to be sent.
     *
     * @param array $payloads - Array of payloads to be sent
     * @param string $type - Type of requests to be made
     * @param bool $debug - If debug is on or not
     * @return bool|string - Returns true or a string of errors
     */
    private function mkCurlRequests($payloads, $type, $debug) {
        // Empty the global buffer.
        $this->final_payload_buffer = array();

        if ($type == 'POST') {
            foreach ($payloads as $buffer) {
                $payload = $this->getPostRequest($this->batchUpdateStm($buffer));
                $curl = $this->getCurlRequest($payload, $type);
                array_push($this->curl_buffer, $curl);

                // If debug is on; store the handle and the payload
                if ($debug) {
                    array_push($this->debug_payloads, array("handle" => $curl, "payload" => $payload));
                }
            }
        }
        else {
            foreach ($payloads as $event) {
                $payload = http_build_query($this->updateStm($event));
                $curl = $this->getCurlRequest($payload, $type);
                array_push($this->curl_buffer, $curl);
            }
        }
        return $this->rollingCurl($this->curl_buffer, $debug);
    }

    /**
     * Asynchronously sends curl requests.
     * - Prevents the queue from being held up by
     *   starting new requests as soon as any are done.
     *
     * @param array $curls - Array of curls to be sent
     * @param bool $debug - If Debug is on or not
     * @return bool|string - Returns true or a string of errors
     */
    private function rollingCurl($curls, $debug) {
        // Empty the global buffer.
        $this->curl_buffer = array();

        // Create a results string to log potential errors
        $res = true;
        $res_ = "";

        // Rolling Window == How many requests concurrently.
        $rolling_window = $this->rolling_window;
        $master = curl_multi_init();

        // Add cUrls to handler.
        $limit = ($rolling_window <= count($curls)) ? $rolling_window : count($curls);
        for ($i = 0; $i < $limit; $i++) {
            $ch = $curls[$i];
            curl_multi_add_handle($master, $ch);
        }

        // Execute the rolling curl
        $running = 0;
        do {
            do {
                $execrun = curl_multi_exec($master, $running);
            } while ($execrun == CURLM_CALL_MULTI_PERFORM);

            while ($done = curl_multi_info_read($master)) {
                if ($debug) {
                    $info = curl_getinfo($done['handle']);
                    if ($info['http_code'] != 200) {
                        $res_.= "Error: Curl Request Failed with response code - ".$info['http_code']."\n";
                    }

                    if ($this->type == "POST") {
                        $data = $this->getDebugData($done);
                    }
                    else {
                        $data = $info['url'];
                    }
                    $this->storeRequestResults($info['http_code'], $data);
                }

                // If there are still curls in the queue add them to the  multi curl handler
                if ($i < count($curls)) {
                    $ch = $curls[$i++];
                    curl_multi_add_handle($master, $ch);
                }
                curl_multi_remove_handle($master, $done['handle']);
                curl_close($done['handle']);
            }
        } while ($running);
        curl_multi_close($master);

        if ($res_ != "") {
            $res = $res_;
        }

        return $res;
    }

    /**
     * Generates and returns a curl resource
     *
     * @param string $payload - Data included in request
     * @param string $type - Type of request to be made
     * @return resource
     */
    private function getCurlRequest($payload, $type) {
        $ch = curl_init($this->url);
        $header = array();
        if ($type == "POST") {
            $header[] = 'Content-Type: '.self::POST_CONTENT_TYPE;
            $header[] = 'Accept: '.self::POST_ACCEPT;
            $header[] = 'Content-Length: '.strlen($payload);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }
        else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
            curl_setopt($ch, CURLOPT_URL, $this->url."?".$payload);
        }

        if ($this->server_anonymization) $header[] = self::SERVER_ANONYMIZATION.": *";

        if ($header) curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($this->curl_timeout != NULL) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->curl_timeout);
        }
        return $ch;
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

        // Switch Debug off in Base Emitter Class
        $this->debugSwitch($deleteLocal);
    }

    // Curl Return Functions

    /**
     * Returns the collector url
     *
     * @return string
     */
    public function returnUrl() {
        return $this->url;
    }

    /**
     * Returns the type of Request we will be making
     *
     * @return null|string
     */
    public function returnType() {
        return $this->type;
    }

    /**
     * Returns the current array of curls we have
     *
     * @return array
     */
    public function returnCurlBuffer() {
        return $this->curl_buffer;
    }

    /**
     * Set the amount of times we need to reach the buffer limit (buffer_size) before we initiate sending
     * 
     * @param int $curl_limit
     */
    public function setCurlAmount($curl_limit) {
        $this->curl_limit = $curl_limit;
    }

    /**
     * Returns the amount of times we need to reach the buffer limit (buffer_size) before we initiate sending
     *
     * @return int
     */
    public function returnCurlAmount() {
        return $this->curl_limit;
    }

    /**
     * Set the max amount of concurrent curl requests being made
     * 
     * @param int $rolling_window
     */
    public function setRollingWindow($rolling_window) {
        $this->rolling_window = $rolling_window;
    }

    /**
     * Returns the max amount of concurrent curl requests being made
     *
     * @return int
     */
    public function returnRollingWindow() {
        return $this->rolling_window;
    }

    // Debug Functions

    /**
     * Returns the array of stored results from a request
     *
     * @return array
     */
    public function returnRequestResults() {
        return $this->requests_results;
    }

    /**
     * Returns the payload associated with the finished curl
     *
     * @param array $done - The array made by finishing a curl
     * @return string - Payload
     */
    private function getDebugData($done) {
        $data = "";
        for ($i = 0; $i < count($this->debug_payloads); $i++) {
            $debug_payload = $this->debug_payloads[$i];
            if ($debug_payload["handle"] == $done['handle']) {
                $data = $debug_payload["payload"];

                // Delete the curl handle & payload from storage
                unset($this->debug_payloads[$i]);

                // Re-index the array after the deletion
                $this->debug_payloads = array_values($this->debug_payloads);
            }
        }
        return $data;
    }

    /**
     * Stores curl request response code
     *
     * @param $code
     * @param $data
     */
    private function storeRequestResults($code, $data) {
        $array["code"] = $code;
        if ($this->type == "GET") {
            $temp = substr($data,(strpos($data,"?")+1),-1);
            parse_str($temp, $output);
            $array["data"] = json_encode($output);
        }
        else {
            $array["data"] = $data;
        }
        array_push($this->requests_results, $array);
    }
}
