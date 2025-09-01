# Emitters Module - CLAUDE.md

## Module Overview

The Emitters module contains various strategies for sending tracked events to Snowplow collectors. Each emitter implements different transmission patterns optimized for specific use cases - from simple synchronous sending to complex asynchronous batch processing.

## Emitter Architecture

### Base Emitter Pattern
All emitters extend the base `Emitter` class and implement the `send()` method:
```php
public function send($buffer, $curl_send = false) {
    // Implementation specific to emitter type
}
```

### Buffer Management
Each emitter manages its own buffer based on configured size:
```php
// ✅ Buffer reaches limit, auto-flush
$this->addEventToBuffer($event);
if (count($this->buffer) >= $this->buffer_size) {
    $this->flush($this->buffer);
}
```

## Emitter Types & Use Cases

### SyncEmitter
**Use Case**: Simple, immediate event transmission
```php
// ✅ For low-volume, real-time tracking
$emitter = new SyncEmitter($uri, "https", "POST");
```

### CurlEmitter
**Use Case**: High-volume async batch processing
```php
// ✅ For production, high-throughput
$emitter = new CurlEmitter($uri, "https", "POST", 50);
```

### SocketEmitter
**Use Case**: Persistent connection scenarios
```php
// ✅ For continuous streaming
$emitter = new SocketEmitter($uri, "tcp", "POST");
```

### FileEmitter
**Use Case**: Background worker processing
```php
// ✅ For deferred, fault-tolerant sending
$emitter = new FileEmitter($uri, "https", "POST");
```

## Critical Implementation Patterns

### Request Building
```php
// ✅ POST request with schema
$body = array(
    "schema" => self::POST_REQ_SCHEMA,
    "data" => $buffer
);
```

### Error Handling
```php
// ✅ Retry logic for failures
if (!in_array($status_code, self::NO_RETRY_STATUS_CODES)) {
    $this->retry_manager->addRequest($request);
}
```

### Debug Mode
```php
// ✅ Debug tracking
if ($this->debug_mode) {
    $this->requests_results[] = $response;
}
```

## CurlEmitter Specific Patterns

### Rolling Window Management
```php
// ✅ Concurrent request limiting
private $rolling_window = 10; // Max concurrent
private $curl_buffer = array();
```

### Multi-Handle Processing
```php
// ✅ Batch curl execution
$mh = curl_multi_init();
foreach ($curl_handles as $ch) {
    curl_multi_add_handle($mh, $ch);
}
```

## SocketEmitter Specific Patterns

### Connection Management
```php
// ✅ Socket initialization
$socket = fsockopen($host, $port, $errno, $errstr, 30);
stream_set_timeout($socket, self::SOCKET_TIMEOUT);
```

### Stream Writing
```php
// ✅ Write with verification
$bytes_written = fwrite($socket, $request);
if ($bytes_written === false) {
    // Handle write failure
}
```

## FileEmitter Specific Patterns

### Worker File Management
```php
// ✅ Log file rotation
$filename = $this->dir.time()."_".$this->index.".log";
$this->writeToFile($filename, $events);
```

### Worker Process Control
```php
// ✅ Background worker spawning
exec("php Worker.php $worker_num > /dev/null &");
```

## Common Pitfalls & Solutions

### 1. Buffer Overflow
```php
// ❌ No buffer limit check
$this->buffer[] = $event;

// ✅ Check and flush
if (count($this->buffer) >= $this->buffer_size) {
    $this->flush($this->buffer);
}
```

### 2. Connection Failures
```php
// ❌ No timeout handling
$response = file_get_contents($url);

// ✅ Timeout configuration
$context = stream_context_create(['http' => ['timeout' => 30]]);
```

### 3. Debug File Permissions
```php
// ❌ Assume write permissions
file_put_contents($file, $data);

// ✅ Check permissions first
if ($this->write_perms && is_writable(dirname($file))) {
    file_put_contents($file, $data);
}
```

## Configuration Constants

Key constants from `Constants.php`:
- `SYNC_BUFFER`: 50
- `CURL_BUFFER`: 50
- `SOCKET_TIMEOUT`: 30
- `WORKER_COUNT`: 2
- `NO_RETRY_STATUS_CODES`: [400, 401, 403, 410, 422]

## Testing Emitters

### Mock Collector Setup
```php
// ✅ Test with local collector
$emitter = new SyncEmitter("localhost", "http", "POST", 1, true);
```

### Debug Mode Verification
```php
// ✅ Enable debug for testing
$emitter = new CurlEmitter($uri, "http", "POST", 1, true);
$results = $emitter->returnRequestResults();
```

## Quick Reference

### Emitter Constructor Parameters
1. `$uri` - Collector endpoint
2. `$protocol` - http/https/tcp
3. `$type` - GET/POST
4. `$buffer_size` - Events before flush
5. `$debug` - Enable debug mode

### Response Handling
- Success: Return `true`
- Retryable failure: Add to retry queue
- Non-retryable: Log and discard

### Server Anonymization
```php
// ✅ Enable anonymization
$emitter = new CurlEmitter($uri, "https", "POST", 50, false, null, true);
```

## Contributing to CLAUDE.md

When adding or updating content in this document, please follow these guidelines:

### File Size Limit
- **CLAUDE.md must not exceed 20KB** for directory-specific files
- Check file size after updates: `wc -c CLAUDE.md`
- Remove outdated content if approaching the limit

### Code Examples
- Keep all code examples **4 lines or fewer**
- Focus on the essential pattern, not complete implementations
- Use `// ❌` and `// ✅` to clearly show wrong vs right approaches

### Content Organization
- Add new patterns to existing sections when possible
- Create new sections sparingly to maintain structure
- Update the architectural principles section for major changes
- Ensure examples follow current codebase conventions

### Quality Standards
- Test any new patterns in actual code before documenting
- Verify imports and syntax are correct for the codebase
- Keep language concise and actionable
- Focus on "what" and "how", minimize "why" explanations

### Instructions for LLMs
When editing files in this repository, **always check for CLAUDE.md guidance**:

1. **Look for CLAUDE.md in the same directory** as the file being edited
2. **If not found, check parent directories** recursively up to project root
3. **Follow the patterns and conventions** described in the applicable CLAUDE.md
4. **Prioritize directory-specific guidance** over root-level guidance when conflicts exist