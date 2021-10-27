<?php

/*
    Subject.php

    Copyright (c) 2014-2021 Snowplow Analytics Ltd. All rights reserved.

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
    Copyright: Copyright (c) 2014-2021 Snowplow Analytics Ltd
    License: Apache License Version 2.0
*/

namespace Snowplow\Tracker;

class Subject extends Constants {

    // Subject Parameters

    private $tracker_settings;

    /**
     * Constructs an array in which subject parameters are stored
     */
    public function __construct() {
        $this->tracker_settings = array("p" => self::DEFAULT_PLATFORM);
    }

    /**
     * Returns the subject parameters as an array which can be added to the payload
     *
     * @return array
     */
    public function getSubject() {
        return $this->tracker_settings;
    }

    // Setter Functions

    /**
     * Sets the platform from which the event is fired
     *
     * @param string $platform
     */
    public function setPlatform($platform) {
        $this->tracker_settings["p"] = $platform;
    }

    /**
     * Sets a custom user identification for the event
     *
     * @param string $userId
     */
    public function setUserId($userId) {
        $this->tracker_settings["uid"] = $userId;
    }

    /**
     * Sets the screen resolution
     *
     * @param int $width
     * @param int $height
     */
    public function setScreenResolution($width, $height) {
        $this->tracker_settings["res"] = $width."x".$height;
    }

    /**
     * Sets the view port resolution
     *
     * @param int $width
     * @param int $height
     */
    public function setViewPort($width, $height) {
        $this->tracker_settings["vp"] = $width."x".$height;
    }

    /**
     * Sets the colour depth
     *
     * @param int $depth
     */
    public function setColorDepth($depth) {
        $this->tracker_settings["cd"] = $depth;
    }

    /**
     * Sets the event timezone
     *
     * @param string $timezone
     */
    public function setTimezone($timezone) {
        $this->tracker_settings["tz"] = $timezone;
    }

    /**
     * Sets the language used
     *
     * @param string $language
     */
    public function setLanguage($language) {
        $this->tracker_settings["lang"] = $language;
    }

    /**
     * Sets the client's IP Address
     *
     * @param string $ipAddress
     */
    public function setIpAddress($ipAddress) {
        $this->tracker_settings["ip"] = $ipAddress;
    }

    /**
     * Sets the Useragent
     *
     * @param string $useragent
     */
    public function setUseragent($useragent) {
        $this->tracker_settings["ua"] = $useragent;
    }

    /**
     * Sets the Network User ID
     *
     * @param string $networkUserId
     */
    public function setNetworkUserId($networkUserId) {
        $this->tracker_settings["tnuid"] = $networkUserId;
    }

    /**
     * Sets the domain User ID
     *
     * @param string $domainUserId
     */
    public function setDomainUserId($domainUserId) {
        $this->tracker_settings["duid"] = $domainUserId;
    }

    /**
     * Sets the Session ID
     *
     * @param string $sessionId
     */
    public function setSessionId($sessionId) {
        $this->tracker_settings["sid"] = $sessionId;
    }

    /**
     * Sets the referer
     *
     * @param string $refr
     */
    public function setRefr($refr) {
        $this->tracker_settings["refr"] = $refr;
    }

    /**
     * Sets the page URL
     *
     * @param string $pageUrl
     */
    public function setPageUrl($pageUrl) {
        $this->tracker_settings["url"] = $pageUrl;
    }

    // Subject Return Functions

    public function returnTrackerSettings() {
        return $this->tracker_settings;
    }
}
