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
use PHPUnit\Framework\TestCase;

/**
 * Tests the basic use-cases of the Tracker
 * - Values being set on Tracker creation
 * - Adding multiple Emitters in an array
 * - Adding an Emitter to the Tracker after initilization
 * - Changing the Tracker's subject
 * - Changing Subject information from the Tracker
 */
class TrackerTest extends TestCase {

    /** @var SyncEmitter */
    private $e1;

    /** @var SyncEmitter */
    private $e2;

    /** @var Subject */
    private $s1;

    // Helper Functions

    protected function setUp(): void {
        // Make multiple emitters
        $this->e1 = $this->getSyncEmitter("GET");
        $this->e2 = $this->getSyncEmitter("POST");

        // Create a Tracker Subject
        $this->s1 = new Subject();
    }

    private function getSyncEmitter($type) {
        return new SyncEmitter("collector.acme.au", "http", $type, 2, true);
    }

    // Tests

    public function testTrackerInit() {
        $tracker = new Tracker($this->e1, $this->s1, "namespace", "app_id", false);

        // Asserts
        $this->assertEquals($this->s1, $tracker->returnSubject());
        $this->assertEquals(false, $tracker->returnEncodeBase64());
        $this->assertEquals(array("tv" => "php-0.2.1", "tna" => "namespace", "aid" => "app_id"), $tracker->returnStdNvPairs());
        $tracker->turnOffDebug(true);
    }

    public function testTrackerInitEmitterArray() {
        $emitters = array($this->e1, $this->e2);
        $tracker = new Tracker($emitters, $this->s1, "namespace", "app_id", false);

        // Asserts
        $emitters = $tracker->returnEmitters();
        $this->assertEquals($emitters[0], $this->e1);
        $this->assertEquals($emitters[1], $this->e2);
        $tracker->turnOffDebug(true);
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

        // Change a facet of the new subject...
        $tracker->returnSubject()->setIpAddress("127.10.0.1");
        $uid = $tracker->returnSubject()->getSubject();

        // Assert - 3
        $this->assertEquals("127.10.0.1", $uid["ip"]);
        $tracker->turnOffDebug(true);
    }

    public function testTrackerAddEmitterAfter() {
        $tracker = new Tracker($this->e1, $this->s1, "namespace", "app_id", false);
        $tracker->addEmitter($this->e2);

        // Assert
        $this->assertEquals(2, count($tracker->returnEmitters()));
        $tracker->turnOffDebug(true);
    }

    public function testEventIdIsProperlyGenerated()
    {
        $emitter = $this->getMockBuilder('Snowplow\Tracker\Emitter')->getMock();

        $test = $this;
        $emitter->expects($this->once())
            ->method('addEvent')
            ->with($this->callback(function ($a) use($test) {
                $test->assertArrayHasKey('eid', $a);
                $test->assertRegExp('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/', $a['eid']);

                return true;
            }));

        $tracker = new Tracker($this->e1, $this->s1, "namespace", "app_id", false);
        $tracker->addEmitter($emitter);
        $tracker->trackPageView("http:/example.com");
    }
}
