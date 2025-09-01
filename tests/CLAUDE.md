# Tests Module - CLAUDE.md

## Testing Overview

The test suite uses PHPUnit 9.5+ for comprehensive testing of the Snowplow PHP Tracker. Tests are organized into unit tests for individual components and integration tests for end-to-end validation. The suite includes mock collectors and fixtures for reliable, isolated testing.

## Test Organization

### Directory Structure
```
tests/
├── bootstrap.php           # PHPUnit bootstrap
├── ClassInitTests/         # Class initialization tests
├── EmitterTests/          # Emitter-specific tests
├── IntegrationTest.php    # End-to-end tests
└── mountebank_mocks/      # Mock collector configs
```

## Testing Patterns

### Test Class Structure
```php
// ✅ Extend PHPUnit TestCase
class TrackerTest extends TestCase {
    protected function setUp(): void {
        // Initialize test fixtures
    }
}
```

### Fixture Management
```php
// ✅ Use setUp for common fixtures
protected function setUp(): void {
    $this->emitter = new SyncEmitter("localhost", "http", "POST", 1, true);
    $this->subject = new Subject();
}
```

### Assertion Patterns
```php
// ✅ Specific assertions
$this->assertEquals(expected, actual);
$this->assertInstanceOf(Tracker::class, $tracker);
$this->assertCount(2, $emitters);
```

## Unit Testing Patterns

### Constructor Testing
```php
// ✅ Test all constructor parameters
public function testTrackerInit() {
    $tracker = new Tracker($emitter, $subject, "ns", "app", false);
    $this->assertEquals("ns", $tracker->returnNamespace());
}
```

### State Verification
```php
// ✅ Verify internal state changes
public function testSubjectUpdate() {
    $subject->setUserId("user123");
    $settings = $subject->returnTrackerSettings();
    $this->assertEquals("user123", $settings["uid"]);
}
```

### Edge Cases
```php
// ✅ Test null/empty values
public function testNullHandling() {
    $payload = new Payload(null);
    $this->assertArrayNotHasKey("ttm", $payload->get());
}
```

## Emitter Testing Patterns

### Mock Collector Setup
```php
// ✅ Use localhost for testing
private function getSyncEmitter($type) {
    return new SyncEmitter("localhost", "http", $type, 1, true);
}
```

### Debug Mode Testing
```php
// ✅ Enable debug for result inspection
$emitter = new CurlEmitter("localhost", "http", "POST", 1, true);
$results = $emitter->returnRequestResults();
$this->assertCount(1, $results);
```

### Buffer Testing
```php
// ✅ Test buffer limits
public function testBufferFlush() {
    $emitter = new SyncEmitter("localhost", "http", "POST", 2);
    // Add events and verify flush behavior
}
```

## Integration Testing Patterns

### End-to-End Flow
```php
// ✅ Complete tracking flow
public function testCompleteTracking() {
    $tracker = $this->setupTracker();
    $tracker->trackPageView("http://example.com");
    $tracker->flushEmitters();
    // Verify collector received event
}
```

### Mock Collector Verification
```php
// ✅ Mountebank integration
public function testCollectorReceivesEvent() {
    // Setup mountebank imposter
    $tracker->trackStructEvent("category", "action");
    // Query mountebank for received requests
}
```

## Common Testing Pitfalls

### 1. Network Dependencies
```php
// ❌ Real network calls in tests
$emitter = new SyncEmitter("collector.snowplow.io");

// ✅ Use localhost/mocks
$emitter = new SyncEmitter("localhost", "http");
```

### 2. Missing TearDown
```php
// ❌ Leave resources open
public function testSocket() {
    $socket = fsockopen("localhost", 8080);
}

// ✅ Clean up resources
protected function tearDown(): void {
    if ($this->socket) fclose($this->socket);
}
```

### 3. Debug Mode Cleanup
```php
// ❌ Leave debug files
$emitter = new FileEmitter($uri, "http", "POST", 1, true);

// ✅ Turn off debug after test
$tracker->turnOffDebug(true);
```

## PHPUnit Configuration

### Key phpunit.xml Settings
```xml
<phpunit bootstrap="tests/bootstrap.php"
         convertWarningsToExceptions="true"
         stopOnFailure="false">
```

### Coverage Configuration
```xml
<filter>
    <whitelist>
        <directory suffix=".php">src/</directory>
    </whitelist>
</filter>
```

## Mock Data Patterns

### Event Fixtures
```php
// ✅ Reusable event data
private function getPageViewData() {
    return [
        "url" => "http://example.com",
        "page" => "Home",
        "refr" => "http://google.com"
    ];
}
```

### Context Fixtures
```php
// ✅ Valid context structure
private function getContext() {
    return [[
        "schema" => "iglu:com.example/context/1-0-0",
        "data" => ["key" => "value"]
    ]];
}
```

## Testing Commands

```bash
# Run all tests
php vendor/bin/phpunit

# Run specific test class
php vendor/bin/phpunit tests/ClassInitTests/TrackerTest.php

# Run with coverage
php vendor/bin/phpunit --coverage-html coverage/

# Run specific test method
php vendor/bin/phpunit --filter testTrackerInit
```

## Mountebank Mock Setup

### Imposter Configuration
```json
{
  "port": 4545,
  "protocol": "http",
  "stubs": [{
    "responses": [{
      "is": {"statusCode": 200}
    }]
  }]
}
```

### Docker Setup
```bash
# Start mountebank
docker run -p 2525:2525 -p 4545:4545 \
  -v $(pwd)/tests/mountebank_mocks:/imposters \
  bbyars/mountebank:2.4.0
```

## Quick Reference

### Test Naming Convention
- `test{MethodName}` - Standard test
- `test{MethodName}With{Condition}` - Conditional test
- `test{MethodName}Throws{Exception}` - Exception test

### Common Assertions
- `assertEquals()` - Value equality
- `assertInstanceOf()` - Type checking
- `assertCount()` - Array/collection size
- `assertArrayHasKey()` - Array key existence
- `expectException()` - Exception testing

### Test Doubles
- Use debug mode for result inspection
- Mock collectors with localhost
- Mountebank for integration testing

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