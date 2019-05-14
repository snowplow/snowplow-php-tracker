<?php

/*
    Tracker.php

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

namespace Snowplow\Tracker;

use Ramsey\Uuid\Uuid;

class Tracker extends Constants {

    // Tracker Parameters

    private $subject;
    private $emitters;
    private $encode_base64;
    private $std_nv_pairs;

    /**
     * Constructs a new tracker object with emitter(s) and a subject.
     *
     * @param emitter|array $emitter - Emitter object, used for sending event payloads to for processing
     * @param subject $subject - Subject object, contains extra information which is parcelled with the event
     * @param string|null $namespace
     * @param string|null $app_id
     * @param bool $encode_base64 - Boolean stating whether or not to encode certain values as base64
     */
    public function __construct($emitter, Subject $subject, $namespace = NULL, $app_id = NULL,
                                $encode_base64 = NULL) {

        // Set the emitter or emitters for the tracker
        if (is_array($emitter)) {
            $this->emitters = $emitter;
        }
        else {
            $this->emitters = array($emitter);
        }

        // Set the subject for the tracker
        $this->subject = $subject;

        // Set truth for base_64 encoding
        $this->encode_base64 = $encode_base64 !== NULL ? $encode_base64 : self::DEFAULT_BASE_64;

        // Tracker Event Parameters
        $this->std_nv_pairs = array(
            "tv"  => self::TRACKER_VERSION,
            "tna" => $namespace,
            "aid" => $app_id
        );
    }

    // Setter Functions

    /**
     * Updates the subject of the tracker with a new subject
     *
     * @param subject $subject
     */
    public function updateSubject(Subject $subject) {
        $this->subject = $subject;
    }

    /**
     * Appends another emitter to the tracker
     *
     * @param emitter $emitter
     */
    public function addEmitter($emitter) {
        array_push($this->emitters, $emitter);
    }

    // Emitter Send Functions

    /**
     * Sends the Payload arrays to the emitter for processing
     * Converts all values within the array into string values before sending
     *
     * @param array $payload
     */
    private function sendRequest($payload) {
        $final_payload = $this->returnArrayStringify('strval', $payload);
        foreach ($this->emitters as $emitter) {
            $emitter->addEvent($final_payload);
        }
    }

    /**
     * Will force send all events in the emitter(s) buffers
     * This happens irrespective of whether or not buffer limit has been reached
     */
    public function flushEmitters() {
        foreach ($this->emitters as $emitter) {
            $emitter->forceFlush();
        }
    }

    /**
     * Will turn off debugging for all emitters
     *
     * @param bool $deleteLocal - Will also delete all local information stored.
     */
    public function turnOffDebug($deleteLocal = false) {
        foreach($this->emitters as $emitter) {
            $emitter->turnOffDebug($deleteLocal);
        }
    }

    // Return Functions

    /**
     * Takes a Payload object as a parameter and appends all necessary event data to it
     * Appends the following: subject, unique id, context, tracker standard pairs
     *
     * @param Payload $ep - Payload object, contains an array of nv pairs
     * @param array|null $context - Event context array, contains extra information on the event
     * @return Payload
     */
    private function returnCompletePayload(Payload $ep, $context = NULL) {
        if ($context != NULL) {
            $context_envelope = array("schema" => self::CONTEXT_SCHEMA, "data" => $context);
            $ep->addJson($context_envelope, $this->encode_base64, "cx", "co");
        }
        $ep->addDict($this->std_nv_pairs);
        $ep->addDict($this->subject->getSubject());
        $ep->add("eid", $this->generateUuid());
        return $ep;
    }

    /**
     * Generates and returns a unique id string for the event
     *
     * @return string - Unique String based on the time of creation
     */
    private function generateUuid() {
        if (function_exists('uuid_create'))
        {
            return uuid_create(UUID_TYPE_TIME);
        }
        return Uuid::uuid1()->toString();
    }

    /**
     * Converts all array values into strings
     * Checks for booleans and inserts True or False instead of 1 and 0
     *
     * @param string $func - Defines what will happen to the array value (strval)
     * @param array $arr - Array of name value pairs
     * @return array - Returns stringified array
     */
    private function returnArrayStringify($func, $arr) {
        $ret = array();
        foreach ($arr as $key => $val) {
            $ret[$key] = (is_array($val)) ? $this->returnArrayStringify($func, $val) : $func($val);
        }
        return $ret;
    }

    // Tracking Functions

    /**
     * Takes a Payload and a Context and forwards the finalised payload array to the sendRequest function.
     *
     * @param Payload $ep - Payload object as parameter
     * @param array|null $context - Context to be added to the event
     */
    private function track(Payload $ep, $context = NULL) {
        $ep = $this->returnCompletePayload($ep, $context);
        $this->sendRequest($ep->get());
    }

    /**
     * Tracks a page view with the aforementioned metrics
     *
     * @param string $page_url - Page URL you want to track
     * @param string|null $page_title - Page Title
     * @param string|null $referrer - Referral Page
     * @param array|null $context - Event Context
     * @param int|null $tstamp - Event Timestamp
     */
    public function trackPageView($page_url, $page_title = NULL, $referrer = NULL, $context = NULL, $tstamp = NULL) {
        $ep = new Payload($tstamp);
        $ep->add("e", "pv");
        $ep->add("url", $page_url);
        $ep->add("page", $page_title);
        $ep->add("refr", $referrer);
        $this->track($ep, $context);
    }

    /**
     * Tracks a structured event with the aforementioned metrics
     *
     * @param string $category - Event Category
     * @param string $action - Event itself
     * @param string|null $label - Refers to the object the action is performed on
     * @param string|null $property - Property associated with either the action or the object
     * @param int|float|null $value - A value associated with the user action
     * @param array|null $context - Event Context
     * @param int|null $tstamp - Event Timestamp
     */
    public function trackStructEvent($category, $action, $label = NULL, $property = NULL, $value = NULL,
                                     $context = NULL, $tstamp = NULL) {
        $ep = new Payload($tstamp);
        $ep->add("e", "se");
        $ep->add("se_ca", $category);
        $ep->add("se_ac", $action);
        $ep->add("se_la", $label);
        $ep->add("se_pr", $property);
        $ep->add("se_va", $value);
        $this->track($ep, $context);
    }

    /**
     * Tracks an unstructured event with the aforementioned metrics
     *
     * @param array $event_json - The properties of the event. Has two fields:
     *                           - A "data" field containing the event properties and
     *                           - A "schema" field identifying the schema against which the data is validated
     * @param array|null $context - Event Context
     * @param int|null $tstamp - Event Timestamp
     */
    public function trackUnstructEvent($event_json, $context = NULL, $tstamp = NULL) {
        $envelope = array("schema" => self::UNSTRUCT_EVENT_SCHEMA, "data" => $event_json);
        $ep = new Payload($tstamp);
        $ep->add("e", "ue");
        $ep->addJson($envelope, $this->encode_base64, "ue_px", "ue_pr");
        $this->track($ep, $context);
    }

    /**
     * Tracks a screen view event with the aforementioned metrics
     *
     * @param string|null $name - Event Screen Name
     * @param string|null $id - Event Screen Unique ID
     * @param array|null $context - Event Context
     * @param int|null $tstamp - Event Timestamp
     */
    public function trackScreenView($name = NULL, $id = NULL, $context = NULL, $tstamp = NULL) {
        $screen_view_properties = array();
        if ($name != NULL) {
            $screen_view_properties["name"] = $name;
        }
        if ($id != NULL) {
            $screen_view_properties["id"] = $id;
        }
        $ep_json = array("schema" => self::SCREEN_VIEW_SCHEMA, "data" => $screen_view_properties);
        $this->trackUnstructEvent($ep_json, $context, $tstamp);
    }

    /**
     * Tracks an ecommerce transaction event, can contain many items
     *
     * @param string $order_id - Transaction order id
     * @param int|float $total_value - Transaction total value
     * @param string|null $currency - Currency used in the transaction
     * @param string|null $affiliation - Transaction affiliation
     * @param int|float|null $tax_value - Total tax value
     * @param int|float|null $shipping - Shipping cost
     * @param string|null $city
     * @param string|null $state
     * @param string|null $country
     * @param array $items - An array of items which make up the transaction
     * @param array|null $context - Event Context
     * @param int|null $tstamp - Event timestamp
     */
    public function trackEcommerceTransaction($order_id, $total_value, $currency = NULL, $affiliation = NULL,
                                              $tax_value = NULL, $shipping = NULL, $city = NULL, $state = NULL,
                                              $country = NULL, $items, $context = NULL, $tstamp = NULL) {
        $ep = new Payload($tstamp);
        $ep->add("e", "tr");
        $ep->add("tr_id", $order_id);
        $ep->add("tr_tt", $total_value);
        $ep->add("tr_cu", $currency);
        $ep->add("tr_af", $affiliation);
        $ep->add("tr_tx", $tax_value);
        $ep->add("tr_sh", $shipping);
        $ep->add("tr_ci", $city);
        $ep->add("tr_st", $state);
        $ep->add("tr_co", $country);
        $this->track($ep, $context);

        // Go through each item in the transaction and create an event for it.

        foreach ($items as $item) {
            $this->trackEcommerceTransactionItems($order_id, $item["sku"], $item["price"], $item["quantity"],
                $item["name"], $item["category"], $currency, $context, $tstamp);
        }
    }

    /**
     * Creates an event for each item in the ecommerceTransaction item array
     *
     * @param string $order_id - Order ID inherited from trackEcommerceTransaction
     * @param string $sku - Product SKU, identity of the product
     * @param int|float $price - Product Price
     * @param int $quantity - Quantity of product purchased
     * @param string|null $name - Name of product
     * @param string|null $category - Product category
     * @param string|null $currency - Currency, inherited from trackEcommerceTransaction
     * @param array|null $context - Event Context
     * @param int|null $tstamp - Event Timestamp
     */
    private function trackEcommerceTransactionItems($order_id, $sku, $price, $quantity, $name = NULL, $category = NULL,
                                                    $currency = NULL, $context = NULL, $tstamp = NULL) {
        $ep = new Payload($tstamp);
        $ep->add("e", "ti");
        $ep->add("ti_id", $order_id);
        $ep->add("ti_pr", $price);
        $ep->add("ti_sk", $sku);
        $ep->add("ti_qu", $quantity);
        $ep->add("ti_nm", $name);
        $ep->add("ti_ca", $category);
        $ep->add("ti_cu", $currency);
        $this->track($ep, $context);
    }

    // Tracker Parameter Return Functions

    /**
     * Returns the Trackers Subject
     *
     * @return Subject
     */
    public function returnSubject() {
        return $this->subject;
    }

    /**
     * Returns the Trackers Emitters as an array
     *
     * @return array
     */
    public function returnEmitters() {
        return $this->emitters;
    }

    /**
     * Returns the Trackers Truth regarding base64 encoding
     *
     * @return bool
     */
    public function returnEncodeBase64() {
        return $this->encode_base64;
    }

    /**
     * Return the standard name-value pairs of the Tracker
     *
     * @return array
     */
    public function returnStdNvPairs() {
        return $this->std_nv_pairs;
    }
}
