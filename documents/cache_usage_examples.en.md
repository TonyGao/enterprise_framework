# Cache Usage Examples

> [中文](cache_usage_examples.md) | English

## Method Signature

```php
public function getTableData(
    string $entityClass,
    int $page = 1,
    int $pageSize = 20,
    ?CacheConfig $cacheConfig = null
): array
```

### Parameters

- `$entityClass`: the entity class name
- `$page`: page number (starting from 1)
- `$pageSize`: rows per page
- `$cacheConfig`: cache config object (default null = no caching)

### CacheConfig Class

`CacheConfig` provides semantic cache configuration options:

```php
// disable cache
CacheConfig::disabled()

// enable cache (default 1 hour)
CacheConfig::cached()

// minute-level cache
CacheConfig::minutes(30)  // cache 30 minutes

// hour-level cache
CacheConfig::hours(2)     // cache 2 hours

// day-level cache
CacheConfig::days(1)      // cache 1 day

// semantic strings
CacheConfig::cached('30 minutes')
CacheConfig::cached('2 hours')
CacheConfig::cached('1 day')
```

## Usage Examples

### 1. Data that rarely changes (recommend long caching)

Applies to: position, department, company and other organization data

```php
$result = $dataGridService->getTableData(
    'App\\Entity\\Organization\\Position',
    $page,
    $pageSize,
    CacheConfig::hours(24)
);
```

### 2. Frequently changing data (short caching or disabled)

Applies to: logs, counters and other volatile data

```php
$result = $dataGridService->getTableData(
    'App\\Entity\\Some\\Log',
    $page,
    $pageSize,
    CacheConfig::minutes(1)
);
```

### 3. Real-time data (disable cache)

```php
$result = $dataGridService->getTableData(
    'App\\Entity\\Some\\RealTime',
    $page,
    $pageSize,
    CacheConfig::disabled()
);
```

## Notes

- Cache is auto-cleared when the watched entity changes (see `documents/universal_cache_listener.md`).
- Use the semantic string form for readability: `'cached 3 hours'`.
- For advanced control, build a `CacheConfig` object with custom tags and TTL.
