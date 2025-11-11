# Webhook Subscription Type Coercion Fix

## Issue Description

A critical type coercion bug was discovered in the webhook subscription creation system that caused the `CreateUserSubscriptionJob` to fail permanently after 3 attempts.

### Error Message
```
Carbon\Carbon::rawAddUnit(): Argument #3 ($value) must be of type int|float, string given
```

### Stack Trace
- Error location: `GraphSubscriptionService.php` line 65
- Called from: `CreateUserSubscriptionJob.php` line 89
- Failure: Carbon's `addMinutes()` method received string `'10080'` instead of int

## Root Cause Analysis

The bug occurred due to PHP's `env()` function always returning string values, even for numeric environment variables:

1. **Environment Variable**: `GRAPH_SUBSCRIPTION_EXPIRATION_MINUTES=10080` is stored as string
2. **Config Retrieval**: `config('services.microsoft_graph.subscription_expiration_minutes')` returns string `'10080'`
3. **Carbon Method**: `now()->addMinutes($expirationMinutes)` expects int/float, receives string
4. **Result**: Type error thrown, job fails permanently

## Implemented Solution

The fix implements a multi-layered defense strategy:

### 1. Service-Level Validation (Primary Fix)

Added `validateExpirationMinutes()` private method in `GraphSubscriptionService`:
- **Type checking**: Validates value is numeric
- **Type casting**: Safely converts to integer
- **Range validation**: Ensures value is between 45-10,080 minutes (Microsoft's limits)
- **Default handling**: Returns 10,080 for null/empty values
- **Logging**: Warns about adjustments made

### 2. Config-Level Type Casting (Secondary Defense)

Updated `config/services.php`:
```php
// Before
'subscription_expiration_minutes' => env('GRAPH_SUBSCRIPTION_EXPIRATION_MINUTES', 10080),

// After
'subscription_expiration_minutes' => (int) env('GRAPH_SUBSCRIPTION_EXPIRATION_MINUTES', 10080),
```

### 3. Comprehensive Error Handling

The validation method provides detailed error messages:
- Non-numeric values throw `InvalidArgumentException` with type info
- Values below minimum (45) are adjusted with warning log
- Values above maximum (10,080) are capped with warning log

## Files Modified

1. **`app/Services/GraphSubscriptionService.php`**
   - Added `validateExpirationMinutes()` method
   - Updated `createSubscription()` to use validation
   - Updated `renewSubscription()` to use validation
   - Added comprehensive logging

2. **`config/services.php`**
   - Line 61: Added `(int)` cast to subscription_expiration_minutes

3. **`tests/Unit/Services/GraphSubscriptionServiceTest.php`** (new)
   - Test string handling from config
   - Test non-numeric value rejection
   - Test minimum value adjustment
   - Test maximum value capping
   - Test null/default handling

## Testing the Fix

### Unit Tests
Run the new test suite:
```bash
php artisan test tests/Unit/Services/GraphSubscriptionServiceTest.php
```

### Manual Testing
1. Clear config cache:
   ```bash
   php artisan config:clear
   ```

2. Test with string value in .env:
   ```bash
   GRAPH_SUBSCRIPTION_EXPIRATION_MINUTES="10080"
   ```

3. Trigger subscription creation:
   ```bash
   php artisan tinker
   $user = User::find(1);
   App\Jobs\CreateUserSubscriptionJob::dispatch($user);
   ```

4. Verify job completes successfully:
   ```bash
   php artisan queue:listen
   ```

### Edge Case Testing
Test various edge cases:
```php
// Test with invalid values
$service->createSubscription($user, ['expirationMinutes' => 'invalid']); // Throws exception
$service->createSubscription($user, ['expirationMinutes' => 30]);       // Adjusts to 45
$service->createSubscription($user, ['expirationMinutes' => 20000]);    // Caps at 10,080
$service->createSubscription($user, ['expirationMinutes' => null]);     // Uses default 10,080
```

## Monitoring

Check logs for validation warnings:
```bash
# Check for adjustments
grep "Expiration minutes" storage/logs/laravel.log

# Check for successful subscriptions
grep "Successfully created Microsoft Graph subscription" storage/logs/laravel.log
```

## Prevention Strategy

### Code Review Checklist
- Always validate external input (env vars, config, API params)
- Use type hints in method signatures where possible
- Cast config values at retrieval when types are known
- Add unit tests for type handling edge cases

### Best Practices Applied
1. **Defense in Depth**: Multiple layers of type safety
2. **Fail Fast**: Throw clear exceptions for invalid data
3. **Graceful Degradation**: Adjust values when safe to do so
4. **Observability**: Log all adjustments and validations
5. **Testing**: Comprehensive unit tests for edge cases

## Rollback Plan

If issues occur, restore from backups:
```bash
# Restore service file
cp app/Services/GraphSubscriptionService.php.backup app/Services/GraphSubscriptionService.php

# Restore config
cp config/services.php.backup config/services.php

# Clear cache
php artisan config:clear
```

## Related Issues

This fix prevents similar issues in:
- Token refresh operations (also uses config values)
- Webhook renewal scheduling
- Any future features using numeric config values

## References

- [Carbon addMinutes() documentation](https://carbon.nesbot.com/docs/#api-addsub)
- [Laravel env() helper documentation](https://laravel.com/docs/10.x/helpers#method-env)
- [Microsoft Graph subscription limits](https://docs.microsoft.com/en-us/graph/webhooks#subscription-lifetime)
