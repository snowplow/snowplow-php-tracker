<?php

/*
    Payload.php

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

class Payload extends Constants {
    
    // Payload Parameters
    private $nv_pairs;

    /**
     * Constructs a Payload object, contains an array in which event parameters are stored
     *
     * @param string|null $tstamp - Timestamp for event
     */
    public function __construct($tstamp = NULL) {
        // Construct a name-value pairs array
        $this->nv_pairs = array();

        // Add Time Stamp to array on event creation
        $this->add("dtm", ($tstamp != NULL) ? $tstamp : $_SERVER['REQUEST_TIME'] * 1000);
    }

    /**
     * Adds a single name-value pair to the payload
     *
     * @param string $name - Key for nv pair
     * @param string|int|bool $value - Value for nv pair
     */
    public function add($name, $value) {
        if ($value != NULL && $value != "") {
            $this->nv_pairs[$name] = $value;
        }
    }

    /**
     * Adds an array of name-value pairs to the payload
     *
     * @param array $dict - Single level array of name => value pairs
     */
    public function addDict($dict) {
        foreach($dict as $name => $value) {
            $this->add($name, $value);
        }
    }

    /**
     * Adds a JSON formatted array to the payload
     * Json encodes the array first (turns it into a string) and then will encode (or not) the string in base64
     *
     * @param array $json - Custom context for the event
     * @param bool $base_64 - If the payload is base64 encoded
     * @param string $name_encoded - Name of the field when encode_base64 is not set
     * @param string $name_not_encoded - Name of the field when encode_base64 is set
     */
    public function addJson($json, $base_64, $name_encoded, $name_not_encoded) {
        if ($json != null) {
            if ($base_64) {
                $this->add($name_encoded, base64_encode(json_encode($json)));
            }
            else {
                $this->add($name_not_encoded, json_encode($json));
            }
        }
    }

    /**
     * Returns the payload as an array of pairs which the emitter can use
     *
     * @return array - Returns the payloads nv_pairs array.
     */
    public function get() {
        return $this->nv_pairs;
    }
}
