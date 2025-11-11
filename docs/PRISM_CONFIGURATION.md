# Prism AI Configuration

This document describes the configuration and setup for the Prism AI integration used in email rule evaluation.

## Fixed Issues (2025-11-10)

### Error: `Argument #2 ($model) must be of type string, null given`

**Problem**: The code was trying to access a non-existent configuration key:
```php
->using('anthropic', config('prism.providers.anthropic.default'))  // Wrong
```

**Solution**: Updated to use the correct configuration path:
```php
->using('anthropic', config('prism.providers.anthropic.model'))  // Correct
```

### Files Updated

- **`app/Services/EmailRuleEvaluator.php`**:
  - Line 135: Fixed `evaluateWithAI()` method
  - Line 506: Fixed `evaluateWithAIForTest()` method

## Configuration

### 1. Environment Variables

Add your Anthropic API key to `.env`:
```bash
ANTHROPIC_API_KEY=your_actual_api_key_here
```

To obtain an API key:
1. Go to https://console.anthropic.com/
2. Sign up or log in
3. Navigate to API Keys section
4. Create a new API key
5. Copy and add to your `.env` file

### 2. Configuration File

The Prism configuration is located at `config/prism.php`:

```php
'anthropic' => [
    'api_key' => env('ANTHROPIC_API_KEY'),
    'model' => 'claude-3-5-sonnet-latest',  // This is what we reference
    'temperature' => 0.7,
    'max_tokens' => 2000,
    // ... other settings
]
```

## Docker Development Setup

### Problem: Code Changes Not Reflecting

By default, Docker builds the application code into the image, requiring rebuilds for changes to take effect.

### Solution 1: Use Docker Compose Override (Recommended for Development)

A `docker-compose.override.yml` file has been created that mounts your local code into the containers:

```yaml
services:
  app:
    volumes:
      - .:/var/www/html  # Mount local code for live updates
    environment:
      ANTHROPIC_API_KEY: ${ANTHROPIC_API_KEY}
```

**Benefits**:
- Code changes reflect immediately without rebuilding
- Faster development cycle
- No need to rebuild containers for code changes

**Usage**:
```bash
# Docker Compose automatically uses override file if present
docker-compose up -d

# Your code changes will now reflect immediately
```

### Solution 2: Rebuild Containers (For Production-like Testing)

Use the provided rebuild script:
```bash
./bin/docker-rebuild.sh
```

Or manually:
```bash
docker-compose down
docker-compose build --no-cache app queue
docker-compose up -d
```

## Usage in Code

### Correct Usage Pattern

```php
use EchoLabs\Prism\Facades\Prism;

// For structured output (JSON)
$response = Prism::structured()
    ->using('anthropic', config('prism.providers.anthropic.model'))
    ->withSystemPrompt('Your system prompt here')
    ->withPrompt($userPrompt)
    ->withSchema($jsonSchema)
    ->generate();

$result = $response->structured;  // Returns decoded JSON

// For text output
$response = Prism::text()
    ->using('anthropic', config('prism.providers.anthropic.model'))
    ->withSystemPrompt('Your system prompt here')
    ->withPrompt($userPrompt)
    ->generate();

$result = $response->text;  // Returns string
```

### Common Mistakes to Avoid

1. **Wrong config path**: Don't use `config('prism.providers.anthropic.default')`
2. **Missing API key**: Always ensure `ANTHROPIC_API_KEY` is set in `.env`
3. **Wrong model format**: Use the model string directly, not a config path

## Testing

### Test the Configuration

```bash
# Inside the container
docker-compose exec app php artisan tinker

# Test Prism is configured correctly
>>> config('prism.providers.anthropic.model')
# Should output: "claude-3-5-sonnet-latest"

>>> config('prism.providers.anthropic.api_key')
# Should output your API key (or at least not null)
```

### Test AI Evaluation

```bash
# Run the email rule evaluator in test mode
docker-compose exec app php artisan email:test-rules
```

## Troubleshooting

### Error: "Argument #2 ($model) must be of type string, null given"
- **Cause**: Configuration path is wrong
- **Fix**: Ensure using `config('prism.providers.anthropic.model')`

### Error: "Invalid API key"
- **Cause**: Missing or incorrect ANTHROPIC_API_KEY
- **Fix**: Add valid API key to `.env`

### Changes not reflecting in Docker
- **Cause**: Code is built into image
- **Fix**: Use docker-compose.override.yml or rebuild containers

### Container fails to start
- **Cause**: Missing environment variables
- **Fix**: Ensure all required variables are in `.env`

## Environment Variables Summary

Required for Prism to work:
```bash
# In .env file
ANTHROPIC_API_KEY=sk-ant-api03-xxxxx  # Your actual API key
```

Optional Prism settings (defaults in config/prism.php):
```bash
# These can be added to override defaults
PRISM_PROVIDER=anthropic  # Default provider
```

## Related Files

- `/app/Services/EmailRuleEvaluator.php` - Uses Prism for AI email classification
- `/config/prism.php` - Prism configuration file
- `/docker-compose.yml` - Main Docker configuration
- `/docker-compose.override.yml` - Development overrides for live code updates
- `/bin/docker-rebuild.sh` - Helper script for rebuilding containers
