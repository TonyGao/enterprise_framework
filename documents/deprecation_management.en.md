# Deprecation Warning Management Guide

> [中文](deprecation_management.md) | English

This document explains how to manage and silence deprecation warnings in a Symfony project.

## Problem

In PHP 8.1+, using implicit nullable parameters (e.g. `\Throwable $exception = null`) produces a deprecation warning:

```
App\DataCollector\AssetDuplicationCollector::collect(): Implicitly marking parameter $exception as nullable is deprecated, the explicit nullable type must be used instead
```

## Fixed Files

The following files have had their deprecation warnings fixed:

### 1. AssetDuplicationCollector.php
```php
// before
public function collect(Request $request, Response $response, \Throwable $exception = null)

// after
public function collect(Request $request, Response $response, ?\Throwable $exception = null)
```

### 2. MenuStaticGenerator.php
```php
// before
public function generateStaticMenu(SymfonyStyle $io = null): void

// after
public function generateStaticMenu(?SymfonyStyle $io = null): void
```

### 3. FormFieldBuilderService.php
```php
// before
public function buildFields(FormBuilderInterface $builder, string $entityClass, callable $customOptionsCallback = null): void

// after
public function buildFields(FormBuilderInterface $builder, string $entityClass, ?callable $customOptionsCallback = null): void
```

## Principle

Use explicit nullable types (`?Type`) for any parameter that can be `null`, instead of relying on the implicit nullable default. This keeps the code forward-compatible with PHP 8.4+, where implicit nullable parameters are removed entirely.

## Verification

Run the test suite and check the log for any remaining deprecation warnings:

```bash
php bin/phpunit
tail -f var/log/dev.log | grep -i deprecat
```
