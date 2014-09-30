<?php
/*
    SubjectTest.php

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
use Snowplow\Tracker\Subject;

class SubjectTest extends PHPUnit_Framework_TestCase {
    public function __construct() {
        $this->subject = new Subject();
    }

    public function testSubjectCreation() {
        $subject = new Subject();
        $this->assertEquals($this->subject, $subject);
    }

    public function testGetSubject() {
        $array = $this->subject->getSubject();
        $this->assertEquals($array, array("p" => "srv"));
    }

    public function testAddPlatform() {
        $this->subject->setPlatform("platform");
        $this->assertArrayHasKey("p", $this->subject->tracker_settings);
        $this->assertEquals("platform", $this->subject->tracker_settings["p"]);
    }

    public function testAddUserId() {
        $this->subject->setUserID("user_id_1");
        $this->assertArrayHasKey("uid", $this->subject->tracker_settings);
        $this->assertEquals("user_id_1", $this->subject->tracker_settings["uid"]);
    }

    public function testAddScreenRes() {
        $this->subject->setScreenResolution(1024, 768);
        $this->assertArrayHasKey("res", $this->subject->tracker_settings);
        $this->assertEquals("1024x768", $this->subject->tracker_settings["res"]);
    }

    public function testAddViewPort() {
        $this->subject->setViewPort(1366, 768);
        $this->assertArrayHasKey("vp", $this->subject->tracker_settings);
        $this->assertEquals("1366x768", $this->subject->tracker_settings["vp"]);
    }

    public function testAddColorDepth () {
        $this->subject->setColorDepth(100000);
        $this->assertArrayHasKey("cd", $this->subject->tracker_settings);
        $this->assertEquals(100000, $this->subject->tracker_settings["cd"]);
    }

    public function testAddTimezone () {
        $this->subject->setTimezone("timezone");
        $this->assertArrayHasKey("tz", $this->subject->tracker_settings);
        $this->assertEquals("timezone", $this->subject->tracker_settings["tz"]);
    }

    public function testAddLang () {
        $this->subject->setLanguage("eng");
        $this->assertArrayHasKey("lang", $this->subject->tracker_settings);
        $this->assertEquals("eng", $this->subject->tracker_settings["lang"]);
    }
}
