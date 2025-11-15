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

## Getting Detailed Error Information

For more details about the conflict, run:

```bash
composer require musoftware/device-secret-laravel:^1.1 -vvv
```

The `-vvv` flag provides verbose output showing exactly which packages are conflicting.

