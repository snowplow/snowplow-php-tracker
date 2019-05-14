<?php
/*
    IntegrationTest.php

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

use Snowplow\Tracker\Tracker;
use Snowplow\Tracker\Subject;
use Snowplow\Tracker\Emitters\SocketEmitter;
use Snowplow\Tracker\Emitters\SyncEmitter;
use Snowplow\Tracker\Emitters\CurlEmitter;
use PHPUnit\Framework\TestCase;

/**
 * This test asserts that for every type of event the tracker
 * can record we are getting the desired output.
 *
 * Tests every event type for:
 * - Sync Emitter
 * - Socket Emitter
 * - Curl Emitter
 */
class IntegrationTest extends TestCase {

    // Helper Functions & Values

    private $payload_schema = "iglu:com.snowplowanalytics.snowplow/payload_data/jsonschema/1-0-2";

    // Tracker, Emitter & Context Builders

    private function getTracker($type) {
        $subject = new Subject();
        $e1 = $this->getSyncEmitter($type);
        $e2 = $this->getSocketEmitter($type);
        $e3 = $this->getCurlEmitter($type);

        $emitters = array($e1, $e2, $e3);
        return new Tracker($emitters, $subject, NULL, NULL, false);
    }

    private function getSyncEmitter($type) {
        return new SyncEmitter("localhost:4545", NULL, $type, NULL, true);
    }

    private function getSocketEmitter($type) {
        return new SocketEmitter("localhost:4545", NULL, $type, NULL, NULL, true);
    }

    private function getCurlEmitter($type) {
        return new CurlEmitter("localhost:4545", NULL, $type, NULL, true);
    }

    // Pre-built Context & Unstruct-Event

    private function getContext() {
        return array(
            "schema" => "iglu:com.acme_company/context_example/jsonschema/2.1.1",
            "data" => array("movie_name" => "Solaris", "poster_country" => "JP", "poster_year" => 1978)
        );
    }

    private function getUnstructEvent() {
        return array(
            "schema" => "com.example_company/save-game/jsonschema/1.0.2",
            "data" => array("save_id" => "4321", "level" => 23, "difficultyLevel" => "HARD", "dl_content" => True)
        );
    }

    // Tests

    public function testPvGet() {
        $tracker = $this->getTracker("GET");
        $tracker->trackPageView("www.example.com", "example", "www.referrer.com");
        $tracker->flushEmitters();
        $this->requestResultAssert($tracker->returnEmitters(), "pv-get");
        $tracker->turnOffDebug(true);
    }

    public function testPvPost() {
        $tracker = $this->getTracker("POST");
        $tracker->trackPageView("www.example.com", "example", "www.referrer.com");
        $tracker->flushEmitters();
        $this->requestResultAssert($tracker->returnEmitters(), "pv-post");
        $tracker->turnOffDebug(true);
    }

    public function testSvGet() {
        $tracker = $this->getTracker("GET");
        $tracker->trackScreenView("HUD", "Level: 23", $this->getContext());
        $tracker->flushEmitters();
        $this->requestResultAssert($tracker->returnEmitters(), "sv-get");
        $tracker->turnOffDebug(true);
    }

    public function testSvPost() {
        $tracker = $this->getTracker("POST");
        $tracker->trackScreenView("HUD", "Level: 23", $this->getContext());
        $tracker->flushEmitters();
        $this->requestResultAssert($tracker->returnEmitters(), "sv-post");
        $tracker->turnOffDebug(true);
    }

    public function testStructGet() {
        $tracker = $this->getTracker("GET");
        $tracker->trackStructEvent("shop", "add-to-basket", NULL, "pcs", 2, $this->getContext());
        $tracker->flushEmitters();
        $this->requestResultAssert($tracker->returnEmitters(), "struct-get");
        $tracker->turnOffDebug(true);
    }

    public function testStructPost() {
        $tracker = $this->getTracker("POST");
        $tracker->trackStructEvent("shop", "add-to-basket", NULL, "pcs", 2, $this->getContext());
        $tracker->flushEmitters();
        $this->requestResultAssert($tracker->returnEmitters(), "struct-post");
        $tracker->turnOffDebug(true);
    }

    public function testUnstructGet() {
        $tracker = $this->getTracker("GET");
        $tracker->trackUnstructEvent($this->getUnstructEvent(), $this->getContext());
        $tracker->flushEmitters();
        $this->requestResultAssert($tracker->returnEmitters(), "unstruct-get");
        $tracker->turnOffDebug(true);
    }

    public function testUnstructPost() {
        $tracker = $this->getTracker("POST");
        $tracker->trackUnstructEvent($this->getUnstructEvent(), $this->getContext());
        $tracker->flushEmitters();
        $this->requestResultAssert($tracker->returnEmitters(), "unstruct-post");
        $tracker->turnOffDebug(true);
    }

    public function testEcommerceGet() {
        $tracker = $this->getTracker("GET");
        $tracker->trackEcommerceTransaction("test_order_id_1", 200, "GBP", "affiliation_1", "tax_value_1",
            "shipping_1", "city_1", "state_1", "France",
            array(
                array("name" => "name_1","category" => "category_1","currency" => "GBP",
                    "price" => 100,"sku" => "sku_1","quantity" => 1)
            ),
            $this->getContext()
        );
        $tracker->flushEmitters();
        $this->requestResultAssert($tracker->returnEmitters(), "ecommerce-get");
        $tracker->turnOffDebug(true);
    }

    public function testEcommercePost() {
        $tracker = $this->getTracker("POST");
        $tracker->trackEcommerceTransaction("test_order_id_1", 200, "GBP", "affiliation_1", "tax_value_1",
            "shipping_1", "city_1", "state_1", "France",
            array(
                array("name" => "name_1","category" => "category_1","currency" => "GBP",
                    "price" => 100,"sku" => "sku_1","quantity" => 1)
            ),
            $this->getContext()
        );
        $tracker->flushEmitters();
        $this->requestResultAssert($tracker->returnEmitters(), "ecommerce-post");
        $tracker->turnOffDebug(true);
    }

    public function testDebugSwitch() {
        $tracker = $this->getTracker("GET");
        $tracker->trackPageView("www.example.com", "example", "www.referrer.com");
        $tracker->flushEmitters();
        $tracker->turnOffDebug(true);
        $emitters = $tracker->returnEmitters();
        foreach ($emitters as $emitter) {
            $results = $emitter->returnRequestResults();
            $this->assertEquals(0, count($results));
        }
    }

    // ASSERTION FUNCTIONS

    // Assert Switcher - used as we need to loop through multiple sets of data to test every emitter

    private function requestResultAssert($emitters, $test) {
        foreach($emitters as $emitter) {
            $results = $emitter->returnRequestResults();
            foreach ($results as $result) {
                switch ($test) {
                    case "pv-get" : $this->assertPageViewGet($result["data"]);
                        break;
                    case "pv-post" : $this->assertPageViewPost($result["data"]);
                        break;
                    case "sv-get" : $this->assertScreenViewGet($result["data"]);
                        break;
                    case "sv-post" : $this->assertScreenViewPost($result["data"]);
                        break;
                    case "struct-get" : $this->assertStructEventGet($result["data"]);
                        break;
                    case "struct-post" : $this->assertStructEventPost($result["data"]);
                        break;
                    case "unstruct-get" : $this->assertUnStructEventGet($result["data"]);
                        break;
                    case "unstruct-post" : $this->assertUnStructEventPost($result["data"]);
                        break;
                    case "ecommerce-get" : $this->assertEcommerceGet($result["data"]);
                        break;
                    case "ecommerce-post" : $this->assertEcommercePost($result["data"]);
                        break;
                }
            }
        }
    }

    // Page View Key Asserts

    private function assertPageViewGet($data) {
        $data = json_decode($data, true);
        $this->assertArrayHasKey("eid", $data);
        $this->assertArrayHasKey("dtm", $data);
        $this->assertArrayHasKey("tv", $data);
        $this->assertArrayHasKey("p", $data);
        $this->assertArrayHasKey("e", $data);
        $this->assertArrayHasKey("url", $data);
        $this->assertArrayHasKey("page", $data);
        $this->assertArrayHasKey("refr", $data);
        $this->assertEquals("pv", $data["e"]);
        $this->assertEquals("www.example.com", $data["url"]);
        $this->assertEquals("example", $data["page"]);
        $this->assertEquals("www.referrer.com", $data["refr"]);
    }

    private function assertPageViewPost($data) {
        $data = json_decode($data, true);
        $this->assertArrayHasKey("schema", $data);
        $this->assertArrayHasKey("data", $data);
        $this->assertEquals($this->payload_schema, $data["schema"]);

        $data = $data["data"];

        foreach ($data as $event) {
            $this->assertArrayHasKey("eid", $event);
            $this->assertArrayHasKey("dtm", $event);
            $this->assertArrayHasKey("tv", $event);
            $this->assertArrayHasKey("p", $event);
            $this->assertArrayHasKey("e", $event);
            $this->assertArrayHasKey("url", $event);
            $this->assertArrayHasKey("page", $event);
            $this->assertArrayHasKey("refr", $event);
            $this->assertEquals("pv", $event["e"]);
            $this->assertEquals("www.example.com", $event["url"]);
            $this->assertEquals("example", $event["page"]);
            $this->assertEquals("www.referrer.com", $event["refr"]);
        }
    }

    // Screen View Key Asserts

    private function assertScreenViewGet($data) {
        $data = json_decode($data, true);
        $this->assertArrayHasKey("eid", $data);
        $this->assertArrayHasKey("dtm", $data);
        $this->assertArrayHasKey("tv", $data);
        $this->assertArrayHasKey("p", $data);
        $this->assertArrayHasKey("e", $data);
        $this->assertArrayHasKey("co", $data);
        $this->assertArrayHasKey("ue_pr", $data);
        $this->assertEquals("ue", $data["e"]);
    }

    private function assertScreenViewPost($data) {
        $data = json_decode($data, true);
        $this->assertArrayHasKey("schema", $data);
        $this->assertArrayHasKey("data", $data);
        $this->assertEquals($this->payload_schema, $data["schema"]);

        $data = $data["data"];

        foreach ($data as $event) {
            $this->assertArrayHasKey("eid", $event);
            $this->assertArrayHasKey("dtm", $event);
            $this->assertArrayHasKey("tv", $event);
            $this->assertArrayHasKey("p", $event);
            $this->assertArrayHasKey("e", $event);
            $this->assertArrayHasKey("co", $event);
            $this->assertArrayHasKey("ue_pr", $event);
            $this->assertEquals("ue", $event["e"]);
        }
    }

    // Structured Event Key Asserts

    private function assertStructEventGet($data) {
        $data = json_decode($data, true);
        $this->assertArrayHasKey("eid", $data);
        $this->assertArrayHasKey("dtm", $data);
        $this->assertArrayHasKey("tv", $data);
        $this->assertArrayHasKey("p", $data);
        $this->assertArrayHasKey("e", $data);
        $this->assertArrayHasKey("se_ca", $data);
        $this->assertArrayHasKey("se_ac", $data);
        $this->assertArrayHasKey("se_pr", $data);
        $this->assertArrayHasKey("se_va", $data);
        $this->assertEquals("se", $data["e"]);
        $this->assertEquals("shop", $data["se_ca"]);
        $this->assertEquals("add-to-basket", $data["se_ac"]);
        $this->assertEquals("pcs", $data["se_pr"]);
        $this->assertEquals("2", $data["se_va"]);
    }

    private function assertStructEventPost($data) {
        $data = json_decode($data, true);
        $this->assertArrayHasKey("schema", $data);
        $this->assertArrayHasKey("data", $data);
        $this->assertEquals($this->payload_schema, $data["schema"]);

        $data = $data["data"];

        foreach ($data as $event) {
            $this->assertArrayHasKey("eid", $event);
            $this->assertArrayHasKey("dtm", $event);
            $this->assertArrayHasKey("tv", $event);
            $this->assertArrayHasKey("p", $event);
            $this->assertArrayHasKey("e", $event);
            $this->assertArrayHasKey("se_ca", $event);
            $this->assertArrayHasKey("se_ac", $event);
            $this->assertArrayHasKey("se_pr", $event);
            $this->assertArrayHasKey("se_va", $event);
            $this->assertEquals("se", $event["e"]);
            $this->assertEquals("shop", $event["se_ca"]);
            $this->assertEquals("add-to-basket", $event["se_ac"]);
            $this->assertEquals("pcs", $event["se_pr"]);
            $this->assertEquals("2", $event["se_va"]);
        }
    }

    // Unstructured Event Key Asserts
    private function assertUnStructEventGet($data) {
        $data = json_decode($data, true);
        $this->assertArrayHasKey("eid", $data);
        $this->assertArrayHasKey("dtm", $data);
        $this->assertArrayHasKey("tv", $data);
        $this->assertArrayHasKey("p", $data);
        $this->assertArrayHasKey("e", $data);
        $this->assertArrayHasKey("ue_pr", $data);
        $this->assertArrayHasKey("co", $data);
        $this->assertEquals("ue", $data["e"]);
    }

    private function assertUnStructEventPost($data) {
        $data = json_decode($data, true);
        $this->assertArrayHasKey("schema", $data);
        $this->assertArrayHasKey("data", $data);
        $this->assertEquals($this->payload_schema, $data["schema"]);

        $data = $data["data"];

        foreach ($data as $event) {
            $this->assertArrayHasKey("eid", $event);
            $this->assertArrayHasKey("dtm", $event);
            $this->assertArrayHasKey("tv", $event);
            $this->assertArrayHasKey("p", $event);
            $this->assertArrayHasKey("e", $event);
            $this->assertArrayHasKey("ue_pr", $event);
            $this->assertArrayHasKey("co", $event);
            $this->assertEquals("ue", $event["e"]);
        }
    }

    // Ecommerce Event Key Asserts
    private function assertEcommerceGet($data) {
        $data = json_decode($data, true);
        $this->assertArrayHasKey("eid", $data);
        $this->assertArrayHasKey("dtm", $data);
        $this->assertArrayHasKey("tv", $data);
        $this->assertArrayHasKey("p", $data);
        $this->assertArrayHasKey("e", $data);
        $this->assertArrayHasKey("co", $data);

        if ($data["e"] == "tr") {
            $this->assertArrayHasKey("tr_id", $data);
            $this->assertArrayHasKey("tr_tt", $data);
            $this->assertArrayHasKey("tr_cu", $data);
            $this->assertArrayHasKey("tr_af", $data);
            $this->assertArrayHasKey("tr_tx", $data);
            $this->assertArrayHasKey("tr_sh", $data);
            $this->assertArrayHasKey("tr_ci", $data);
            $this->assertArrayHasKey("tr_st", $data);
            $this->assertArrayHasKey("tr_co", $data);
            $this->assertEquals("200", $data["tr_tt"]);
            $this->assertEquals("GBP", $data["tr_cu"]);
            $this->assertEquals("affiliation_1", $data["tr_af"]);
            $this->assertEquals("tax_value_1", $data["tr_tx"]);
            $this->assertEquals("shipping_1", $data["tr_sh"]);
            $this->assertEquals("city_1", $data["tr_ci"]);
            $this->assertEquals("state_1", $data["tr_st"]);
            $this->assertEquals("France", $data["tr_co"]);
        }
        else {
            $this->assertArrayHasKey("ti_id", $data);
            $this->assertArrayHasKey("ti_pr", $data);
            $this->assertArrayHasKey("ti_sk", $data);
            $this->assertArrayHasKey("ti_qu", $data);
            $this->assertArrayHasKey("ti_nm", $data);
            $this->assertArrayHasKey("ti_ca", $data);
            $this->assertArrayHasKey("ti_cu", $data);
            $this->assertEquals("100", $data["ti_pr"]);
            $this->assertEquals("sku_1", $data["ti_sk"]);
            $this->assertEquals("1", $data["ti_qu"]);
            $this->assertEquals("name_1", $data["ti_nm"]);
            $this->assertEquals("category_1", $data["ti_ca"]);
            $this->assertEquals("GBP", $data["ti_cu"]);
        }
    }

    private function assertEcommercePost($data) {
        $data = json_decode($data, true);
        $this->assertArrayHasKey("schema", $data);
        $this->assertArrayHasKey("data", $data);
        $this->assertEquals($this->payload_schema, $data["schema"]);

        $data = $data["data"];

        foreach ($data as $event) {
            $this->assertArrayHasKey("eid", $event);
            $this->assertArrayHasKey("dtm", $event);
            $this->assertArrayHasKey("tv", $event);
            $this->assertArrayHasKey("p", $event);
            $this->assertArrayHasKey("e", $event);
            $this->assertArrayHasKey("co", $event);

            if ($event["e"] == "tr") {
                $this->assertArrayHasKey("tr_id", $event);
                $this->assertArrayHasKey("tr_tt", $event);
                $this->assertArrayHasKey("tr_cu", $event);
                $this->assertArrayHasKey("tr_af", $event);
                $this->assertArrayHasKey("tr_tx", $event);
                $this->assertArrayHasKey("tr_sh", $event);
                $this->assertArrayHasKey("tr_ci", $event);
                $this->assertArrayHasKey("tr_st", $event);
                $this->assertArrayHasKey("tr_co", $event);
                $this->assertEquals("200", $event["tr_tt"]);
                $this->assertEquals("GBP", $event["tr_cu"]);
                $this->assertEquals("affiliation_1", $event["tr_af"]);
                $this->assertEquals("tax_value_1", $event["tr_tx"]);
                $this->assertEquals("shipping_1", $event["tr_sh"]);
                $this->assertEquals("city_1", $event["tr_ci"]);
                $this->assertEquals("state_1", $event["tr_st"]);
                $this->assertEquals("France", $event["tr_co"]);
            }
            else {
                $this->assertArrayHasKey("ti_id", $event);
                $this->assertArrayHasKey("ti_pr", $event);
                $this->assertArrayHasKey("ti_sk", $event);
                $this->assertArrayHasKey("ti_qu", $event);
                $this->assertArrayHasKey("ti_nm", $event);
                $this->assertArrayHasKey("ti_ca", $event);
                $this->assertArrayHasKey("ti_cu", $event);
                $this->assertEquals("100", $event["ti_pr"]);
                $this->assertEquals("sku_1", $event["ti_sk"]);
                $this->assertEquals("1", $event["ti_qu"]);
                $this->assertEquals("name_1", $event["ti_nm"]);
                $this->assertEquals("category_1", $event["ti_ca"]);
                $this->assertEquals("GBP", $event["ti_cu"]);
            }
        }
    }
}
