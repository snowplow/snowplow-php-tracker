<?php
/*
    CurlEmitterTest.php

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
use Snowplow\Tracker\Emitters\CurlEmitter;
use PHPUnit\Framework\TestCase;

/**
 * Tests the functionality of the Curl emitter
 */
class CurlEmitterTest extends TestCase {

    // Helper Functions & Values

    private $uri = "localhost:4545";

    private function requestResultAssert($emitters, $code) {
        foreach($emitters as $emitter) {
            $results = $emitter->returnRequestResults();
            foreach ($results as $result) {
                $this->assertEquals($code, $result["code"]);
            }
        }
    }

    private function returnTracker($type, $debug, $uri) {
        $subject = new Subject();
        $e1 = $this->returnCurlEmitter($type, $uri, $debug);
        return new Tracker($e1, $subject, NULL, NULL, true);
    }

    private function returnCurlEmitter($type, $uri, $debug) {
        return new CurlEmitter($uri, false, $type, 2, $debug);
    }

    // Tests

    public function testCurlPostBadUri() {
        $tracker = $this->returnTracker("POST", true, "collector.acme.au");
        $tracker->flushEmitters();
        for ($i = 0; $i < 1; $i++) {
            $tracker->trackPageView("www.example.com", "example", "www.referrer.com");
        }
        $tracker->flushEmitters();

        //Asserts
        $this->requestResultAssert($tracker->returnEmitters(), 0);
        $tracker->turnOffDebug(true);
    }

    public function testCurlGetBadUri() {
        $tracker = $this->returnTracker("GET", true, "collector.acme.au");
        $tracker->flushEmitters();
        for ($i = 0; $i < 1; $i++) {
            $tracker->trackPageView("www.example.com", "example", "www.referrer.com");
        }
        $tracker->flushEmitters();

        //Asserts
        $this->requestResultAssert($tracker->returnEmitters(), 0);
        $tracker->turnOffDebug(true);
    }

    public function testCurlDebugGet() {
        $tracker = $this->returnTracker("GET", true, $this->uri);
        for ($i = 0; $i < 1; $i++) {
            $tracker->trackPageView("www.example.com", "example", "www.referrer.com");
        }
        $tracker->flushEmitters();

        //Asserts
        $this->requestResultAssert($tracker->returnEmitters(), 200);
        $tracker->turnOffDebug(true);
    }

    public function testCurlDebugPost() {
        $tracker = $this->returnTracker("POST", true, $this->uri);
        for ($i = 0; $i < 1; $i++) {
            $tracker->trackPageView("www.example.com", "example", "www.referrer.com");
        }
        $tracker->flushEmitters();

        //Asserts
        $this->requestResultAssert($tracker->returnEmitters(), 200);
        $tracker->turnOffDebug(true);
    }

    public function testCurlPost() {
        $tracker = $this->returnTracker("POST", true, $this->uri);
        for ($i = 0; $i < 1000; $i++) {
            $tracker->trackPageView("www.example.com", "example", "www.referrer.com");
        }
        $tracker->flushEmitters(false);

        //Asserts
        $this->requestResultAssert($tracker->returnEmitters(), 200);
        $tracker->turnOffDebug(true);
    }

    public function testBadType() {
        $tracker = $this->returnTracker("POSTS", false, $this->uri);
        $emitters = $tracker->returnEmitters();
        $emitter = $emitters[0];
        $this->assertEquals("http://".$this->uri."/com.snowplowanalytics.snowplow/tp2",
            $emitter->returnUrl());
    }

    public function testReturnFunctions() {
        $tracker = $this->returnTracker("POST", false, $this->uri);
        $emitters = $tracker->returnEmitters();
        $emitter = $emitters[0];

        $this->assertEquals("http://".$this->uri."/com.snowplowanalytics.snowplow/tp2",
            $emitter->returnUrl());
        $this->assertEquals("POST",
            $emitter->returnType());
        $this->assertEquals(0,
            count($emitter->returnCurlBuffer()));
        $this->assertEquals(50,
            $emitter->returnCurlAmount());
        $this->assertEquals(10,
            $emitter->returnRollingWindow());
    }
}
