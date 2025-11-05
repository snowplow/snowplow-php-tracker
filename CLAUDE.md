# Snowplow PHP Tracker - CLAUDE.md

## Project Overview

The Snowplow PHP Tracker is a library for sending analytics events to Snowplow collectors. It provides a robust event tracking system with multiple emitter strategies, configurable payloads, and comprehensive event types. The tracker follows an object-oriented architecture with clear separation between event construction, payload management, and event transmission.

## Development Commands

```bash
# Install dependencies
docker-compose run --rm snowplow composer.phar install

# Run test suite
docker-compose run --rm snowplow script/tests.sh

# Run specific PHPUnit tests
php vendor/bin/phpunit

# Run tests with coverage
php vendor/bin/phpunit --coverage-clover=build/logs/clover.xml
```

## Architecture

### System Design
The tracker uses a layered architecture with three core components:
1. **Tracker**: Main interface for tracking events, manages Subject and Emitters
2. **Subject**: Contains user/session information attached to events
3. **Emitters**: Responsible for sending events to collectors with different strategies

### Key Components
- `Tracker`: Event tracking orchestration and API
- `Payload`: Event data structure and encoding
- `Subject`: User context and session information
- `Emitter`: Base class for event transmission strategies
- `Constants`: Configuration constants and defaults

## Core Architectural Principles

### 1. Separation of Concerns
```php
// ✅ Each class has single responsibility
$subject = new Subject();
$emitter = new SyncEmitter($uri);
$tracker = new Tracker($emitter, $subject);
```

### 2. Strategy Pattern for Emitters
```php
// ✅ Different emitter strategies
new SyncEmitter($uri);     // Synchronous sending
new CurlEmitter($uri);      // Async with curl
new SocketEmitter($uri);    // Socket-based
```

### 3. Immutable Event Construction
```php
// ✅ Events built through Payload objects
$payload = new Payload($timestamp);
$payload->add("e", "pv");
```

## Layer Organization & Responsibilities

### Root Source (`src/`)
- **Core Classes**: `Tracker.php`, `Subject.php`, `Payload.php`, `Emitter.php`
- **Configuration**: `Constants.php` - Central configuration
- **Purpose**: Main tracker functionality

### Emitters (`src/Emitters/`)
- **Implementations**: Various emitter strategies (Curl, Socket, Sync, File)
- **Retry Management**: `RetryRequestManager.php`
- **Purpose**: Handle event transmission to collectors

### Tests (`tests/`)
- **Unit Tests**: `ClassInitTests/` - Class initialization tests
- **Emitter Tests**: `EmitterTests/` - Emitter-specific tests
- **Integration**: `IntegrationTest.php` - End-to-end testing

## Critical Import Patterns

### Namespace Structure
```php
// ✅ Correct namespace usage
namespace Snowplow\Tracker;
use Snowplow\Tracker\Emitters\CurlEmitter;
```

### Dependency Imports
```php
// ✅ External dependencies first
use Ramsey\Uuid\Uuid;
use ErrorException;
// Then internal dependencies
use Snowplow\Tracker\Emitter;
```

## Essential Library Patterns

### Event Tracking Pattern
```php
// ✅ Track page view
$tracker->trackPageView($page_url, $page_title);

// ✅ Track structured event
$tracker->trackStructEvent($category, $action, $label);
```

### Emitter Configuration
```php
// ✅ Configure emitter with buffer
$emitter = new CurlEmitter(
    $uri,
    "https",  // protocol
    "POST",   // type
    50        // buffer_size
);
```

### Subject Configuration
```php
// ✅ Set user context
$subject = new Subject();
$subject->setUserId("user123");
$subject->setPlatform("web");
```

## Model Organization Pattern

### Payload Structure
```php
// ✅ Payload with base64 encoding
$payload->addJson($data, true, "ue_px", "ue_pr");

// ✅ Add standard fields
$payload->add("e", "pv");  // Event type
$payload->add("url", $url); // Page URL
```

### Event Data Model
```php
// ✅ Structured event fields
array(
    "e" => "se",        // Event type
    "se_ca" => $cat,    // Category
    "se_ac" => $action  // Action
)
```

## Common Pitfalls & Solutions

### 1. Buffer Management
```php
// ❌ Not flushing buffer
$tracker->trackPageView($url);

// ✅ Force flush when needed
$tracker->flushEmitters();
```

### 2. Timestamp Handling
```php
// ❌ Wrong timestamp format
$tracker->trackPageView($url, null, null, time());

// ✅ Use milliseconds
$tracker->trackPageView($url, null, null, time() * 1000);
```

### 3. Context Encoding
```php
// ❌ Raw context array
$context = ["user" => "123"];

// ✅ Properly structured context
$context = [
    ["schema" => "iglu:...", "data" => ["user" => "123"]]
];
```

### 4. Emitter Array Initialization
```php
// ❌ Single emitter as array element
$tracker = new Tracker([$emitter], $subject);

// ✅ Single emitter or array
$tracker = new Tracker($emitter, $subject);
// Or multiple: new Tracker([$e1, $e2], $subject);
```

## File Structure Template

```
snowplow-php-tracker/
├── src/
│   ├── Constants.php         # Configuration constants
│   ├── Tracker.php           # Main tracker class
│   ├── Subject.php           # User context
│   ├── Payload.php           # Event payload
│   ├── Emitter.php           # Base emitter
│   └── Emitters/
│       ├── CurlEmitter.php
│       ├── SyncEmitter.php
│       ├── SocketEmitter.php
│       └── FileEmitter.php
├── tests/
│   ├── bootstrap.php
│   ├── ClassInitTests/
│   ├── EmitterTests/
│   └── IntegrationTest.php
├── composer.json
├── phpunit.xml
└── docker-compose.yml
```

## Quick Reference

### Event Types Checklist
- [ ] Page View: `trackPageView()`
- [ ] Structured: `trackStructEvent()`
- [ ] Unstructured: `trackUnstructEvent()`
- [ ] Screen View: `trackScreenView()`
- [ ] E-commerce: `trackEcommerceTransaction()`

### Emitter Selection Guide
- **SyncEmitter**: Simple, blocking requests
- **CurlEmitter**: Async, high-volume, batching
- **SocketEmitter**: Persistent connection
- **FileEmitter**: Background processing with workers

### Required Event Fields
- `e`: Event type (required)
- `tv`: Tracker version (auto-added)
- `tna`: Namespace (optional)
- `aid`: App ID (optional)
- `dtm`: Device timestamp (auto-added)

### Testing Patterns
```php
// ✅ Test with mock collector
$emitter = new SyncEmitter("localhost", "http", "POST", 1, true);
$tracker = new Tracker($emitter, new Subject());
```

## Contributing to CLAUDE.md

When adding or updating content in this document, please follow these guidelines:

### File Size Limit
- **CLAUDE.md must not exceed 40KB** (currently ~19KB)
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

### Multiple CLAUDE.md Files
- **Directory-specific CLAUDE.md files** can be created for specialized modules
- Follow the same structure and guidelines as this root CLAUDE.md
- Keep them focused on directory-specific patterns and conventions
- Maximum 20KB per directory-specific CLAUDE.md file

### Instructions for LLMs
When editing files in this repository, **always check for CLAUDE.md guidance**:

1. **Look for CLAUDE.md in the same directory** as the file being edited
2. **If not found, check parent directories** recursively up to project root
3. **Follow the patterns and conventions** described in the applicable CLAUDE.md
4. **Prioritize directory-specific guidance** over root-level guidance when conflicts exist