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
use Requests;

class Emitter {
    const DEFAULT_REQ_TYPE = "POST";
    const DEFAULT_PROTOCOL = "http";
    const DEFAULT_BUFFER_SIZE = 10;
    const BASE_SCHEMA_PATH = "iglu:com.snowplowanalytics.snowplow";
    const SCHEMA_TAG = "jsonschema";

    /**
     * Constructs an emitter object which will be used to send event data to a collector
     *
     * @param string $collector_uri - URI of the collector
     * @param string|null $req_type - Request Type (POST or GET)
     * @param string|null $protocol - Protocol to be used (HTTP or HTTPS)
     * @param int|null $buffer_size - Buffer Size, amount of events to be stored before sending
     */
    public function __construct($collector_uri, $req_type = NULL, $protocol = NULL, $buffer_size = NULL) {
        $this->post_request_schema = self::BASE_SCHEMA_PATH."/payload_data/".self::SCHEMA_TAG."/1-0-0";
        $this->req_type = ($req_type != null) ? $req_type : self::DEFAULT_REQ_TYPE;
        $this->protocol = ($protocol != null) ? $protocol : self::DEFAULT_PROTOCOL;
        $this->collector_url = $this->returnCollectorUrl($collector_uri);
        if ($buffer_size == NULL) {
            if ($this->req_type == "POST") {
                $this->buffer_size = self::DEFAULT_BUFFER_SIZE;
            }
            else {
                $this->buffer_size = 1;
            }
        }
        else {
            $this->buffer_size = (int) $buffer_size;
        }
        $this->buffer = array();
        $this->requests_results = array();
    }

    /**
     * Pushes the event payload into the emitter buffer.
     * When buffer is full it flushes the buffer.
     *
     * @param array $final_payload - Takes the Trackers Payload as a parameter
     */
    public function sendEvent($final_payload) {
        array_push($this->buffer, $final_payload);
        if (count($this->buffer) >= $this->buffer_size) {
            $this->flush();
        }
    }

    /**
     * Flushes the event buffer of the emitter
     * Checks which send type the emitter is using and forwards data accordingly
     * Resets the buffer to nothing after flushing
     */
    public function flush() {
        if (count($this->buffer) != 0 ) {
            if ($this->req_type == "POST") {
                $data = $this->returnPostRequest();
                $this->postRequest($data);
            }
            else if ($this->req_type == "GET") {
                foreach ($this->buffer as $data) {
                    $this->getRequest($data);
                }
            }
            $this->buffer = array();
        }
    }

    // Send Functions
    /**
     * Using a GET Request sends the data to a collector
     *
     * @param array $data - The array which is going to be sent in the GET Request
     */
    private function getRequest($data) {
        $r = Requests::get($this->collector_url.http_build_query($data));
        $this->storeRequestResults($r);
    }

    /**
     * Using a POST Request sends the data to a collector
     *
     * @param array $data - Is the array which is going to be sent in the POST Request
     */
    private function postRequest($data) {
        $header = array('Content-Type' => 'application/json; charset=utf-8');
        $r = Requests::post($this->collector_url, $header, json_encode($data));
        $this->storeRequestResults($r);
    }

    // Make Functions
    /**
     * Returns the collector URL based on: request type, protocol and host given
     * IF a bad type is given in emitter creation returns NULL
     *
     * @param string $host - The Collector URI to be used for tracking
     * @return string|null collector_url - Returns the Collector URL
     */
    private function returnCollectorUrl($host) {
        switch ($this->req_type) {
            case "POST": return $this->protocol."://".$host."/com.snowplowanalytics.snowplow/tp2";
                break;
            case "GET": return $this->protocol."://".$host."/i?";
                break;
            default: return NULL;
        }
    }

    /**
     * Returns an array which has been formatted to be ready for a POST Request
     *
     * @return array - POST Request formatted array
     */
    private function returnPostRequest() {
        $data_post_request = array("schema" => $this->post_request_schema, "data" => array());
        foreach($this->buffer as $event) {
            array_push($data_post_request["data"], $event);
        }
        return $data_post_request;
    }

    // Debug Functions
    /**
     * Returns the array of stored results from a request
     *
     * @return array - Dynamic array of stored results
     */
    public function getRequestResults() {
        return $this->requests_results;
    }

    /**
     * Stores all of the parameters of the Request Response into a Dynamic Array for use in unit testing.
     *
     * @param \Requests_Response $r - Is the response from a GET or POST request
     */
    private function storeRequestResults(\Requests_Response $r) {
        $array = array();
        $array["url"] = $r->url;
        $array["code"] = $r->status_code;
        $array["headers"] = $r->headers;
        $array["body"] = $r->body;
        $array["raw"] = $r->raw;
        array_push($this->requests_results, $array);
    }
}
