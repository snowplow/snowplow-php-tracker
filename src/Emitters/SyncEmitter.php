<?php
/*
    SyncEmitter.php

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
use Requests;
use Exception;

class SyncEmitter extends Emitter {

    // Emitter Parameters

    private $type;
    private $url;
    private $debug;
    private $requests_results;
    private $max_retry_attempts;
    private $retry_backoff_ms;
    private $server_anonymization;

    /**
     * Creates a Synchronous Emitter
     *
     * @param string $uri - Collector URI to be used for the request
     * @param string|null $protocol - Protocol to be used for the request (http || https)
     * @param string|null $type - Type of request to be made (POST || GET)
     * @param int|null $buffer_size - Number of events to buffer before making a POST request to collector
     * @param bool|null $debug - If debug is on
     * @param int|null $max_retry_attempts - The maximum number of times to retry a request. Defaults to 1.
     * @param int|null $retry_backoff_ms - The number of milliseconds to backoff before retrying a request. Defaults to 100ms.
     * @param bool|null $server_anonymization - Whether to enable Server Anonymization and not collect the IP or Network User ID. Defaults to false.
     */
    public function __construct($uri, $protocol = NULL, $type = NULL, $buffer_size = NULL, $debug = false, $max_retry_attempts = NULL, $retry_backoff_ms = NULL, $server_anonymization = false) {
        $this->type = $this->getRequestType($type);
        $this->url  = $this->getCollectorUrl($this->type, $uri, $protocol);
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
        $buffer = $buffer_size == NULL ? self::SYNC_BUFFER : $buffer_size;
        $this->setup("sync", $debug, $buffer);
    }

    /**
     * Sends data with the configured Request type
     *
     * @param array $buffer
     * @return bool|string $res - Return true or an error string
     */
    public function send($buffer) {
        if (count($buffer) > 0) {
            $res = true;
            $type = $this->type;
            if ($type == "GET") {
                $res_ = "";
                foreach ($buffer as $payload) {
                    $res = $this->curlRequest($this->updateStm($payload), $type);
                    if (!is_bool($res)) {
                        $res_.= $res;
                    }
                }
                if ($res_ != "") {
                    $res = $res_;
                }
            }
            else if ($type == "POST") {
                $data = $this->getPostRequest($this->batchUpdateStm($buffer));
                $res = $this->curlRequest($data, $type);
            }
            return $res;
        }
        return "No events to send";
    }

    // Send Functions

    /**
     * Use cURL to send data to the collector
     *
     * @param array $data - Is the array which is going to be sent in the Request
     * @param string $type - The type of the Request
     * @return bool|string - Return true if successful request or an error string
     */
    private function curlRequest($data, $type, $retry_request_manager = NULL) {
        $res = true;
        $res_ = "";

        if ($retry_request_manager == NULL) {
            $retry_request_manager = new RetryRequestManager($this->max_retry_attempts, $this->retry_backoff_ms);
        }

        // Create a cURL handle, set transfer options and execute
        $ch = curl_init($this->url);
        $header = array();
        if ($type == 'POST') {
            $json_data = json_encode($data);
            $header[] = 'Content-Type: '.self::POST_CONTENT_TYPE;
            $header[] = 'Accept: '.self::POST_ACCEPT;
            $header[] = 'Content-Length: '.strlen($json_data);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        }
        else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
            curl_setopt($ch, CURLOPT_URL, $this->url."?".http_build_query($data));
        }

        if ($this->server_anonymization) $header[] = self::SERVER_ANONYMIZATION.': *';
        if ($header) curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);

        $status_code = 0;

        if (!curl_errno($ch)) {
            $info = curl_getinfo($ch);
            $status_code = $info['http_code'];
            if ($this->debug) {
                $this->storeRequestResults($status_code, $data);
                if ($info['http_code'] != 200) {
                    $res_.= "Sync ".$type." Request Failed: ".$status_code;
                }
            }
        }
        else {
            if ($this->debug) {
                $this->storeRequestResults(404, $data);
            }
            $res_.="Sync ".$type." Request Failed: ".curl_error($ch);
        }
        // Close handle
        curl_close($ch);

        // Retry request if necessary
        if ($retry_request_manager->shouldRetryForStatusCode($status_code)) {
            $retry_request_manager->incrementRetryCount();
            $retry_request_manager->backoff();

            return $this->curlRequest($data, $type, $retry_request_manager);
        }

        // If request failed return error string, else return true
        if ($res_ != "") {
            $res = $res_;
        }
        return $res;
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

    // Return Functions

    /**
     * Returns an array which has been formatted to be ready for a POST Request
     *
     * @param array $buffer
     * @return array - POST Request formatted array
     */
    private function getPostRequest($buffer) {
        $data = array("schema" => self::POST_REQ_SCHEMA, "data" => $buffer);
        return $data;
    }

    // Sync Return Functions

    /**
     * Returns the Emitter Collector URL
     *
     * @return string
     */
    public function returnUrl() {
        return $this->url;
    }

    /**
     * Returns the Emitter HTTP Request Type
     *
     * @return null|string
     */
    public function returnType() {
        return $this->type;
    }

    // Debug Functions

    /**
     * Returns the array of stored results from a request
     *
     * @return array - Dynamic array of stored results
     */
    public function returnRequestResults() {
        return $this->requests_results;
    }

    /**
     * Stores all of the parameters of the Request Response into a Dynamic Array for use in unit testing.
     *
     * @param int $code - Is the response from a GET or POST request
     * @param array $data - The payload array that is being sent
     */
    private function storeRequestResults($code, array $data) {
        $array["code"] = $code;
        $array["data"] = json_encode($data);
        array_push($this->requests_results, $array);
    }
}
