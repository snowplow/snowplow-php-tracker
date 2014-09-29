<?php
/*
    EmitterTest.php

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
use Snowplow\Tracker\Emitter;

class EmitterTest extends PHPUnit_Framework_TestCase {
    public function testEmitterInitPost() {
        $emitter = new Emitter("example.collector.uri", "POST", "http", NULL);
        $this->assertEquals(10, $emitter->buffer_size);
    }

    public function testEmitterInitGet() {
        $emitter = new Emitter("example.collector.uri", "GET", "http", NULL);
        $this->assertEquals(1, $emitter->buffer_size);
    }

    public function testEmitterInitUnsupportedMethod() {
        $emitter = new Emitter("example.collector.uri", "BAD", "http", NULL);
        $this->assertEquals(NULL, $emitter->collector_url);
    }
}
