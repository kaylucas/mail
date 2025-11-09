# Email Rule Database Error Fix

## Issue
When trying to create email rules via the API, users were receiving a generic database error:
```json
{
    "message": "Failed to create email rule",
    "error": "A database error occurred. Please try again later."
}
```

## Root Cause
The `user_id` field was missing from the `$fillable` array in the `EmailRule` model. This caused Laravel's mass assignment protection to block the `user_id` field from being set when creating new email rules, resulting in a database constraint violation (user_id cannot be null).

The actual SQL error was:
```
SQLSTATE[HY000]: General error: 1364 Field 'user_id' doesn't have a default value
```

## Solution
Added `user_id` to the `$fillable` array in `app/Models/EmailRule.php`:

```php
protected $fillable = [
    'user_id',  // <-- Added this line
    'name',
    'description',
    'prompt',
    'simple_conditions',
    'is_active',
    'priority',
    'ai_provider',
    'ai_model',
];
```

## Files Modified
- `/app/Models/EmailRule.php` - Added `user_id` to fillable array

## Verification
After the fix, email rules can be successfully created via the API:

```bash
curl -X POST 'http://mail.loc/api/email-rules' \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{
    "name": "Human needs response",
    "description": "",
    "simple_conditions": {},
    "prompt": "Check if this email is sent by a human",
    "is_active": true,
    "priority": 0,
    "ai_provider": "default",
    "ai_model": "",
    "actions": [
      {
        "action_type": "add_label",
        "action_config": {
          "label_name": "needs-response"
        }
      }
    ]
  }'
```

Response:
```json
{
    "message": "Email rule created successfully",
    "data": {
        "id": 3,
        "user_id": 66,
        "name": "Human needs response",
        ...
    }
}
```

## Prevention
To prevent similar issues in the future:
1. Always include foreign key fields in the `$fillable` array when they need to be set during creation
2. Consider using `$guarded = []` instead of `$fillable` for internal models where mass assignment protection is less critical
3. Ensure proper error logging is enabled in development to catch actual database errors instead of sanitized messages
