<?php
// Parse Arguments from command line
$args = parse($argv);

if (!isset($args["type"])) {
    die("--type must be given");
}

// Worker Params
$type = $args["type"];
$dir = $args["file_path"]."/";
$url = $args["url"];
$timeout = $args["timeout"];

if ($type == "POST") {
    $buffer_size = 50;
    $rolling_window = 5;
}
else {
    $buffer_size = 1;
    $rolling_window = 25;
}

// Worker Loop
$loop = true;
$count = 0;

while ($loop && $count <= 1) {
    // Try to fetch a file
    $path = getEventsFile($dir);
    if (strlen($path) > 0) {
        // If files found reset the timeout counter
        $count = 0;

        // Rename the events file
        $path = $dir.$path;
        $path = renameEventsLog($path);

        // Consume File
        $ret = consume($url, $path, $type, $buffer_size, $rolling_window);
        if (!$ret) {
            copy($path, dirname(dirname($path))."/failed-logs/failed-".rand().".log");
        }
        unlink($path);
    }
    else {
        // No files to consume currently, have a sleep
        sleep($timeout);
        $count++;
    }
}

// Exit when there are no more files or time limit reached
exit(0);

//--- Functions ---//

// Return an events log for the worker
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
        print("file: $old does not exist");
        exit(0);
    }
    if (!rename($old, $path)) {
        print("error renaming from $old to new\n");
        exit(1);
    }
    return $path;
}

/**
 * Consumes the events file
 * - fires the rolling curl function
 *
 * @param string $url
 * @param string $file
 * @param string $type
 * @param int $buffer_size
 * @param int $rolling_window
 * @return bool - Returns the success of the transmission
 */
function consume($url, $file, $type, $buffer_size, $rolling_window) {
    // Get the file contents
    $contents = file_get_contents($file);
    $lines = explode("\n", $contents);

    // Create the payload buffer and curl_buffer
    $buffer = array();
    $curl_buffer = array();

    // Iterate through all of the lines in the log file.
    foreach ($lines as $line) {
        if (!trim($line)) {
            continue;
        }
        $payload = json_decode($line, true);
        if ($type == "POST") {
            // Add Payloads into the buffer until we reach the limit.
            array_push($buffer,$payload);
            if (count($buffer) >= $buffer_size) {
                $data = returnPostRequest($buffer);
                array_push($curl_buffer, returnCurlRequest($data, $url, $type));
                $buffer = array();
            }
        }
        else {
            $data = http_build_query($payload);
            array_push($curl_buffer, returnCurlRequest($data, $url, $type));
        }
    }

    // If there are any events left over in the buffer
    // - or if the buffer_size was too big for the amount of events...
    if (count($buffer) != 0) {
        if ($type == "POST") {
            $data = returnPostRequest($buffer);
            array_push($curl_buffer, returnCurlRequest($data, $url, $type));
        }
    }

    // Start sending requests
    return rollingCurl($curl_buffer, $rolling_window);
}

// Worker Functions
/**
 * Create a curl object
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
    $post_req_schema = "iglu:com.snowplowanalytics.snowplow/payload_data/jsonschema/1-0-1";
    $data = json_encode(array("schema" => $post_req_schema, "data" => $buffer));
    return $data;
}

/**
 * Asynchronously sends curl requests.
 * - Prevents the queue from being held up by
 *   starting new requests as soon as any are done.
 *
 * @param array $curls - array of curls to be sent
 * @param int $rolling_window - amount of concurrent requests
 * @return bool
 */
function rollingCurl($curls, $rolling_window) {
    $master = curl_multi_init();

    // Add curls to handler.
    $limit = ($rolling_window <= count($curls)) ? $rolling_window : count($curls);
    for ($i = 0; $i < $limit; $i++) {
        $ch = $curls[$i];
        curl_multi_add_handle($master, $ch);
    }
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

                // Close the finished curl handler.
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
