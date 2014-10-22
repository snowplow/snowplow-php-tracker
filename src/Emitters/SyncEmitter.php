<?php
/*
    SyncEmitter.php

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
use Requests;
use Exception;

class SyncEmitter extends Emitter {
    // Emitter Parameters
    private $url;
    private $type;
    private $protocol;

    /**
     * Creates a Synchronous Emitter
     *
     * @param string $uri
     * @param string|null $protocol
     * @param string|null $type
     * @param int|null $buffer_size
     * @param bool $debug
     */
    public function __construct($uri, $protocol = NULL, $type = NULL, $buffer_size = NULL, $debug = false) {
        $this->type = ($type == NULL) ? "POST" : $type;
        $this->protocol = ($protocol == NULL) ? "http" : $protocol;
        $this->url = $this->getUrl($uri, $this->protocol, $this->type);

        // If Debug is on create a requests_results
        $this->debug = $debug;
        if ($debug == true) {
            $this->debug = true;
            $this->requests_results = array();
        }
        $buffer = $this->getBufferSize($this->type, $buffer_size, 50, 1);
        $this->setup("sync", $debug, $buffer);
    }

    /**
     * Sends data with the configured Request type
     *
     * @param array $buffer
     * @return bool $res
     */
    public function send($buffer) {
        if (count($buffer) > 0) {
            $res = true;
            $type = $this->type;
            if ($type == "GET") {
                $res_ = "";
                foreach ($buffer as $payload) {
                    $res = $this->getRequest($payload);
                    if (!is_bool($res)) {
                        $res_.= $res;
                    }
                }
                if ($res_ != "") {
                    $res = $res_;
                }
            }
            else if ($type == "POST") {
                $data = $this->getPostRequest($buffer);
                $res = $this->postRequest($data);
            }
            return $res;
        }
        return "No events to write";
    }

    // Send Functions
    /**
     * Using a GET Request sends the data to a collector
     *
     * @param array $data - The array which is going to be sent in the GET Request
     * @return bool - Return whether the request was successful
     */
    private function getRequest($data) {
        try {
            $r = Requests::get($this->url.http_build_query($data));
            if ($this->debug) {
                $this->storeRequestResults($r, $data);
            }
            if ($r->status_code == 200) {
                return true;
            }
            else {
                return "Get Failed: ".$r->status_code;
            }
        }
        catch (Exception $e) {
            return "Get Failed: ".$e;
        }
    }

    /**
     * Using a POST Request sends the data to a collector
     *
     * @param array $data - Is the array which is going to be sent in the POST Request
     * @return bool - Return whether the request was successful
     */
    private function postRequest($data) {
        $header = array('Content-Type' => 'application/json; charset=utf-8');
        try {
            $r = Requests::post($this->url, $header, json_encode($data));
            if ($this->debug) {
                $this->storeRequestResults($r, $data);
            }
            if ($r->status_code == 200) {
                return true;
            }
            else {
                return "Post Failed: ".$r->status_code;
            }
        }
        catch (Exception $e) {
            return "Post Failed: ".$e;
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

        // Switch Debug off in Base Emitter Class
        $this->debugSwitch($deleteLocal);
    }

    // Return Functions
    /**
     * Returns the collector URL based on: request type, protocol and host given
     * IF a bad type is given in emitter creation returns NULL
     *
     * @param string $uri - The Collector URI to be used for tracking
     * @param string $protocol - The collector protocol to use
     * @param string $type - The type of request to be made
     * @return string|null collector_url - Returns the Collector URL
     */
    private function getUrl($uri, $protocol, $type) {
        switch ($type) {
            case "POST": return $protocol."://".$uri.self::POST_PATH;
                break;
            case "GET": return $protocol."://".$uri."/i?";
                break;
            default: return NULL;
        }
    }

    /**
     * Returns an array which has been formatted to be ready for a POST Request
     *
     * @param array $buffer
     * @return array - POST Request formatted array
     */
    private function getPostRequest($buffer) {
        $data = array("schema" => self::POST_REQ_SCEHMA, "data" => $buffer);
        return $data;
    }

    /**
     * Sets the buffer size for the emitter
     *
     * @param int $buffer_size
     * @param int $default_1
     * @param int $default_2
     * @param string $type
     * @return int - Returns the calculated buffer size
     */
    private function getBufferSize($type, $buffer_size, $default_1, $default_2) {
        if ($buffer_size == NULL) {
            if ($type == "POST") {
                return $default_1;
            }
            else {
                return $default_2;
            }
        }
        else {
            return $buffer_size;
        }
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
     * Returns the Emitter HTTP Protocol
     *
     * @return null|string
     */
    public function returnProtocol() {
        return $this->protocol;
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
     * @param \Requests_Response $r - Is the response from a GET or POST request
     * @param array $data - The payload array that is being sent
     */
    private function storeRequestResults(\Requests_Response $r, array $data) {
        $array["code"] = $r->status_code;
        $array["data"] = json_encode($data);
        array_push($this->requests_results, $array);
    }
}
