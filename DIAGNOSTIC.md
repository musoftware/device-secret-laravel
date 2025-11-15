# Diagnostic Guide for Installation Issues

If you're getting a dependency conflict when installing this package, follow these steps:

## Step 1: Check Your Laravel Version

Run this command in your **consuming project** (not the package directory):

```bash
composer show laravel/framework
```

Or check your `composer.json` for:
```json
"laravel/framework": "^X.Y"
```

## Step 2: Check PHP Version

This package requires **PHP 8.0 or higher**:

```bash
php -v
```

## Step 3: Check for Conflicts

Run this command to see what's conflicting:

```bash
composer why-not musoftware/device-secret-laravel ^1.1
```

## Step 4: Check Illuminate/Support Version

```bash
composer show illuminate/support
```

**Note:** Run these commands separately - `composer show` takes package names only, not version constraints.

## Common Issues and Solutions

### Issue 1: Using Laravel 7 or Earlier

**Problem:** Laravel 7 requires PHP 7.2.5-7.4, but this package requires PHP 8.0+

**Solution:** Upgrade to Laravel 8 or higher. Laravel 8 requires PHP 7.3+, Laravel 9+ requires PHP 8.0+.

### Issue 2: Repository Not Configured

**Problem:** Composer can't find the package

**Solution:** Add this to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/musoftware/device-secret-laravel.git"
        }
    ]
}
```

### Issue 3: Version Constraint Too Strict

**Problem:** Composer says the version constraint is too strict

**Solution:** This is just a warning. The package should still install. Use:

```bash
composer require musoftware/device-secret-laravel:^1.1 --update-with-dependencies
```

### Issue 4: PHP 8.2 with Laravel 9.39.0 or Earlier

**Problem:** Error: `nette/schema v1.2.2 requires php >=7.1 <8.2 -> your php version (8.2.x) does not satisfy that requirement`

Or: `found musoftware/device-secret-laravel[dev-main, v1.0.0, ..., v1.3.3] but it does not match the constraint` (version 1.3.4 not found)

**Root Cause:** 
- Laravel 9.39.0's dependency chain (league/commonmark → league/config → nette/schema) includes old versions that don't support PHP 8.2.
- Packagist may not have indexed the new version yet (can take a few minutes after git push).

**Solution:** 

**Option 1: Wait for Packagist to update (Recommended)**
Wait 1-2 minutes after the version is pushed, then install with dependency updates:

```bash
composer require musoftware/device-secret-laravel:1.3.4 --update-with-dependencies
```

**Option 2: Install from GitHub directly (Immediate)**
If Packagist hasn't updated yet, install directly from GitHub:

```bash
composer require musoftware/device-secret-laravel:dev-main --update-with-dependencies
```

Then later update to the stable version:

```bash
composer require musoftware/device-secret-laravel:^1.3.4 --update-with-dependencies
```

**Option 3: Manual dependency update first**
Update the dependency chain before installing the package:

```bash
composer update league/commonmark league/config nette/schema --with-all-dependencies
composer require musoftware/device-secret-laravel:1.3.4 --update-with-dependencies
```

**Note:** The `--update-with-dependencies` flag is required to allow Composer to resolve compatible versions in the dependency chain.

## Getting Detailed Error Information

For more details about the conflict, run:

```bash
composer require musoftware/device-secret-laravel:^1.1 -vvv
```

The `-vvv` flag provides verbose output showing exactly which packages are conflicting.

