# AI-Powered Email Rules System

Comprehensive documentation for the intelligent email automation and classification feature.

## Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Configuration](#configuration)
4. [How It Works](#how-it-works)
5. [Creating Rules](#creating-rules)
6. [Action Types](#action-types)
7. [Simple Conditions (Pre-filters)](#simple-conditions-pre-filters)
8. [AI Evaluation](#ai-evaluation)
9. [Database Schema](#database-schema)
10. [API Reference](#api-reference)
11. [Queue Configuration](#queue-configuration)
12. [Troubleshooting](#troubleshooting)

## Overview

The AI-powered email rules system automatically processes incoming emails using artificial intelligence to classify, label, forward, and set reminders based on custom criteria. This feature combines traditional rule-based filtering with modern AI evaluation for intelligent email management.

### Key Features

- **Custom AI Prompts**: Define rules using natural language (e.g., "Is this a customer support request?")
- **Simple Pre-Filters**: Use sender, subject, or recipient conditions to skip AI evaluation for basic rules
- **Batched AI Processing**: Multiple rules evaluated in a single API call for cost optimization
- **Multiple Actions**: Automatically label emails, forward to addresses, or create reminders
- **Multi-Provider Support**: Works with Anthropic Claude, OpenAI GPT, and Google Gemini
- **Automatic Classification**: Every email automatically evaluated for automated/human detection and response requirements
- **Priority-Based Execution**: Rules execute in priority order (lower number = higher priority)
- **Event-Driven**: Rules trigger automatically when emails are created or updated

## Architecture

### System Flow

```
Email Created/Updated Event
         ↓
ProcessEmailRules Listener
         ↓
ProcessEmailRulesJob (queued)
         ↓
EmailRuleEvaluator Service
         ↓
    ┌────┴────┐
    ↓         ↓
Simple        AI
Conditions    Evaluation
    ↓         ↓
    └────┬────┘
         ↓
Execute Actions
    ├── Add Label
    ├── Forward Email
    └── Create Reminder
         ↓
EmailRuleExecution (logged)
```

### Components

**Models:**
- `EmailRule` - Stores rule configuration and AI prompts
- `EmailRuleAction` - Defines actions to execute when rule matches
- `EmailRuleExecution` - Logs rule execution history
- `EmailLabel` - Labels applied to emails
- `EmailReminder` - Reminders created for emails

**Services:**
- `EmailRuleEvaluator` - Core rule evaluation and orchestration logic
- `LabelActionHandler` - Creates/assigns labels to emails
- `ForwardActionHandler` - Forwards emails to specified addresses
- `ReminderActionHandler` - Creates reminders with specified delays

**Jobs:**
- `ProcessEmailRulesJob` - Queued job that processes rules for a single email

**Events:**
- `EmailCreated` - Fired when new email is synced
- `EmailUpdated` - Fired when email is modified

**Listeners:**
- `ProcessEmailRules` - Listens to email events and dispatches rule processing jobs

## Configuration

### Environment Variables

Add these to your `.env` file:

```env
# Email Rules Feature
EMAIL_RULES_ENABLED=true
EMAIL_RULES_QUEUE=email-rules
EMAIL_REMINDER_CHECK_TIME=09:00

# AI Provider Configuration
PRISM_PROVIDER=anthropic
ANTHROPIC_API_KEY=your_anthropic_key_here

# Alternative providers:
# PRISM_PROVIDER=openai
# OPENAI_API_KEY=your_openai_key_here
#
# PRISM_PROVIDER=gemini
# GEMINI_API_KEY=your_gemini_key_here
```

### Configuration File

The system is configured via `config/email_rules.php`:

```php
return [
    // Feature toggle
    'enabled' => env('EMAIL_RULES_ENABLED', true),

    // Queue name for rule processing
    'queue' => env('EMAIL_RULES_QUEUE', 'email-rules'),

    // Daily reminder check time (24-hour format)
    'reminder_check_time' => env('EMAIL_REMINDER_CHECK_TIME', '09:00'),

    // Maximum rules per user
    'max_rules_per_user' => 50,

    // Maximum actions per rule
    'max_actions_per_rule' => 10,

    // Rule evaluation timeout (seconds)
    'evaluation_timeout' => 30,

    // Default AI provider
    'default_provider' => env('PRISM_PROVIDER', 'anthropic'),

    // Fields that don't trigger rule re-processing
    'skip_update_fields' => [
        'is_read',
        'flag_status',
        'updated_at',
    ],
];
```

## How It Works

### Email Processing Flow

1. **Email Arrives**: When an email is synced via Microsoft Graph, an `EmailCreated` event is fired
2. **Event Listener**: The `ProcessEmailRules` listener catches the event
3. **Queue Dispatch**: A `ProcessEmailRulesJob` is dispatched to the `email-rules` queue
4. **Rule Loading**: The job loads all active rules for the user (ordered by priority)
5. **Pre-Filtering**: Simple conditions are evaluated first (no AI cost)
6. **AI Batch Evaluation**: Remaining rules are evaluated together in one AI call
7. **Action Execution**: Matched rules trigger their configured actions
8. **Logging**: Execution results are stored in `email_rule_executions` table

### Cost Optimization

The system minimizes AI API costs through several strategies:

**1. Simple Conditions Pre-Filter**
Rules with simple conditions (sender, subject, recipient filters) are evaluated without AI:

```json
{
  "simple_conditions": {
    "from": "@customers.com",
    "subject": "Support Request"
  }
}
```

If simple conditions match, the rule executes immediately without AI evaluation.

**2. Batched AI Evaluation**
Multiple rules are evaluated in a single AI API call:

```
Prompt: "Analyze this email and determine which rules match:
  Rule 1: Is this a customer complaint?
  Rule 2: Is this a payment inquiry?
  Rule 3: Does this require urgent attention?"

Response: {
  "rule_1": true,
  "rule_2": false,
  "rule_3": true,
  "is_automated": false,
  "needs_response": true
}
```

This reduces API calls from N (number of rules) to 1 per email.

**3. Update Field Skip List**
Minor email updates (read status, flags) don't trigger rule re-processing:

```php
'skip_update_fields' => [
    'is_read',
    'flag_status',
    'updated_at',
]
```

## Creating Rules

### Via API

**Create Rule Example:**

```bash
curl -X POST http://mail.loc/api/email-rules \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Support Requests",
    "description": "Automatically categorize customer support emails",
    "prompt": "Is this email requesting technical support or reporting a bug?",
    "simple_conditions": {
      "from": "@customers.com"
    },
    "is_active": true,
    "priority": 10,
    "ai_provider": "anthropic",
    "ai_model": "claude-3-5-sonnet-20241022",
    "actions": [
      {
        "action_type": "add_label",
        "action_config": {
          "label_name": "Support"
        }
      },
      {
        "action_type": "forward",
        "action_config": {
          "email_addresses": ["support@company.com"],
          "include_note": true
        }
      },
      {
        "action_type": "add_reminder",
        "action_config": {
          "days_after": 3,
          "message": "Follow up on support request"
        }
      }
    ]
  }'
```

### Via Frontend

1. Navigate to **Settings > Email Rules**
2. Click **Create Rule** button
3. Fill in the form:
   - **Name**: Short descriptive name
   - **Description**: Optional longer explanation
   - **AI Prompt**: Natural language criteria (required)
   - **Simple Conditions**: Optional pre-filters
     - From: Filter by sender email/name
     - To: Filter by recipient email
     - Subject: Filter by subject content
   - **Priority**: Lower numbers execute first (default: 100)
   - **AI Provider**: anthropic, openai, or gemini (optional, uses default)
   - **AI Model**: Specific model override (optional)
4. Add one or more actions
5. Click **Save Rule**

### Rule Configuration Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Rule name (max 255 chars) |
| `description` | string | No | Longer explanation (max 1000 chars) |
| `prompt` | string | Yes | AI evaluation criteria in natural language |
| `simple_conditions` | object | No | Pre-filter conditions (from/to/subject) |
| `is_active` | boolean | No | Whether rule is active (default: true) |
| `priority` | integer | No | Execution priority 0-1000 (default: 100) |
| `ai_provider` | string | No | anthropic, openai, or gemini (uses default if omitted) |
| `ai_model` | string | No | Specific model to use (provider default if omitted) |
| `actions` | array | Yes | Array of action objects (min 1, max 10) |

## Action Types

### 1. Add Label

Applies a label to the email. Labels are created automatically if they don't exist.

**Configuration:**

```json
{
  "action_type": "add_label",
  "action_config": {
    "label_name": "Important"
  }
}
```

**Validation:**
- `label_name` (required, string, max 100 chars)

**Behavior:**
- Creates label with random color if it doesn't exist
- Associates email with label (prevents duplicates)
- Labels visible in email list UI

### 2. Forward Email

Forwards the email to one or more email addresses.

**Configuration:**

```json
{
  "action_type": "forward",
  "action_config": {
    "email_addresses": [
      "support@company.com",
      "manager@company.com"
    ],
    "include_note": true
  }
}
```

**Validation:**
- `email_addresses` (required, array, min 1, each must be valid email)
- `include_note` (optional, boolean, default: false)

**Behavior:**
- Sends email via Microsoft Graph API forward endpoint
- Optionally includes note: "Forwarded by automated rule: [Rule Name]"
- Logs success/failure in execution record
- Does not create local email record (handled by Microsoft)

### 3. Add Reminder

Creates a reminder to follow up on the email after specified days.

**Configuration:**

```json
{
  "action_type": "add_reminder",
  "action_config": {
    "days_after": 3,
    "message": "Follow up on this request"
  }
}
```

**Validation:**
- `days_after` (required, integer, min 1)
- `message` (required, string, max 500 chars)

**Behavior:**
- Creates reminder with status "pending"
- Sets `remind_at` to current date + `days_after`
- Reminder appears in frontend reminders list
- User can dismiss, complete, or snooze reminders

## Simple Conditions (Pre-filters)

Simple conditions allow rules to match emails based on basic criteria without AI evaluation. This is faster and free.

### Configuration Format

```json
{
  "simple_conditions": {
    "from": "searchterm",
    "to": "searchterm",
    "subject": "searchterm"
  }
}
```

### Matching Logic

All specified conditions must match (AND logic):

**From Condition:**
- Searches both `from_email` and `from_name` fields
- Case-insensitive substring match
- Example: `"from": "@company.com"` matches any email from company.com domain

**To Condition:**
- Searches all recipients in `to_recipients` array
- Case-insensitive substring match
- Example: `"to": "support@"` matches emails sent to any support@ address

**Subject Condition:**
- Searches `subject` field
- Case-insensitive substring match
- Example: `"subject": "[URGENT]"` matches emails with [URGENT] in subject

### When to Use Simple Conditions

Use simple conditions when you can filter emails with basic text matching:

**Good Use Cases:**
- Emails from specific domain: `"from": "@vendor.com"`
- Emails with keyword in subject: `"subject": "invoice"`
- Emails to specific address: `"to": "billing@company.com"`

**Bad Use Cases:**
- Sentiment analysis (requires AI)
- Intent detection (requires AI)
- Complex conditional logic (requires AI)
- Content understanding (requires AI)

### Combined Strategy

Best practice: Use simple conditions + AI prompt for efficiency:

```json
{
  "simple_conditions": {
    "from": "@customers.com"
  },
  "prompt": "Is this email requesting a refund?"
}
```

This rule:
1. First checks if email is from customers.com (fast, free)
2. If yes, uses AI to determine if it's a refund request (slow, costs money)
3. If simple condition fails, skips AI evaluation entirely

## AI Evaluation

### How AI Evaluation Works

When simple conditions don't match (or aren't defined), the system uses AI to evaluate rules.

### Batched Prompt Structure

The evaluator builds a single prompt for all AI-required rules:

```
Analyze the following email and determine which rules match:

EMAIL DETAILS:
Subject: Need help with login issues
From: John Doe <john@example.com>
To: support@company.com
Received: 2025-11-07 10:30:00
Body Preview: Hi, I've been trying to log in to my account...

RULES TO EVALUATE:

Rule 1 (ID: 123):
Name: Technical Support
Criteria: Is this email requesting technical support?

Rule 2 (ID: 124):
Name: Account Issues
Criteria: Does this email mention account or authentication problems?

Rule 3 (ID: 125):
Name: Urgent Issues
Criteria: Does this email indicate an urgent problem requiring immediate attention?

For each rule, respond with true if the email matches the criteria, false otherwise.
```

### AI Response Schema

The AI responds with structured JSON:

```json
{
  "is_automated": false,
  "needs_response": true,
  "rule_123": true,
  "rule_124": true,
  "rule_125": false
}
```

**Standard Fields:**
- `is_automated` (boolean): Whether email appears to be automated/newsletter
- `needs_response` (boolean): Whether email requires human response

**Rule Fields:**
- `rule_{id}` (boolean): Whether the specific rule matches

### AI Provider Configuration

**Anthropic (Default):**
```env
PRISM_PROVIDER=anthropic
ANTHROPIC_API_KEY=sk-ant-api03-...
```

Uses Claude 3.5 Sonnet by default (configurable in `config/prism.php`)

**OpenAI:**
```env
PRISM_PROVIDER=openai
OPENAI_API_KEY=sk-...
```

Uses GPT-4 by default (configurable in `config/prism.php`)

**Google Gemini:**
```env
PRISM_PROVIDER=gemini
GEMINI_API_KEY=...
```

Uses Gemini Pro by default (configurable in `config/prism.php`)

### Per-Rule Provider Override

Rules can specify a custom provider/model:

```json
{
  "ai_provider": "openai",
  "ai_model": "gpt-4-turbo-preview"
}
```

Note: When batching rules, the first rule's provider is used for the batch. If you need different providers, use different priorities to separate batches.

### Writing Effective AI Prompts

**Good Prompts:**
- Clear, specific criteria: "Is this email requesting a product refund?"
- Binary questions: "Does this email contain complaints about service quality?"
- Context-aware: "Is this a follow-up to a previous conversation?"

**Bad Prompts:**
- Vague criteria: "Is this important?" (subjective)
- Multiple questions: "Is this urgent or complex?" (ambiguous AND/OR)
- Action instructions: "Reply with a thank you" (not a matching criterion)

**Best Practices:**
- Focus on detection, not action
- Use specific domain language
- Test with sample emails
- Combine with simple conditions when possible

## Database Schema

### email_rules

Stores rule configurations:

```sql
CREATE TABLE email_rules (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    prompt TEXT NOT NULL,
    simple_conditions JSON NULL COMMENT 'Pre-filter conditions',
    is_active BOOLEAN DEFAULT TRUE,
    priority INT DEFAULT 0 COMMENT 'Lower = higher priority',
    ai_provider VARCHAR(255) NULL COMMENT 'anthropic, openai, or gemini',
    ai_model VARCHAR(255) NULL COMMENT 'Specific model to use',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_active (is_active),
    INDEX idx_user_priority_active (user_id, priority, is_active)
);
```

### email_rule_actions

Stores actions for each rule:

```sql
CREATE TABLE email_rule_actions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    email_rule_id BIGINT UNSIGNED NOT NULL,
    action_type VARCHAR(50) NOT NULL COMMENT 'add_label, forward, add_reminder',
    action_config JSON NOT NULL COMMENT 'Action-specific configuration',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (email_rule_id) REFERENCES email_rules(id) ON DELETE CASCADE,
    INDEX idx_email_rule_id (email_rule_id)
);
```

### email_rule_executions

Logs rule execution history:

```sql
CREATE TABLE email_rule_executions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    email_rule_id BIGINT UNSIGNED NOT NULL,
    email_id BIGINT UNSIGNED NOT NULL,
    matched BOOLEAN DEFAULT FALSE,
    actions_executed INT DEFAULT 0,
    actions_failed INT DEFAULT 0,
    execution_details JSON NULL COMMENT 'Detailed results',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (email_rule_id) REFERENCES email_rules(id) ON DELETE CASCADE,
    FOREIGN KEY (email_id) REFERENCES emails(id) ON DELETE CASCADE,
    INDEX idx_email_rule_id (email_rule_id),
    INDEX idx_email_id (email_id),
    INDEX idx_created_at (created_at)
);
```

### email_labels

Stores label definitions:

```sql
CREATE TABLE email_labels (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(7) DEFAULT '#3B82F6' COMMENT 'Hex color code',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_label (user_id, name),
    INDEX idx_user_id (user_id)
);
```

### email_label (pivot table)

Associates emails with labels:

```sql
CREATE TABLE email_label (
    email_id BIGINT UNSIGNED NOT NULL,
    email_label_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,

    PRIMARY KEY (email_id, email_label_id),
    FOREIGN KEY (email_id) REFERENCES emails(id) ON DELETE CASCADE,
    FOREIGN KEY (email_label_id) REFERENCES email_labels(id) ON DELETE CASCADE
);
```

### email_reminders

Stores email reminders:

```sql
CREATE TABLE email_reminders (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    email_id BIGINT UNSIGNED NOT NULL,
    message VARCHAR(500) NOT NULL,
    remind_at TIMESTAMP NOT NULL,
    status VARCHAR(20) DEFAULT 'pending' COMMENT 'pending, dismissed, completed',
    dismissed_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (email_id) REFERENCES emails(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_email_id (email_id),
    INDEX idx_remind_at (remind_at),
    INDEX idx_status (status)
);
```

## API Reference

All endpoints require authentication via `auth:sanctum` middleware.

### Email Rules Endpoints

#### List All Rules

```
GET /api/email-rules
```

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "name": "Support Requests",
      "description": "Categorize support emails",
      "prompt": "Is this a support request?",
      "simple_conditions": {
        "from": "@customers.com"
      },
      "is_active": true,
      "priority": 10,
      "ai_provider": "anthropic",
      "ai_model": "claude-3-5-sonnet-20241022",
      "created_at": "2025-11-07T10:00:00Z",
      "updated_at": "2025-11-07T10:00:00Z",
      "actions": [
        {
          "id": 1,
          "email_rule_id": 1,
          "action_type": "add_label",
          "action_config": {
            "label_name": "Support"
          }
        }
      ]
    }
  ]
}
```

#### Create Rule

```
POST /api/email-rules
```

**Request Body:** (see [Creating Rules](#creating-rules) section)

**Response:** 201 Created
```json
{
  "message": "Email rule created successfully",
  "data": { /* rule object */ }
}
```

#### Get Single Rule

```
GET /api/email-rules/{id}
```

**Response:** 200 OK
```json
{
  "data": { /* rule object with actions */ }
}
```

#### Update Rule

```
PUT /api/email-rules/{id}
```

**Request Body:** Same as create (all fields required)

**Response:** 200 OK
```json
{
  "message": "Email rule updated successfully",
  "data": { /* updated rule object */ }
}
```

#### Delete Rule

```
DELETE /api/email-rules/{id}
```

**Response:** 200 OK
```json
{
  "message": "Email rule deleted successfully"
}
```

#### Toggle Rule Active Status

```
PATCH /api/email-rules/{id}/toggle
```

**Response:** 200 OK
```json
{
  "message": "Email rule status updated successfully",
  "data": {
    "id": 1,
    "is_active": false
  }
}
```

### Email Reminders Endpoints

#### List All Reminders

```
GET /api/email-reminders?status=pending
```

**Query Parameters:**
- `status` (optional): pending, dismissed, or completed

**Response:** 200 OK
```json
{
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "email_id": 123,
      "message": "Follow up on support request",
      "remind_at": "2025-11-10T09:00:00Z",
      "status": "pending",
      "dismissed_at": null,
      "completed_at": null,
      "created_at": "2025-11-07T10:00:00Z",
      "updated_at": "2025-11-07T10:00:00Z",
      "email": {
        "id": 123,
        "subject": "Login issues",
        "from_name": "John Doe",
        "from_email": "john@example.com",
        "folder": {
          "name": "Inbox"
        }
      }
    }
  ]
}
```

#### Dismiss Reminder

```
PATCH /api/email-reminders/{id}/dismiss
```

**Response:** 200 OK
```json
{
  "message": "Reminder dismissed successfully",
  "data": { /* updated reminder */ }
}
```

#### Complete Reminder

```
PATCH /api/email-reminders/{id}/complete
```

**Response:** 200 OK
```json
{
  "message": "Reminder completed successfully",
  "data": { /* updated reminder */ }
}
```

#### Snooze Reminder

```
PATCH /api/email-reminders/{id}/snooze
```

**Request Body:**
```json
{
  "days": 3
}
```

**Validation:**
- `days` (required, integer, 1-30)

**Response:** 200 OK
```json
{
  "message": "Reminder snoozed successfully",
  "data": { /* updated reminder with new remind_at */ }
}
```

## Queue Configuration

### Running the Queue Worker

The email rules system uses a dedicated queue for processing:

**Option 1: Process email-rules queue only**
```bash
php artisan queue:work --queue=email-rules
```

**Option 2: Process multiple queues with priority**
```bash
php artisan queue:work --queue=email-rules,default
```

**Option 3: Use Horizon (recommended for production)**
```bash
composer require laravel/horizon
php artisan horizon:install
php artisan horizon
```

Configure Horizon in `config/horizon.php`:
```php
'environments' => [
    'production' => [
        'email-rules' => [
            'connection' => 'redis',
            'queue' => ['email-rules'],
            'balance' => 'auto',
            'processes' => 3,
            'tries' => 3,
            'timeout' => 180,
        ],
    ],
],
```

### Job Configuration

**ProcessEmailRulesJob:**
- Queue: `email-rules` (configurable)
- Tries: 3 attempts
- Timeout: 180 seconds (3 minutes)
- Max Exceptions: 3

### Monitoring Queue Jobs

**Check queue status:**
```bash
php artisan queue:failed    # List failed jobs
php artisan queue:retry all # Retry all failed jobs
php artisan queue:flush     # Clear all failed jobs
```

**View logs:**
```bash
php artisan pail                                    # Tail logs in real-time
grep "ProcessEmailRulesJob" storage/logs/laravel.log  # Search job logs
```

### Queue Best Practices

1. **Always run queue worker in production**
   - Use Supervisor or systemd to keep worker running
   - Configure automatic restart on failure

2. **Monitor failed jobs**
   - Set up alerts for failed job count
   - Review failures regularly
   - Retry transient failures

3. **Scale workers based on volume**
   - High email volume: Increase worker processes
   - Multiple servers: Use Redis for queue backend

## Troubleshooting

### Rules Not Executing

**Symptom:** Rules created but emails not being processed

**Checks:**
1. Verify queue worker is running:
   ```bash
   ps aux | grep "queue:work"
   ```

2. Check if feature is enabled:
   ```bash
   php artisan tinker
   >>> config('email_rules.enabled')
   => true
   ```

3. Check for failed jobs:
   ```bash
   php artisan queue:failed
   ```

4. Check rule is active:
   ```sql
   SELECT id, name, is_active FROM email_rules WHERE user_id = ?;
   ```

5. Review logs:
   ```bash
   grep "ProcessEmailRulesJob" storage/logs/laravel.log | tail -20
   ```

### AI Evaluation Failing

**Symptom:** Rules with AI prompts not matching correctly

**Checks:**
1. Verify API key is set:
   ```bash
   php artisan tinker
   >>> config('prism.providers.anthropic.api_key')
   ```

2. Check provider configuration:
   ```bash
   php artisan tinker
   >>> config('email_rules.default_provider')
   >>> config('prism.providers.anthropic.model')
   ```

3. Review AI evaluation logs:
   ```bash
   grep "AI evaluation" storage/logs/laravel.log
   ```

4. Test API connection:
   ```bash
   curl -H "x-api-key: $ANTHROPIC_API_KEY" \
        -H "anthropic-version: 2023-06-01" \
        https://api.anthropic.com/v1/messages
   ```

**Common Errors:**
- `401 Unauthorized`: Invalid API key
- `429 Too Many Requests`: Rate limit exceeded (upgrade plan or reduce email volume)
- `500 Internal Server Error`: Check API status at provider's status page

### Actions Not Executing

**Symptom:** Rules match but actions don't execute

**Checks:**
1. Check execution logs:
   ```sql
   SELECT * FROM email_rule_executions
   WHERE email_rule_id = ?
   ORDER BY created_at DESC LIMIT 10;
   ```

2. Review action execution in logs:
   ```bash
   grep "Executing rule actions" storage/logs/laravel.log
   ```

3. Check action configuration:
   ```sql
   SELECT action_type, action_config
   FROM email_rule_actions
   WHERE email_rule_id = ?;
   ```

**Common Issues:**
- **Label action fails**: Check label name length (max 100 chars)
- **Forward action fails**: Verify Microsoft Graph API permissions, check recipient email format
- **Reminder action fails**: Check days_after is positive integer

### Performance Issues

**Symptom:** Slow email processing, high queue backlog

**Solutions:**

1. **Increase queue workers:**
   ```bash
   # Run multiple workers
   php artisan queue:work --queue=email-rules &
   php artisan queue:work --queue=email-rules &
   php artisan queue:work --queue=email-rules &
   ```

2. **Optimize simple conditions:**
   - Add pre-filters to rules to reduce AI calls
   - Use specific simple conditions to skip obvious non-matches

3. **Reduce batch size:**
   - Split rules into separate priority groups
   - Each priority creates a separate AI batch

4. **Monitor AI API latency:**
   ```bash
   grep "AI evaluation response received" storage/logs/laravel.log | tail -20
   ```

5. **Check database indexes:**
   ```sql
   EXPLAIN SELECT * FROM email_rules
   WHERE user_id = ? AND is_active = 1
   ORDER BY priority ASC;
   ```

### Debugging Tools

**Check rule matches for specific email:**
```bash
php artisan tinker
>>> $email = App\Models\Email::find(123);
>>> $user = $email->user;
>>> $rules = $user->activeEmailRules;
>>> foreach ($rules as $rule) {
...   echo "Rule: {$rule->name}\n";
...   echo "Simple match: " . ($rule->matchesSimpleConditions($email) ? 'YES' : 'NO') . "\n";
... }
```

**Manually trigger rule processing:**
```bash
php artisan tinker
>>> App\Jobs\ProcessEmailRulesJob::dispatch(emailId: 123, userId: 1);
```

**View execution history:**
```sql
SELECT
    r.name AS rule_name,
    e.subject AS email_subject,
    ex.matched,
    ex.actions_executed,
    ex.actions_failed,
    ex.created_at
FROM email_rule_executions ex
JOIN email_rules r ON ex.email_rule_id = r.id
JOIN emails e ON ex.email_id = e.id
WHERE r.user_id = ?
ORDER BY ex.created_at DESC
LIMIT 20;
```

## Related Documentation

- [CLAUDE.md](../CLAUDE.md) - Full project documentation
- [AUTOMATIC_EMAIL_SYNC.md](./AUTOMATIC_EMAIL_SYNC.md) - Email sync feature
- [SECURITY_FIXES.md](./SECURITY_FIXES.md) - Security implementation details
