<?php
/*
    Constants.php

    Copyright (c) 2014-2022 Snowplow Analytics Ltd. All rights reserved.

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
    License: Apache License Version 2.0
*/

namespace Snowplow\Tracker;
use ErrorException;

/**
 * Contains all of the constants needed for the PHP Tracker.
 */
class Constants {
    /**
     * Settings for the PHP Tracker
     * - Version: the current version of the PHP Tracker
     * - Base64: whether or not we will encode events in Base64 before sending
     * - Debug Log Files: whether or not debug stores physical log files
     *                    if set to false all debug messages will appear in the console
     * - Context: the schema path for a custom-context
     * - Unstruct: the schema path for an unstructured event
     * - Screen View: the schema path for a custom screen view event
     * - Post: the schema path for a POST Payload
     * - Platform: the default platform that we assume the tracker is running on
     * - Post Path: the path appended to the collector uri for all POST requests
     * - Post Content Type: the content type we will be appending to the header for all POST Requests
     * - Post Accept: the type of content that will be accepted by the collector
     * - Get Path: the path appended to the collector uri for all GET requests
     * - Protocol: the default protocol to be used for the collector
     * - SSL: the default for whether or not to use SSL Encryption
     * - Type: the default for what type of request the emitter will be making (POST or GET)
     */
    const TRACKER_VERSION       = "php-0.9.0";
    const DEFAULT_BASE_64       = true;
    const DEBUG_LOG_FILES       = true;
    const CONTEXT_SCHEMA        = "iglu:com.snowplowanalytics.snowplow/contexts/jsonschema/1-0-1";
    const UNSTRUCT_EVENT_SCHEMA = "iglu:com.snowplowanalytics.snowplow/unstruct_event/jsonschema/1-0-0";
    const SCREEN_VIEW_SCHEMA    = "iglu:com.snowplowanalytics.snowplow/screen_view/jsonschema/1-0-0";
    const POST_REQ_SCHEMA       = "iglu:com.snowplowanalytics.snowplow/payload_data/jsonschema/1-0-4";
    const DEFAULT_PLATFORM      = "srv";
    const POST_PATH             = "/com.snowplowanalytics.snowplow/tp2";
    const POST_CONTENT_TYPE     = "application/json; charset=utf-8";
    const POST_ACCEPT           = "application/json";
    const GET_PATH              = "/i";
    const DEFAULT_PROTOCOL      = "http";
    const DEFAULT_SSL           = false;
    const DEFAULT_REQ_TYPE      = "POST";
    const NO_RETRY_STATUS_CODES = array(400, 401, 403, 410, 422);
    const SERVER_ANONYMIZATION  = "SP-Anonymous";

    /**
     * Settings for the Synchronous Emitter
     * - Buffer: the amount of events that will occur before sending begins
     */
    const SYNC_BUFFER = 50;

    /**
     * Settings for the Socket Emitter
     * - Buffer: the amount of events that will occur before sending begins
     * - Timeout: the time allowed for sending to the socket before we attempt a reconnect
     */
    const SOCKET_BUFFER  = 50;
    const SOCKET_TIMEOUT = 30;

    /**
     * Settings for the Asynchronous Rolling Curl Emitter
     * - Buffer: the amount of events that will occur before sending begins
     * - Amount: the amount of times we need to reach the buffer limit
     *   before we initiate sending
     * - Window: the amount of concurrent curl requests being made
     */
    const CURL_BUFFER      = 50;
    const CURL_AMOUNT_POST = 50;
    const CURL_WINDOW_POST = 10;
    const CURL_AMOUNT_GET  = 250;
    const CURL_WINDOW_GET  = 30;

    /**
     * Settings for the background File Emitter
     * - Count: The amount of workers that are created
     * - Buffer: the amount of events that will occur before sending begins
     * - Timeout: the amount of time the worker will wait before looking for new log files to process
     *   NOTE: This occurs 5 times before the worker kills itself.
     *         If a new file is found after a timeout the counter will reset.
     * - Folder: the name of the folder which will be created in the root
     *   of this project.  Will hold all of the worker folders and the
     *   'failed' log folder.
     * - Buffer: the amount of events which will be stored in a single
     *   curl. For GET this is always 1.
     * - Window: the amount of concurrent curl requests being made.
     *   NOTE: Each worker will be sending the same amount of concurrent
     *         events.  If lots of logs are failing reduce the concurrent
     *         sending limit.
     */
    const WORKER_COUNT       = 2;
    const WORKER_BUFFER      = 250;
    const WORKER_TIMEOUT     = 15;
    const WORKER_FOLDER      = "temp/";
    const WORKER_BUFFER_POST = 50;
    const WORKER_BUFFER_GET  = 1;
    const WORKER_WINDOW_POST = 10;
    const WORKER_WINDOW_GET  = 30;

    /**
     * Custom handler to turn all PHP Warnings into ErrorExceptions
     */
    public function warning_handler() {
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        }, E_WARNING);
    }
}
