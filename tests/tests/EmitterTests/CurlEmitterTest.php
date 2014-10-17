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

class CurlEmitterTest extends PHPUnit_Framework_TestCase {
    private $uri = "228e51cc.ngrok.com";
    private $badUri = "dummy-post-colllector.cloudfront.com";

    public function testCurlForceFlushGet() {
        $tracker = $this->returnTracker("GET", false);
        $tracker->returnSubject()->setNetworkUserId("network-id");

        // Test Response Codes
        $tracker->flushEmitters(true);
        $tracker->flushEmitters(false);

        // Add an event and flush again
        for ($i = 0; $i < 1; $i++) {
            $tracker->trackPageView("www.example.com", "example", "www.referrer.com");
        }
        $tracker->flushEmitters(true);
    }

    public function testCurlDebugGet() {
        $tracker = $this->returnTracker("GET", true);
        for ($i = 0; $i < 1; $i++) {
            $tracker->trackPageView("www.example.com", "example", "www.referrer.com");
        }
        $tracker->flushEmitters(true);

        //Asserts
        $this->requestResultAssert($tracker->returnEmitters());
    }

    public function testCurlPost() {
        $tracker = $this->returnTracker("POST", false);
        for ($i = 0; $i < 100; $i++) {
            $tracker->trackPageView("www.example.com", "example", "www.referrer.com");
        }
    }

    public function testCurlForceFlushPost() {
        $tracker = $this->returnTracker("POST", false);
        $tracker->returnSubject()->setNetworkUserId("network-id");
        for ($i = 0; $i < 1; $i++) {
            $tracker->trackPageView("www.example.com", "example", "www.referrer.com");
        }
        $tracker->flushEmitters(true);
    }

    public function testCurlDebugPost() {
        $tracker = $this->returnTracker("POST", true);
        for ($i = 0; $i < 1; $i++) {
            $tracker->trackPageView("www.example.com", "example", "www.referrer.com");
        }
        $tracker->flushEmitters(true);

        //Asserts
        $this->requestResultAssert($tracker->returnEmitters());
    }

    public function testBadType() {
        $tracker = $this->returnTracker("BAD", false);
        $emitters = $tracker->returnEmitters();
        $emitter = $emitters[0];
        $this->assertNull($emitter->returnUrl());
    }

    public function testReturnFunctions() {
        $tracker = $this->returnTracker("POST", false);
        $emitters = $tracker->returnEmitters();
        $emitter = $emitters[0];

        $this->assertEquals("http://".$this->uri."/com.snowplowanalytics.snowplow/tp2",
            $emitter->returnUrl());
        $this->assertEquals(false,
            $emitter->returnSsl());
        $this->assertEquals("POST",
            $emitter->returnType());
        $this->assertEquals(0,
            count($emitter->returnCurlBuffer()));
        $this->assertEquals(50,
            $emitter->returnCurlAmount());
        $this->assertEquals(10,
            $emitter->returnRollingWindow());
    }

    private function requestResultAssert($emitters) {
        foreach($emitters as $emitter) {
            $results = $emitter->returnRequestResults();
            foreach ($results as $result) {
                if ($result["code"] != 0) {
                    $this->assertEquals(200, $result["code"]);
                }
            }
        }
    }

    private function returnTracker($type, $debug) {
        $subject = new Subject();
        $e1 = $this->returnCurlEmitter($type, $this->uri, $debug);
        $e2 = $this->returnCurlEmitter($type, $this->badUri, $debug);

        return new Tracker(array($e1, $e2), $subject, NULL, NULL, true);
    }

    private function returnCurlEmitter($type, $uri, $debug) {
        return new CurlEmitter($uri, false, $type, 2, $debug);
    }
}
