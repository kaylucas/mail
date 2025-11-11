# Webhook Type Coercion Bug Fix - Summary

## Problem
The `CreateUserSubscriptionJob` was failing with a type error because Carbon's `addMinutes()` method was receiving a string value `'10080'` instead of an integer.

## Root Cause
Environment variables in PHP are always strings. When `GRAPH_SUBSCRIPTION_EXPIRATION_MINUTES=10080` is retrieved via `env()`, it returns the string `"10080"` not the integer `10080`.

## Solution Implemented

### 1. Service-Level Validation (`app/Services/GraphSubscriptionService.php`)
- Added `validateExpirationMinutes()` private method that:
  - Checks if value is numeric
  - Casts to integer safely
  - Validates range (45-10,080 minutes per Microsoft limits)
  - Provides default value for null/empty
  - Logs warnings for adjustments

### 2. Config-Level Type Casting (`config/services.php` line 61)
```php
// Added (int) cast
'subscription_expiration_minutes' => (int) env('GRAPH_SUBSCRIPTION_EXPIRATION_MINUTES', 10080),
```

### 3. Comprehensive Testing (`tests/Unit/Services/GraphSubscriptionServiceTest.php`)
- Tests string handling from config
- Tests invalid value rejection
- Tests range validation (min/max)
- Tests default value handling

## Files Changed
1. `/Users/kaylucas/Projects/mail/app/Services/GraphSubscriptionService.php` - Added validation method
2. `/Users/kaylucas/Projects/mail/config/services.php` - Added type casting at line 61
3. `/Users/kaylucas/Projects/mail/tests/Unit/Services/GraphSubscriptionServiceTest.php` - New test file
4. `/Users/kaylucas/Projects/mail/docs/WEBHOOK_TYPE_COERCION_FIX.md` - Documentation

## Testing Verification
✅ All 5 tests pass successfully
✅ Type casting verified in tinker
✅ Config and cache cleared

## To Deploy
1. The fix is already applied to the codebase
2. Config cache has been cleared
3. Ready for queue worker restart to pick up changes

## Rollback (if needed)
```bash
cp app/Services/GraphSubscriptionService.php.backup app/Services/GraphSubscriptionService.php
cp config/services.php.backup config/services.php
php artisan config:clear
```

## Prevention for Future
- Always cast numeric config values: `(int)`, `(float)`, `(bool)`
- Validate external input at service layer
- Add unit tests for type handling
- Use type hints where possible
