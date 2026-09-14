# SensioFrameworkExtraBundle Deprecation Warning Fix

> [中文](sensio_framework_extra_bundle_deprecation_fix.md) | English

## Problem

Using SensioFrameworkExtraBundle v6.2.10 in a Symfony 6.4 project produces this deprecation warning:

```
Method "Symfony\Component\DependencyInjection\Extension\ExtensionInterface::load()" might add "void" as a native return type declaration in the future. Do the same in implementation "Sensio\Bundle\FrameworkExtraBundle\DependencyInjection\SensioFrameworkExtraExtension" now to avoid errors or add an explicit @return annotation to suppress this message.
```

## Root Cause

1. **SensioFrameworkExtraBundle is abandoned**: it is officially marked as abandoned and no longer maintained.
2. **Version incompatibility**: v6.2.10 (released 2023-02-24) is the last release and is not fully compatible with Symfony 6.4's new type-declaration requirements.
3. **Integrated into Symfony core**: all functionality provided by SensioFrameworkExtraBundle is now part of Symfony core.

## Solution

### Option 1: Migrate to Symfony native attributes (recommended)

#### 1. Update route annotations

Migrate existing `@Route` annotations to PHP 8 attributes:

**Before:**
```php
use Symfony\Component\Routing\Annotation\Route;

class TestController extends AbstractController
{
    /**
     * @Route("/test/{element}", methods="GET", name="element_page")
     */
    public function element(Request $request, $element): Response
    {
        // ...
    }

    /**
     * @Route("/", methods="GET", name="index_page")
     */
    public function index(Request $request): Response
    {
        // ...
    }
}
```

**After:**
```php
use Symfony\Component\Routing\Attribute\Route;

class TestController extends AbstractController
{
    #[Route('/test/{element}', methods: ['GET'], name: 'element_page')]
    public function element(Request $request, $element): Response
    {
        // ...
    }

    #[Route('/', methods: ['GET'], name: 'index_page')]
    public function index(Request $request): Response
    {
        // ...
    }
}
```

#### 2. Update ParamConverter usage

Replace `@ParamConverter` with the `#[MapEntity]`/`#[MapRequestPayload]` attributes or explicit `#[Entity]` handling.

#### 3. Remove the bundle

Once all features are migrated, remove SensioFrameworkExtraBundle from `config/bundles.php` and `composer.json`.

### Option 2: Suppress the warning (temporary)

Add an explicit `@return` annotation to the affected extension method to suppress the message:

```php
/**
 * @return void
 */
public function load(...) { ... }
```

## Verification

```bash
php bin/console cache:clear
tail -f var/log/dev.log | grep -i deprecat
```

The deprecation warning should no longer appear, and the application remains fully functional.
