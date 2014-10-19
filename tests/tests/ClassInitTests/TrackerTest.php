<?php
/*
    TrackerTest.php

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

class TrackerTest extends PHPUnit_Framework_TestCase {
    private $uri = "228e51cc.ngrok.com";

    public function __construct() {
        // Make multiple emitters
        $this->e1 = $this->getSyncEmitter("GET");
        $this->e2 = $this->getSyncEmitter("POST");

        // Create a Tracker Subject
        $this->s1 = new Subject();
    }

    public function testTrackerInit() {
        $tracker = new Tracker($this->e1, $this->s1, "namespace", "app_id", false);

        // Asserts
        $this->assertEquals($this->s1, $tracker->returnSubject());
        $this->assertEquals(false, $tracker->returnEncodeBase64());
        $this->assertEquals(array("tv" => "php-0.2.0", "tna" => "namespace", "aid" => "app_id"),
            $tracker->returnStdNvPairs());
    }

    public function testSetNetworkUserId() {
        $tracker = new Tracker($this->e1, $this->s1, "namespace", "app_id", false);
        $tracker->returnSubject()->setNetworkUserId("network-user-id");

        $this->assertEquals($tracker->returnSubject()->returnNetworkUserId(), "network-user-id");
    }

    public function testTrackerInitEmitterArray() {
        $emitters = array($this->e1, $this->e2);
        $tracker = new Tracker($emitters, $this->s1, "namespace", "app_id", false);

        // Asserts
        $emitters = $tracker->returnEmitters();
        $this->assertEquals($emitters[0], $this->e1);
        $this->assertEquals($emitters[1], $this->e2);
    }

    public function testTrackerChangeSubject() {
        $subject1 = new Subject();
        $subject1->setUserID("user_id_1");
        $subject2 = new Subject();
        $subject2->setUserID("user_id_2");
        $tracker = new Tracker($this->e1, $subject1, "namespace", "app_id", false);
        $uid = $tracker->returnSubject()->getSubject();

        // Assert - 1
        $this->assertEquals("user_id_1", $uid["uid"]);

        // Change...
        $tracker->updateSubject($subject2);
        $uid = $tracker->returnSubject()->getSubject();

        // Assert - 2
        $this->assertEquals("user_id_2", $uid["uid"]);

        $tracker->returnSubject()->setIpAddress("127.10.0.1");
        $uid = $tracker->returnSubject()->getSubject();

        // Assert - 3
        $this->assertEquals("127.10.0.1", $uid["ip"]);
    }

    public function testTrackerAddEmitterAfter() {
        $tracker = new Tracker($this->e1, $this->s1, "namespace", "app_id", false);
        $tracker->addEmitter($this->e2);

        // Assert
        $this->assertEquals(2, count($tracker->returnEmitters()));
    }

    public function testNuidQueues() {
        $tracker = new Tracker($this->e1, $this->s1, "namespace", "app_id", false);
        $subject = $tracker->returnSubject();
        $tracker->trackPageView("www.example.com", "example", "www.referrer.com");

        $subject->setNetworkUserId("nuid-1");
        $tracker->trackPageView("www.example.com", "example", "www.referrer.com");

        $subject->setNetworkUserId("nuid-2");
        $tracker->trackPageView("www.example.com", "example", "www.referrer.com");

        $nuid_array = array("", "nuid-1", "nuid-2");

        // Check that we have three buffer-nuid arrays
        $emitters = $tracker->returnEmitters();
        foreach ($emitters as $emitter) {
            $buffers = $emitter->returnBuffers();
            $this->assertEquals(3, count($buffers));
            for ($i = 0; $i < 3; $i++) {
                $this->assertEquals($nuid_array[$i], $buffers[$i]["nuid"]);
            }
        }

        // Send the three events
        $tracker->flushEmitters(true);

        // Check everything sent
        foreach ($emitters as $emitter) {
            $buffers = $emitter->returnBuffers();
            foreach ($buffers as $buffer) {
                $this->assertEquals(0, count($buffer["buffer"]));
            }
        }
    }

    private function getSyncEmitter($type) {
        return new SyncEmitter($this->uri, "http", $type, 2, true);
    }
}
