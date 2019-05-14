<?php
/*
    PayloadTest.php

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

use Snowplow\Tracker\Payload;
use PHPUnit\Framework\TestCase;

/**
 * Tests the functions used to construct the event
 * payloads
 */
class PayloadTest extends TestCase {

    public function testPayloadAdd() {
        $event_payload = new Payload();
        $event_payload->add("sku", "WM5");
        $array = $event_payload->get();

        // Asserts
        $this->assertArrayHasKey("sku", $array);
        $this->assertEquals("WM5", $array["sku"]);
    }

    public function testPayloadAddDict() {
        $event_payload = new Payload();
        $dict_array = array("sku" => "WM5","price" => 500);
        $event_payload->addDict($dict_array);
        $array = $event_payload->get();

        // Asserts
        $this->assertArrayHasKey("sku", $array);
        $this->assertArrayHasKey("price", $array);
        $this->assertEquals("WM5", $array["sku"]);
        $this->assertEquals(500, $array["price"]);
    }

    public function testPayloadAddJson() {
        $event_payload = new Payload();
        $json_array = array(
            "sku" => "WM5",
            array(
                "price" => 500,
                "country" => "France"
            )
        );
        $json = json_encode($json_array);
        $json_base64 = base64_encode(json_encode($json_array));
        $event_payload->addJson($json_array, true, "name_encoded", "name_not_encoded");
        $event_payload->addJson($json_array, false, "name_encoded", "name_not_encoded");
        $array = $event_payload->get();

        // Asserts
        $this->assertArrayHasKey("name_encoded", $array);
        $this->assertArrayHasKey("name_not_encoded", $array);
        $this->assertEquals($json_base64, $array["name_encoded"]);
        $this->assertEquals($json, $array["name_not_encoded"]);
    }
}
