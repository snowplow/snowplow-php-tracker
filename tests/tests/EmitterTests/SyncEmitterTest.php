<?php
/*
    SyncEmitterTest.php

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
use Snowplow\Tracker\Emitters\SyncEmitter;
use Snowplow\Tracker\Subject;

class SyncEmitterTest extends PHPUnit_Framework_TestCase {
    private $uri = "228e51cc.ngrok.com";
    private $badUri = "dummy-post-colllector.cloudfront.com";
    private $protocol = "http";

    public function testSyncPostDebug() {
        $tracker = $this->returnTracker("POST", true);
        $tracker->returnSubject()->setNetworkUserId("network-id");
        for ($i = 0; $i < 1; $i++) {
            $tracker->trackPageView("www.example.com", "example", "www.referrer.com");
        }
        $tracker->flushEmitters(true);

        //Asserts
        $this->requestResultAssert($tracker->returnEmitters());
    }

    public function testSyncForceFlushPost() {
        $tracker = $this->returnTracker("POST", false);
        for ($i = 0; $i < 1; $i++) {
            $tracker->trackPageView("www.example.com", "example", "www.referrer.com");
        }
        $tracker->flushEmitters(true);
    }

    public function testSyncGetDebug() {
        $tracker = $this->returnTracker("GET", true);
        $tracker->returnSubject()->setNetworkUserId("network-id");
        for ($i = 0; $i < 1; $i++) {
            $tracker->trackPageView("www.example.com", "example", "www.referrer.com");
        }
        $tracker->flushEmitters(true);

        //Asserts
        $this->requestResultAssert($tracker->returnEmitters());
    }
    public function testSyncForceFlushGet() {
        $tracker = $this->returnTracker("GET", false);
        for ($i = 0; $i < 1; $i++) {
            $tracker->trackPageView("www.example.com", "example", "www.referrer.com");
        }
        $tracker->flushEmitters(true);
    }

    public function testReturnFunctions() {
        $e1 = $this->returnSyncEmitter("GET", $this->uri, false);

        $e2 = $this->returnSyncEmitter("POST", $this->uri, false);

        // Asserts
        $this->assertEquals($this->protocol."://".$this->uri."/i?",
            $e1->returnUrl());
        $this->assertEquals("GET",
            $e1->returnType());
        $this->assertEquals($this->protocol,
            $e1->returnProtocol());
        $this->assertEquals($this->protocol."://".$this->uri."/com.snowplowanalytics.snowplow/tp2",
            $e2->returnUrl());
        $this->assertEquals("POST",
            $e2->returnType());
        $this->assertEquals($this->protocol,
            $e2->returnProtocol());
    }

    public function testSyncBadType() {
        $e1 = $this->returnSyncEmitter("Bad Type", $this->uri, false);

        // Asserts
        $this->assertEquals(NULL, $e1->returnUrl());
    }

    public function testSyncInitNoBuffer() {
        $e1 = $this->returnSyncEmitter("GET", $this->uri, false, NULL);
        $e2 = $this->returnSyncEmitter("POST", $this->uri, false, NULL);

        //Asserts
        $this->assertEquals(1, $e1->returnBufferSize());
        $this->assertEquals(50, $e2->returnBufferSize());
    }

    private function requestResultAssert($emitters) {
        foreach($emitters as $emitter) {
            $results = $emitter->returnRequestResults();
            foreach ($results as $result) {
                $this->assertEquals(200, $result["code"]);
            }
        }
    }

    private function returnTracker($type, $debug) {
        $subject = new Subject();
        $e1 = $this->returnSyncEmitter($type, $this->uri, $debug);
        $e2 = $this->returnSyncEmitter($type, $this->badUri, $debug);

        return new Tracker(array($e1, $e2), $subject, NULL, NULL, true);
    }

    private function returnSyncEmitter($type, $uri, $debug, $buffer = 10) {
        return new SyncEmitter($uri, "http", $type, $buffer, $debug);
    }
}
