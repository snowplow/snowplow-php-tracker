<?php
/*
    CurlEmitter.php

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

class CurlEmitter extends Emitter{
    // Emitter Constants
    const CURL_AMOUNT_POST = 50;
    const CURL_AMOUNT_GET = 250;
    const CURL_WINDOW_POST = 10;
    const CURL_WINDOW_GET = 30;

    // Emitter Parameters
    private $url;
    private $ssl;
    private $type;

    // Curl Specific Parameters
    private $curl_buffer = array();
    private $curl_amount;
    private $rolling_window;

    /**
     * Constructs an async curl emitter.
     *
     * @param string $uri
     * @param bool|null $ssl
     * @param string|null $type
     * @param int|null $buffer_size
     * @param bool $debug
     */
    public function __construct($uri, $ssl = NULL, $type = NULL, $buffer_size = NULL, $debug = false) {
        $this->ssl = ($ssl != NULL) ? (bool) $ssl : false;
        $this->type = ($type != NULL) ? $type : "POST";
        $this->url = $this->getUrl($this->type, $this->ssl, $uri);

        // If Debug is on create a requests_results
        $this->debug = $debug;
        if ($debug == true) {
            $this->debug = true;
            $this->debug_payloads = array();
            $this->requests_results = array();
        }
        $buffer = ($buffer_size != NULL) ? $buffer_size : 50;
        $this->setup("curl", $debug, $buffer);
    }

    /**
     * Push event buffers into curls and store them
     * - Wait until we have an allotted amount of curls before executing
     * - Or force the execution of the curl flush
     *
     * @param $buffer
     * @param string $nuid - The Trackers network user id
     * @param bool $force
     * @return bool
     */
    public function send($buffer, $nuid, $force = false) {
        $type = $this->type;
        $debug = $this->debug;
        if (count($buffer) > 0) {
            if ($type == "POST") {
                $payload = $this->getPostRequest($buffer);
                $curl = $this->getCurlRequest($payload, $type, $nuid);
                array_push($this->curl_buffer, $curl);
                if ($debug) {
                    array_push($this->debug_payloads, array("handle" => $curl, "payload" => $payload));
                }
            }
            else {
                foreach ($buffer as $event) {
                    $payload = http_build_query($event);
                    $curl = $this->getCurlRequest($payload, $type, $nuid);
                    array_push($this->curl_buffer, $curl);
                }
            }
        }
        if (count($this->curl_buffer) >= $this->curl_amount) {
            return $this->rollingCurl($this->curl_buffer, $debug);
        }
        else if ($force) {
            if (count($this->curl_buffer) > 0) {
                return $this->rollingCurl($this->curl_buffer ,$debug);
            }
            else {
                return "No curls to send!";
            }
        }
        return "Still adding to the curl buffer: ".count($this->curl_buffer);
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

        do {
            $execrun = curl_multi_exec($master, $running);
            while ($execrun == CURLM_CALL_MULTI_PERFORM);
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
     * @param string $nuid - The Trackers network user id
     * @return resource
     */
    private function getCurlRequest($payload, $type, $nuid) {
        $ch = curl_init($this->url);
        if ($type == "POST") {
            $header = array(
                'Content-Type: application/json; charset=utf-8',
                'Accept: application/json',
                'Content-Length: '.strlen($payload));
            if ($nuid != "") {
                array_push($header, 'Cookie: sp='.$nuid);
            }
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }
        else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
            curl_setopt($ch, CURLOPT_URL, $this->url."?".$payload);
            if ($nuid != "") {
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Cookie: sp='.$nuid));
            }
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        return $ch;
    }

    /**
     * Compiles events from buffer into a single string.
     *
     * @param array $buffer
     * @return string - Returns a json_encoded string with all of the events to be sent.
     */
    private function getPostRequest($buffer) {
        $data = json_encode(array("schema" => self::POST_REQ_SCEHMA, "data" => $buffer));
        return $data;
    }

    /**
     * Makes and returns the collector url
     * - Sets the curl buffer amount
     * - Sets the rolling window for the curl emitter
     *
     * @param string $type
     * @param bool $ssl
     * @param string $uri
     * @return null|string
     */
    private function getUrl($type, $ssl, $uri) {
        $protocol = ($ssl) ? "https" : "http";
        if ($type == "POST") {
            $this->curl_amount = self::CURL_AMOUNT_POST;
            $this->rolling_window = self::CURL_WINDOW_POST;
            return $protocol."://".$uri.self::POST_PATH;
        }
        else if ($type == "GET") {
            $this->curl_amount = self::CURL_AMOUNT_GET;
            $this->rolling_window = self::CURL_WINDOW_GET;
            return $protocol."://".$uri."/i";
        }
        else {
            return NULL;
        }
    }

    /**
     * Disables debug mode
     *
     * @param bool $deleteLocal - Empty results array
     */
    public function turnOfDebug($deleteLocal) {
        $this->debug = false;
        if ($deleteLocal) {
            $this->requests_results = array();
        }
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
     * Returns whether or not we are using ssl
     *
     * @return bool
     */
    public function returnSsl() {
        return $this->ssl;
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
     * Returns the amount of curls we need before sending
     *
     * @return int
     */
    public function returnCurlAmount() {
        return $this->curl_amount;
    }

    /**
     * Returns the amount of simultaneous curls we send
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
                unset($this->debug_payloads[$i]);
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
