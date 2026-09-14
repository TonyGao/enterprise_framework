# Universal Entity Cache Listener

> [中文](universal_cache_listener.md) | English

## Overview

The `UniversalEntityCacheListener` is a unified cache-management solution that automatically clears caches when entity data changes. Compared to creating a separate listener per entity, this approach is simpler, easier to maintain and more extensible.

## Key Features

### 1. Unified Management
- One listener handles all entities that require cache clearing
- Watched entities are managed centrally via configuration
- Avoids duplicate code and improves reuse

### 2. Configuration-Driven
- Configure watched entities in `config/services.yaml`
- Add/remove watched entities dynamically
- Adjust the watch scope without code changes

### 3. Automated Handling
- Listens to entity `postPersist`, `postUpdate`, `postRemove` events
- Automatically calls `DataGridService::clearEntityCache()` to clear related caches
- No manual cache-clearing calls in controllers

## Configuration

### 1. Configure watched entities in services.yaml

```yaml
parameters:
    # cache listener config
    app.cache_watched_entities:
        - 'App\Entity\Organization\Position'
        - 'App\Entity\Organization\Department'
        - 'App\Entity\Organization\Company'
        # add other entities that need watching

services:
    # universal entity cache listener
    App\EventListener\Entity\UniversalEntityCacheListener:
        arguments:
            $dataGridService: '@App\Service\Platform\DataGridService'
            $watchedEntities: '%app.cache_watched_entities%'
        tags:
            - { name: doctrine.event_listener, event: postPersist }
            - { name: doctrine.event_listener, event: postUpdate }
```

### 2. Extending

To watch a new entity, add its class name to `app.cache_watched_entities`. No code changes are needed.
