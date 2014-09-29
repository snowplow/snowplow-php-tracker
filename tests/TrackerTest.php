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
use Snowplow\Tracker\Emitter;
use Snowplow\Tracker\Subject;

class TrackerTest extends PHPUnit_Framework_TestCase {

    /*
        Tracker Test URIs
        GET: d3rkrsqld9gmqf.cloudfront.net
        POST: clojtest5-env.elasticbeanstalk.com
    */

    public function __construct() {
        // Make multiple emitters

        $e1 = new Emitter("clojtest5-env.elasticbeanstalk.com", "POST", "http", "1");
        $e2 = new Emitter("d3rkrsqld9gmqf.cloudfront.net", "GET", "http", "1");

        // Make multiple tracker settings groups.

        $s1 = new Subject();

        // Create a tracker:

        $this->t1 = new Tracker($e1, $s1, "namespace", "app_id", "0");

        // Append more Emitters to the tracker.

        $this->t1->addEmitter($e2);

        // Edit subject matter from tracker

        $this->t1->subject->setTimezone("timezone_1");

        // Tracker Context Example

        $this->context = array(
            "schema" => "iglu:com.acme_company/context_example/jsonschema/2.1.1",
            "data" => array("movie_name" => "Solaris", "poster_country" => "JP", "poster_year" => 1978)
        );

        // Tracker Unstructured Event Example

        $this->unstruct_event = array(
            "schema" => "com.example_company/save-game/jsonschema/1.0.2",
            "data" => array("save_id" => "4321", "level" => 23, "difficultyLevel" => "HARD", "dl_content" => True)
        );
    }

    public function testTrackerInit() {
        $emitter = new Emitter("clojtest5-env.elasticbeanstalk.com", "POST", "http", "1");
        $subject = new Subject();
        $tracker = new Tracker($emitter, $subject, "namespace", "app_id", "0");

        // Asserts

        $this->assertEquals($tracker->emitter[0], $emitter);
        $this->assertEquals($tracker->subject, $subject);
        $this->assertEquals($tracker->encode_base64, false);
        $this->assertEquals($tracker->CONTEXT_SCHEMA, "iglu:com.snowplowanalytics.snowplow/contexts/jsonschema/1-0-0");
        $this->assertEquals($tracker->SCREEN_VIEW_SCHEMA, "iglu:com.snowplowanalytics.snowplow/screen_view/jsonschema/1-0-0");
        $this->assertEquals($tracker->UNSTRUCT_EVENT_SCHEMA, "iglu:com.snowplowanalytics.snowplow/unstruct_event/jsonschema/1-0-0");
        $this->assertEquals($tracker->std_nv_pairs, array("tv" => "php-0.1.0", "tna" => "namespace", "aid" => "app_id"));
    }

    public function testTrackerInitEmitterArray() {
        $emitter1 = new Emitter("clojtest5-env.elasticbeanstalk.com", "POST", "http", "1");
        $emitter2 = new Emitter("clojtest5-env.elasticbeanstalk.com", "GET", "http", "1");
        $emitters = array($emitter1, $emitter2);
        $subject = new Subject();
        $tracker = new Tracker($emitters, $subject, "namespace", "app_id", "0");

        // Asserts

        $this->assertEquals($tracker->emitter[0], $emitter1);
        $this->assertEquals($tracker->emitter[1], $emitter2);
    }

    public function testTrackerChangeSubject() {
        $emitter = new Emitter("clojtest5-env.elasticbeanstalk.com", "POST", "http", "1");
        $subject1 = new Subject();
        $subject1->setUserID("user_id_1");
        $subject2 = new Subject();
        $subject2->setUserID("user_id_2");
        $tracker = new Tracker($emitter, $subject1, "namespace", "app_id", "0");
        $uid = $tracker->subject->getSubject();

        // Assert - 1

        $this->assertEquals("user_id_1", $uid["uid"]);

        // Change...

        $tracker->updateSubject($subject2);
        $uid = $tracker->subject->getSubject();

        // Assert - 2

        $this->assertEquals("user_id_2", $uid["uid"]);
    }

    public function testTrackerAddEmitterAfter() {
        $emitter = new Emitter("clojtest5-env.elasticbeanstalk.com", "POST", "http", "1");
        $emitter1 = new Emitter("clojtest5-env.elasticbeanstalk.com", "POST", "http", "1");
        $subject = new Subject();
        $tracker = new Tracker($emitter, $subject, "namespace", "app_id", "0");
        $tracker->addEmitter($emitter1);

        // Assert

        $this->assertEquals(2, count($tracker->emitter));
    }

    public function testBufferSend() {
        $e1 = new Emitter("clojtest5-env.elasticbeanstalk.com", "POST", "http", 10);
        $s1 = new Subject();
        $tracker = new Tracker($e1, $s1, "namespace", "app_id", "0");
        for ($i = 0; $i < 25; $i++) {
            $tracker->trackPageView("www.example.com", "example", "www.referrer.com");
        }
        $tracker->flushEmitters();
        $results = $tracker->emitter[0]->getRequestResults();

        // Asserts

        $this->assertEquals(3, count($results));
        foreach ($results as $result) {
            $this->assertEquals(200, $result["code"]);
        }
    }

    public function testTrackPageView() {
        $this->t1->trackPageView("www.example.com", "example", "www.referrer.com", $this->context);

        // Asserts

        foreach($this->t1->emitter as $emitter) {
            $this->assertEquals(200, $emitter->requests_results[0]["code"]);
        }
    }

    public function testTrackScreenView() {
        $this->t1->trackScreenView("HUD", "Level: 23", $this->context);

        // Asserts

        $this->assertEquals(200, $this->t1->emitter[0]->requests_results[0]["code"]);
    }

    public function testTrackStructEvent() {
        $this->t1->trackStructEvent("shop", "add-to-basket", NULL, "pcs", 2, $this->context);

        // Asserts

        foreach($this->t1->emitter as $emitter) {
            $this->assertEquals(200, $emitter->requests_results[0]["code"]);
        }
    }

    public function testTrackUnstructEvent() {
        $this->t1->trackUnstructEvent($this->unstruct_event, $this->context);

        // Asserts

        foreach($this->t1->emitter as $emitter) {
            $this->assertEquals(200, $emitter->requests_results[0]["code"]);
        }
    }

    public function testTrackEcommerceTransaction() {
        $this->t1->trackEcommerceTransaction("test_order_id_1", 200, "GBP", "affiliation_1", "tax_value_1",
            "shipping_1", "city_1", "state_1", "France",
            array(
                array("name" => "name_1","category" => "category_1","currency" => "GBP",
                    "price" => 100,"sku" => "sku_1","quantity" => 1),
                array("name" => "name_2","category" => "category_2","currency" => "GBP",
                    "price" => 100,"sku" => "sku_2","quantity" => 1)
            ),
            $this->context
        );

        // Asserts

        foreach($this->t1->emitter as $emitter) {
            foreach ($emitter->requests_results as $results) {
                $this->assertEquals(200, $results["code"]);
            }
        }
    }
}
