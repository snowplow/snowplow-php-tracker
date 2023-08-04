<?php
/*
    RetryRequestManagerTest.php

    Copyright (c) 2014-2023 Snowplow Analytics Ltd. All rights reserved.

    This program is licensed to you under the Apache License Version 2.0,
    and you may not use this file except in compliance with the Apache License
    Version 2.0. You may obtain a copy of the Apache License Version 2.0 at
    http://www.apache.org/licenses/LICENSE-2.0.

    Unless required by applicable law or agreed to in writing,
    software distributed under the Apache License Version 2.0 is distributed on
    an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either
    express or implied. See the Apache License Version 2.0 for the specific
    language governing permissions and limitations there under.
*/

use Snowplow\Tracker\Emitters\RetryRequestManager;
use PHPUnit\Framework\TestCase;

class RetryRequestManagerTest extends TestCase {

    public function testIncreasesBackoffDelayExponentially() {
        $retry = new RetryRequestManager();
        $retry->incrementRetryCount();
        $this->assertEquals(100, $retry->getBackoffDelayMs());
        $retry->incrementRetryCount();
        $this->assertEquals(200, $retry->getBackoffDelayMs());
        $retry->incrementRetryCount();
        $this->assertEquals(400, $retry->getBackoffDelayMs());
        $retry->incrementRetryCount();
        $this->assertEquals(800, $retry->getBackoffDelayMs());
    }

    public function testRetriesOnFailureStatusCodes() {
        $retry = new RetryRequestManager();
        $this->assertTrue($retry->shouldRetryForStatusCode(0));
        $this->assertTrue($retry->shouldRetryForStatusCode(404));
        $this->assertTrue($retry->shouldRetryForStatusCode(500));
    }

    public function doesntRetryOnSome4xxFailureStatusCodes() {
        $retry = new RetryRequestManager();
        $this->assertTrue($retry->shouldRetryForStatusCode(400));
        $this->assertTrue($retry->shouldRetryForStatusCode(401));
    }

    public function testDoesntRetryOnSuccessStatusCodes() {
        $retry = new RetryRequestManager();
        $this->assertFalse($retry->shouldRetryForStatusCode(200));
        $this->assertFalse($retry->shouldRetryForStatusCode(201));
    }

    public function testDoesntRetryAfterMaxRetries() {
        $retry = new RetryRequestManager(2);
        $this->assertTrue($retry->shouldRetryForStatusCode(500));
        $retry->incrementRetryCount();
        $this->assertTrue($retry->shouldRetryForStatusCode(500));
        $retry->incrementRetryCount();
        $this->assertFalse($retry->shouldRetryForStatusCode(500));
    }
}
