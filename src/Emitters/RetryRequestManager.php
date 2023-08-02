<?php
/*
    RetryRequestManager.php

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

namespace Snowplow\Tracker\Emitters;

/**
 * Manages the state for retrying failed requests in emitters.
 */
class RetryRequestManager {
    /// The number of times the current request has been retried
    private int $retry_count = 0;
    /// The maximum number of times to retry a request
    private int $max_retry_attempts;
    /// The number of milliseconds to backoff before retrying a request
    private int $backoff_ms;

    public function __construct(int $max_retry_attempts = NULL, int $backoff_ms = NULL) {
        $this->max_retry_attempts = $max_retry_attempts == NULL ? 1 : $max_retry_attempts;
        $this->backoff_ms = $backoff_ms == NULL ? 10 : $backoff_ms;
    }

    public function incrementRetryCount(): void {
        $this->retry_count++;
    }

    public function shouldRetryFailedRequest(): bool {
        return $this->shouldRetryForStatusCode(0);
    }

    public function shouldRetryForStatusCode(int $status_code): bool {
        if ($this->retry_count >= $this->max_retry_attempts) {
            return false;
        }

        // successful requests should not be retried
        if ($this->isGoodStatusCode($status_code)) {
            return false;
        }

        // don't retry certain 4xx codes, retry everything else
        $dont_retry_codes = array(400, 401, 403, 410, 422);
        return !in_array($status_code, $dont_retry_codes);
    }

    public function isGoodStatusCode(int $status_code): bool {
        return $status_code >= 200 && $status_code < 300;
    }

    public function backoff(): void {
        usleep(pow($this->backoff_ms, $this->retry_count) * 1000);
    }
}

?>
