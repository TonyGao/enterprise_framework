# Semantic Cache Configuration Guide

> [中文](semantic_cache_configuration.md) | English

## Overview

`DataGridService` now supports semantic strings for cache configuration, making code more intuitive and readable. You can pass strings like `'cached 3 hours'` directly instead of manually creating a `CacheConfig` object.

## Basic Usage

### Using semantic strings directly

```php
// cache for 3 hours
$result = $dataGridService->getTableData(
    'App\\Entity\\Organization\\Position',
    $page,
    $pageSize,
    'cached 3 hours'
);

// cache for 30 minutes
$result = $dataGridService->getTableData(
    'App\\Entity\\Organization\\Position',
    $page,
    $pageSize,
    'cached 30 minutes'
);

// disable cache
$result = $dataGridService->getTableData(
    'App\\Entity\\Organization\\Position',
    $page,
    $pageSize,
    'disabled'
);
```

## Supported Semantic Formats

### 1. With the `cached` prefix

```php
'cached 3 hours'     // cache 3 hours
'cached 30 minutes'  // cache 30 minutes
'cached 2 days'      // cache 2 days
'cached 1.5 hours'   // cache 1.5 hours
'cached 90 minutes'  // cache 90 minutes
'cached 1 week'      // cache 1 week
'cached'             // cache 1 hour (default)
```

### 2. Direct time formats

```php
'3 hours'      // auto-enable cache for 3 hours
'30 minutes'   // auto-enable cache for 30 minutes
'2 days'       // auto-enable cache for 2 days
'45 seconds'   // auto-enable cache for 45 seconds
```

### 3. Disabling cache

```php
'disabled'     // disable caching
```

## Supported Units

- `seconds`, `minutes`, `hours`, `days`, `weeks`
- Supports decimal values (e.g. `1.5 hours`)
- The `cached` prefix and time units are case-insensitive

## Notes

- Cache keys are derived automatically from the entity, query parameters and page state.
- When entity data changes, the universal entity cache listener clears related caches automatically.
- Use semantic strings to keep configuration readable; for advanced control, pass a `CacheConfig` object instead.
