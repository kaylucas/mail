# AI Provider Configuration Fix

## Problem Summary
The email rule evaluation was failing with a 401 authentication error because the code was hardcoded to use the Anthropic provider, but the user only had an OpenAI API key configured.

## Root Cause
1. **Hardcoded Provider**: `EmailRuleEvaluator.php` was hardcoded to use `'anthropic'` provider
2. **Missing API Key**: No `ANTHROPIC_API_KEY` was set in the environment
3. **Configuration Ignored**: The `PRISM_PROVIDER` environment variable was being ignored

## Solution Applied

### 1. Configuration Changes (`config/prism.php`)

Made all provider settings configurable via environment variables:

```php
'providers' => [
    'anthropic' => [
        'model' => env('ANTHROPIC_MODEL', 'claude-3-5-sonnet-latest'),
        // ... other settings now use env()
    ],
    'openai' => [
        'model' => env('OPENAI_MODEL', 'gpt-4o'),
        // ... other settings now use env()
    ],
    'gemini' => [
        'model' => env('GEMINI_MODEL', 'gemini-pro'),
        // ... other settings now use env()
    ],
],
```

### 2. Service Changes (`app/Services/EmailRuleEvaluator.php`)

Changed from hardcoded provider:
```php
// OLD - Hardcoded
->using('anthropic', config('prism.providers.anthropic.model'))
```

To dynamic provider selection:
```php
// NEW - Dynamic
->using(config('prism.default'), config('prism.providers.' . config('prism.default') . '.model'))
```

Applied to both methods:
- `evaluateWithAI()` - Line ~135
- `evaluateWithAIForTest()` - Line ~506

### 3. Environment Variables

Required variables in `.env`:

```bash
# Choose your provider: 'openai', 'anthropic', or 'gemini'
PRISM_PROVIDER=openai

# OpenAI Configuration (if using OpenAI)
OPENAI_API_KEY=your-api-key-here
OPENAI_MODEL=gpt-4o  # Optional, defaults to gpt-4o

# Anthropic Configuration (if using Anthropic)
# ANTHROPIC_API_KEY=your-api-key-here
# ANTHROPIC_MODEL=claude-3-5-sonnet-latest  # Optional

# Gemini Configuration (if using Gemini)
# GEMINI_API_KEY=your-api-key-here
# GEMINI_MODEL=gemini-pro  # Optional
```

## Verification

### Test Configuration
```bash
php artisan tinker --execute="echo 'Provider: ' . config('prism.default') . PHP_EOL . 'Model: ' . config('prism.providers.' . config('prism.default') . '.model');"
```

### Test Email Rule Evaluation
```bash
# Test with a specific email
php artisan tinker
>>> $email = \App\Models\Email::find(146);
>>> $evaluator = app(\App\Services\EmailRuleEvaluator::class);
>>> $result = $evaluator->testRulesForEmail($email);
>>> print_r($result);
```

## Docker Deployment

To apply changes in Docker container:

```bash
# 1. Clear config cache
docker-compose exec app php artisan config:clear

# 2. Clear application cache
docker-compose exec app php artisan cache:clear

# 3. Optimize (rebuild cached config)
docker-compose exec app php artisan optimize

# 4. Verify configuration
docker-compose exec app php artisan tinker --execute="echo config('prism.default');"
```

## Switching Providers

To switch between AI providers:

1. **For OpenAI**:
   ```bash
   PRISM_PROVIDER=openai
   OPENAI_API_KEY=sk-...
   ```

2. **For Anthropic**:
   ```bash
   PRISM_PROVIDER=anthropic
   ANTHROPIC_API_KEY=sk-ant-...
   ```

3. **For Gemini**:
   ```bash
   PRISM_PROVIDER=gemini
   GEMINI_API_KEY=...
   ```

## Troubleshooting

### Error: "x-api-key header is required"
- **Cause**: Missing API key for the selected provider
- **Fix**: Ensure the API key environment variable is set for your chosen provider

### Error: "Model not found"
- **Cause**: Invalid model name for the provider
- **Fix**: Check the model name in your environment variables matches the provider's available models

### Provider not switching
- **Cause**: Config cache not cleared
- **Fix**: Run `php artisan config:clear` after changing `.env`

## Benefits of This Fix

1. **Provider Flexibility**: Can switch between OpenAI, Anthropic, and Gemini without code changes
2. **Environment-Based Configuration**: All settings controlled via `.env`
3. **No Hardcoding**: Removes all hardcoded provider references
4. **Consistent Behavior**: Both production and test evaluation use the same provider
5. **Easy Testing**: Can test different providers by changing one environment variable

## Files Modified

- `/config/prism.php` - Made models and settings configurable
- `/app/Services/EmailRuleEvaluator.php` - Dynamic provider selection
- `.env.ai-example` - Template for AI configuration

## Rollback Instructions

If needed, restore from backups:
```bash
cp /Users/kaylucas/Projects/mail/config/prism.php.backup /Users/kaylucas/Projects/mail/config/prism.php
cp /Users/kaylucas/Projects/mail/app/Services/EmailRuleEvaluator.php.backup /Users/kaylucas/Projects/mail/app/Services/EmailRuleEvaluator.php
php artisan config:clear
```
