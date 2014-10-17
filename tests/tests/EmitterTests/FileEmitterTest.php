<?php
/*
    FileEmitterTest.php

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
use Snowplow\Tracker\Emitters\FileEmitter;
use Snowplow\Tracker\Subject;

class FileEmitterTest extends PHPUnit_Framework_TestCase {
    private $uri = "228e51cc.ngrok.com";

    public function testFilePostForceFlush() {
        $tracker = $this->returnTracker("POST", false);
        $tracker->returnSubject()->setNetworkUserId("network-id");
        $tracker->flushEmitters(true);
        for ($i = 0; $i < 1; $i++) {
            $tracker->trackPageView("www.example.com", "example", "www.referrer.com");
        }
        $tracker->flushEmitters(true);
    }

    public function testFileGetForceFlush() {
        $tracker = $this->returnTracker("GET", false);
        $tracker->returnSubject()->setNetworkUserId("network-id");
        for ($i = 0; $i < 1; $i++) {
            $tracker->trackPageView("www.example.com", "example", "www.referrer.com");
        }
        $tracker->flushEmitters(true);

        // Will not do anything but just to check
        // that if we have a file emitter it will not error!
        $tracker->turnOfDebug(true);
    }

    public function testFilePOST() {
        $tracker = $this->returnTracker("POST", false);
        $tracker->returnSubject()->setNetworkUserId("network-id");
        for ($i = 0; $i < 10; $i++) {
            $tracker->trackPageView("www.example.com", "example", "www.referrer.com");
        }
    }

    public function testBadType() {
        $tracker = $this->returnTracker("BAD", false);
        $emitters = $tracker->returnEmitters();
        $emitter = $emitters[0];
        $this->assertNull($emitter->returnUrl());
    }

    public function testReturnFunctions() {
        $root_dir = dirname(dirname(dirname(__DIR__)));
        $tracker = $this->returnTracker("POST", false);
        $emitters = $tracker->returnEmitters();
        $emitter = $emitters[0];

        $this->assertEquals("http://".$this->uri."/com.snowplowanalytics.snowplow/tp2",
            $emitter->returnUrl());
        $this->assertEquals($root_dir."/temp/",
            $emitter->returnFilePath());
        $this->assertEquals(0,
            $emitter->returnWorkerCount());
        $this->assertEquals(2,
            $emitter->returnTimeout());
        $this->assertEquals("POST",
            $emitter->returnType());
        $this->assertEquals($root_dir,
            $emitter->returnRootPath());

        $paths = $emitter->returnWorkerPaths();
        $this->assertEquals($root_dir."/temp/w0/",
            $paths[0]);
        $this->assertEquals($root_dir."/temp/w1/",
            $paths[1]);
    }

    private function returnTracker($type) {
        $subject = new Subject();
        $emitter = $this->returnFileEmitter($type);
        return new Tracker($emitter, $subject, NULL, NULL, true);
    }

    private function returnFileEmitter($type) {
        return new FileEmitter($this->uri, false, $type, 3, 2, 2);
    }
}
