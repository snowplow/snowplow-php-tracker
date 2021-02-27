<?php
/*
    Worker.php

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

// Parse Arguments from command line

$args = parse($argv);

// Check that all of the parameters were set

if (!isset($args["type"])) {
    die("--type must be given");
}
if (!isset($args["file_path"])) {
    die("--file_path must be given");
}
if (!isset($args["url"])) {
    die("--url must be given");
}
if (!isset($args["timeout"])) {
    die("--timeout must be given");
}
if (!isset($args["window"])) {
    die("--window must be given");
}
if (!isset($args["buffer"])) {
    die("--buffer must be given");
}

// Worker Parameters

$type           = $args["type"];
$dir            = $args["file_path"];
$url            = $args["url"];
$timeout        = $args["timeout"];
$buffer_size    = $args["buffer"];
$rolling_window = $args["window"];

// Worker Loop

$loop = true;
$count = 0;

while ($loop && $count < 5) {
    // Try to fetch a file
    $path = getEventsFile($dir);
    if (strlen($path) > 0) {
        // If files found reset the timeout counter
        $count = 0;

        // Rename the events file
        $path = $dir.$path;
        $path = renameEventsLog($path);

        // Consume, update sent tstamp and send events in the file
        $final_payload_buffer = consumeFile($path, $type, $buffer_size);
        $curl_buffer = mkCurls($final_payload_buffer, $url, $type);
        $ret = execute($curl_buffer, $rolling_window);

        // If any of the curls failed copy the log-file to the failed directory
        // Currently is set as an 'all or nothing' failure approach.
        if (!$ret) {
            copy($path, dirname(dirname($path))."/failed-logs/failed-".rand().".log");
        }

        // Delete the log-file
        unlink($path);
    }
    else {
        // No files to consume currently
        sleep($timeout);
        $count++;
    }
}

// Exit when there are no more files or time limit reached

exit(0);

//--- Functions ---//

/**
 * Get an events log from the workers folder
 *
 * @param string $dir
 * @return bool|string
 */
function getEventsFile($dir) {
    if (is_dir($dir)) {
        if ($dh = opendir($dir)) {
            while (($file = readdir($dh)) !== false) {
                if (strpos($file,"events") !== false) {
                    closedir($dh);
                    return $file;
                }
            }
            closedir($dh);
            return false;
        }
    }
    return false;
}

/**
 * Parse cli arguments
 *
 * @param $argv
 * @return array $ret
 */
function parse($argv){
    $ret = array();
    for ($i = 0; $i < count($argv); ++$i) {
        $arg = $argv[$i];
        if ('--' != substr($arg, 0, 2)) {
            continue;
        }
        $ret[substr($arg, 2, strlen($arg))] = trim($argv[++$i]);
    }
    return $ret;
}

/**
 * Rename the events log
 *
 * @param string $path
 * @return string
 */
function renameEventsLog($path) {
    $dir = dirname($path);
    $old = $path;
    $path = $dir.'/consuming-'.rand().'.log';
    if(!file_exists($old)) {
        print("File: ".$old." does not exist");
        exit(0);
    }
    if (!rename($old, $path)) {
        print("Error: renaming from ".$old." to new\n");
        exit(1);
    }
    return $path;
}

/**
 * Consumes the events file, adds the sent tstamp
 * The final_payload_buffer is:
 *  - either an array of events (GET)
 *  - or an array of payloads of events
 *
 * @param string $file
 * @param string $type
 * @param int $buffer_size
 * @return array - The final_payload_buffer
 */
function consumeFile($file, $type, $buffer_size) {
    // Get the file contents
    $contents = file_get_contents($file);
    $lines = explode("\n", $contents);

    // Create the payload buffer and final_payload_buffer
    $buffer = array();
    $final_payload_buffer = array();

    // Iterate through all of the lines in the log file.
    foreach ($lines as $line) {
        if (!trim($line)) {
            continue;
        }
        $payload = json_decode($line, true);
        if ($type == "POST") {
            array_push($buffer, $payload);
            if (count($buffer) >= $buffer_size) {
                array_push($final_payload_buffer, $buffer);
                $buffer = array();
            }
        }
        else {
            array_push($final_payload_buffer, $payload);
        }
    }

    // If there are any events left over in the buffer or if the buffer_size was bigger than the amount of events
    if (count($buffer) != 0) {
        if ($type == "POST") {
            array_push($final_payload_buffer, $buffer);
        }
    }
    return $final_payload_buffer;
}

/**
 * Makes the curls buffer
 *
 * @param array $final_payload_array
 * @param string $url
 * @param string $type
 * @return array - The curl requests buffer
 */
function mkCurls($final_payload_array, $url, $type) {
    // Create the curl buffer
    $curl_buffer = array();

    if ($type == 'POST') {
        foreach ($final_payload_array as $buffer) {
            $data = returnPostRequest(array_map('updateStm', $buffer));
            array_push($curl_buffer, returnCurlRequest($data, $url, $type));
        }
    }
    else {
        foreach ($final_payload_array as $event) {
            $data = http_build_query(updateStm($event));
            array_push($curl_buffer, returnCurlRequest($data, $url, $type));
        }
    }
    return $curl_buffer;
}

// Worker Functions

/**
 * Creates a GET or POST curl resource
 *
 * @param string $payload
 * @param string $url
 * @param string $type
 * @return resource
 */
function returnCurlRequest($payload, $url, $type) {
    $ch = curl_init($url);
    if ($type == "POST") {
        $header = array(
            'Content-Type: application/json; charset=utf-8',
            'Accept: application/json',
            'Content-Length: '.strlen($payload));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    }
    else {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_URL, $url."?".$payload);
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    return $ch;
}

/**
 * Compiles events from buffer into a single string.
 *
 * @param array $buffer
 * @return string - Returns a json_encoded string with all of the events to be sent.
 */
function returnPostRequest($buffer) {
    $post_req_schema = "iglu:com.snowplowanalytics.snowplow/payload_data/jsonschema/1-0-4";
    $data = json_encode(array("schema" => $post_req_schema, "data" => $buffer));
    return $data;
}

/**
 * Returns the event payload with current time as stm.
 *
 * @param array $payload
 * @return array - Updated event payload
 */
function updateStm($payload) {
    $payload["stm"] = strval(time() * 1000);
    return $payload;
}

/**
 * Asynchronously sends curl requests.
 * - Prevents the queue from being held up by
 *   starting new requests as soon as any are done.
 *
 * @param array $curls - array of curls to be sent
 * @param int $rolling_window - amount of concurrent requests
 * @return bool - Returns the success of the transmission
 */
function execute($curls, $rolling_window) {
    $master = curl_multi_init();

    // Add curls to handler.
    $limit = ($rolling_window <= count($curls)) ? $rolling_window : count($curls);
    for ($i = 0; $i < $limit; $i++) {
        $ch = $curls[$i];
        curl_multi_add_handle($master, $ch);
    }

    // Execute the rolling curl
    do {
        $execrun = curl_multi_exec($master, $running);
        while ($execrun == CURLM_CALL_MULTI_PERFORM);
        if ($execrun != CURLM_OK) {
            break;
        }
        while ($done = curl_multi_info_read($master)) {
            $info = curl_getinfo($done['handle']);
            if ($info['http_code'] == 200) {
                // If there are still curls in the queue add them to the handler.
                if ($i < count($curls)) {
                    $ch = $curls[$i++];
                    curl_multi_add_handle($master, $ch);
                }

                // Close and remove the finished curl.
                curl_multi_remove_handle($master, $done['handle']);
                curl_close($done['handle']);
            }
            else {
                return false;
            }
        }
    } while ($running);

    curl_multi_close($master);
    return true;
}
