<?php
/*
    SocketEmitterTest.php

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

class SocketEmitterTest extends PHPUnit_Framework_TestCase {
    private $uri = "5af018b5.ngrok.com";
    private $badUri = "dummy-post-colllector.cloudfront.com";

    public function testSocketForceFlushGet() {
        $tracker = $this->returnTracker("GET", false);
        $tracker->returnSubject()->setNetworkUserId("network-id");
        $tracker->flushEmitters(true);
        for ($i = 0; $i < 1; $i++) {
            $tracker->trackPageView("www.example.com", "example", "www.referrer.com");
        }
        $tracker->flushEmitters(true);
    }

    public function testSocketDebugGet() {
        $tracker = $this->returnTracker("GET", true);
        for ($i = 0; $i < 1; $i++) {
            $tracker->trackPageView("www.example.com", "example", "www.referrer.com");
        }
        $tracker->flushEmitters(true);

        //Asserts
        $this->requestResultAssert($tracker->returnEmitters());
    }

    public function testSocketForceFlushPost() {
        $tracker = $this->returnTracker("POST", false);
        $tracker->returnSubject()->setNetworkUserId("network-id");
        for ($i = 0; $i < 1; $i++) {
            $tracker->trackPageView("www.example.com", "example", "www.referrer.com");
        }
        $tracker->flushEmitters(true);
    }

    public function testSocketDebugPost() {
        $tracker = $this->returnTracker("POST", true);
        for ($i = 0; $i < 1; $i++) {
            $tracker->trackPageView("www.example.com", "example", "www.referrer.com");
        }
        $tracker->flushEmitters(true);

        //Asserts
        $this->requestResultAssert($tracker->returnEmitters());
    }

    public function testReturnFunctions() {
        $tracker = $this->returnTracker("GET", false);
        $emitters = $tracker->returnEmitters();
        $emitter = $emitters[0];

        $this->assertEquals(false,
            $emitter->returnSsl());
        $this->assertEquals($this->uri,
            $emitter->returnUri());
        $this->assertEquals(30,
            $emitter->returnTimeout());
        $this->assertEquals("GET",
            $emitter->returnType());
        $this->assertEquals(NULL,
            $emitter->returnSocket());
        $this->assertEquals(false,
            $emitter->returnSocketIsFailed());
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
        $e1 = $this->returnSocketEmitter($type, $this->uri, $debug);
        $e2 = $this->returnSocketEmitter($type, $this->badUri, $debug);

        return new Tracker(array($e1, $e2), $subject, NULL, NULL, true);
    }

    private function returnSocketEmitter($type, $uri, $debug) {
        return new SocketEmitter($uri, NULL, $type, NULL, NULL, $debug);
    }
}
