# Phase 2.9 Implementation Report — SMS Gateway & System Settings API

**Repository:** `sahabuddin123/innova-web2`  
**Branch:** `feature/phase-1-security-hardening`  
**Framework:** Existing Laravel 8 application  
**API Prefix:** `/api/v1/*`  
**Phase Status:** **PASS** (100% verified with automated tests)  
**Date:** October 25, 2026  

---

## 1. Executive Summary

Phase 2.9 establishes a secure, robust, and audited REST API layer for **System Settings** and **SMS Gateway** communication in the INNOVA Web modernization project. 

The implementation preserves and builds upon the existing database schema (`settings` table), existing SMS provider conventions (Elitbuzz SMS Gateway), and existing business notifications (student fees, class postponements, class schedules, and grant project trainee updates). It introduces zero breaking changes to existing web controllers while adding strict administrative access controls, automated delivery audit trails (`sms_logs`), credential masking, phone number normalization, duplicate send protection, and comprehensive test coverage.

All automated tests ran strictly on isolated in-memory SQLite (`:memory:`) with zero live network calls to the external SMS provider (100% mock transport).

---

## 2. Existing Functionality Reused

1. **`App\Models\Setting`**:
   - Reused the existing key-value schema (`id`, `key`, `value`, `timestamps`).
   - Preserved core static methods `Setting::get($key, $default)` and `Setting::set($key, $value)`.
   - Preserved `SettingServiceProvider` runtime configuration binding (`config('settings.*')`).
   - Extended with static secret masking, category grouping, and strict permitted-key white-listing.
2. **`App\Http\Controllers\Admin\SMSController`**:
   - Reused existing Elitbuzz SMS payload structures (`contacts` with `88` prefix, `api_key`, `senderid`, `msg`, `type = text` or `text/unicode`).
   - Reused notification template messages for Today/Tomorrow Class Postponement, Course Fee Due Reminder, Class Schedule, and Class Reminder.
   - Refactored `SMSController::send_sms($phone, $msg)` to delegate to `App\Services\SmsService`, ensuring automatic delivery logging and mockability across all legacy callers.
3. **Decoupled Notification Callers**:
   - `StudentManagementController` (admission alerts)
   - `FeesTransectionsController` (payment collection confirmation)
   - `AssetTraineeApiController` (ASSET project trainee qualification alerts)
   - `BatchsController` (batch schedule notifications)
   - `UsersController` (student portal user credentials)

---

## 3. Implemented Endpoints & Contracts

### 3.1 System Settings API

| Method | URI | Auth / Middleware | Scope & Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/settings/public` | None (Public) | Returns non-sensitive site settings (name, title, logo, copyright, social links, theme). Strictly excludes secrets. |
| `GET` | `/api/v1/settings` | `auth:sanctum`, `admin.identity` | Returns all system settings grouped by category (`general`, `branding`, `footer_seo`, `social`, `analytics`, `payments`, `sms`, `ui_theme`) with sensitive keys masked. |
| `PUT` | `/api/v1/settings` | `auth:sanctum`, `admin.identity` | Updates permitted settings. Rejects unpermitted arbitrary keys with 422. Preserves stored secrets if masked placeholder is submitted. |
| `GET` | `/api/v1/settings/sms` | `auth:sanctum`, `admin.identity` | Returns SMS gateway configuration (`sms_url`, `sms_sender_id`, `sms_enabled`, `sms_provider`) with `sms_api_key` masked. |
| `PUT` | `/api/v1/settings/sms` | `auth:sanctum`, `admin.identity` | Updates SMS gateway provider configuration. Retains existing key if masked placeholder is submitted. |

### 3.2 SMS Gateway & Template Management API

| Method | URI | Auth / Middleware | Scope & Description |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/v1/sms/send` | `auth:sanctum`, `admin.identity`, `throttle:30,1` | Dispatches direct SMS to recipient. Normalizes phone, enforces idempotency, logs delivery. |
| `POST` | `/api/v1/sms/send-template` | `auth:sanctum`, `admin.identity`, `throttle:30,1` | Dispatches approved template SMS. Auto-interpolates placeholders from `student_id` or `trainee_id`. |
| `POST` | `/api/v1/sms/send-batch` | `auth:sanctum`, `admin.identity`, `throttle:10,1` | Bulk dispatches template or custom SMS to all active students enrolled in an academic batch. |
| `GET` | `/api/v1/sms/logs` | `auth:sanctum`, `admin.identity` | Paginated delivery audit logs with filters (`status`, `recipient`, `template_key`, `search`, `from_date`, `to_date`). |
| `GET` | `/api/v1/sms/logs/{id}` | `auth:sanctum`, `admin.identity` | Returns detailed audit log entry including sender, student, and batch relations. |
| `GET` | `/api/v1/sms/templates` | `auth:sanctum`, `admin.identity` | Returns catalog of approved system SMS templates with default text and active overrides. |
| `PUT` | `/api/v1/sms/templates/{key}` | `auth:sanctum`, `admin.identity` | Updates and persists custom text override for an approved SMS template. |
| `POST` | `/api/v1/sms/test-connection` | `auth:sanctum`, `admin.identity`, `throttle:5,1` | Tests gateway provider connectivity and credentials validity without sending real messages. |

---

## 4. Settings Access Rules & Secret Protection

### 4.1 Public vs. Administrative Scopes
- **Public Settings (`GET /api/v1/settings/public`):**
  - Permitted keys: `site_name`, `site_title`, `default_email_address`, `currency_code`, `currency_symbol`, `site_logo`, `site_favicon`, `footer_copyright_text`, `seo_meta_title`, `seo_meta_description`, `short_about`, `social_facebook`, `social_twitter`, `social_instagram`, `social_linkedin`, `social_youtube`, `contact_phone`, `contact_address`, `office_hours`, and UI theme configuration.
  - Zero sensitive keys or third-party credentials are ever returned to unauthenticated clients.
- **Administrative Settings (`GET /api/v1/settings` & `GET /api/v1/settings/sms`):**
  - Requires valid Sanctum token with `Admin` guard identity.
  - Sensitive keys are automatically masked before JSON serialization:
    - `sms_api_key`
    - `stripe_secret_key`, `stripe_key`
    - `paypal_secret_id`, `paypal_client_id`
    - `bkash_app_key`, `bkash_app_secret`, `bkash_password`
    - `mail_password`, `aws_secret_access_key`

### 4.2 Masking Format & Preservation on Update
- Masking format: If length > 12, displays first 4 characters, 8 bullet characters (`••••••••`), and last 4 characters (e.g. `test••••••••7890`). Shorter strings display 8 bullets.
- When an administrator submits an update form containing a masked placeholder string (e.g. `••••••••` or `test••••••••7890`), the backend detects `Setting::isMaskedValue($val)` and **preserves the existing secret** in the database rather than corrupting it with asterisks/bullets.

### 4.3 Protection Against Arbitrary `.env` / Configuration Injection
- All settings updates pass through `UpdateSettingsRequest` which enforces `Setting::isPermittedKey($key)`.
- Rejects unpermitted arbitrary keys (e.g. `app_key`, `database_password`, `aws_access_key_id`) with HTTP 422 Unprocessable Entity.

---

## 5. SMS Gateway Provider Integration & Delivery Engine

### 5.1 Provider Capabilities & Protocol
- **Provider:** Elitbuzz SMS Gateway (`https://api.elitbuzz-bd.com/smsapi`).
- **HTTP Method:** `POST` (cURL transport with 15-second timeout and SSL host verification).
- **Contacts Format:** Canonical Bangladeshi format `8801XXXXXXXXX`.
- **Character Encoding:** Automatically detects Unicode / Bengali characters in the message body and sets `type = text/unicode` (or `text` for ASCII).

### 5.2 Delivery Status Lifecycles
- `queued`: Initial state before network dispatch.
- `sent`: Successfully accepted/submitted by the provider gateway (e.g. `SMS SUBMITTED: <id>`).
- `failed`: Provider rejection, authentication failure, disabled gateway, or network error.

### 5.3 Reliability, Duplicate Prevention & Cost Controls
1. **Idempotency Guard (60-second window):**
   - Dispatches check `SmsLog` for an identical recipient and message within the past 60 seconds with `status = sent`.
   - Duplicates are rejected with HTTP 422 (`code: DUPLICATE_SMS`) to prevent double-charges and recipient spam.
2. **Rate Limiting:**
   - Direct SMS & Template SMS: `throttle:30,1` (30 requests/min).
   - Batch SMS: `throttle:10,1` (10 batch dispatches/min).
   - Connectivity Test: `throttle:5,1` (5 pings/min).
3. **Audit Log Sanitization:**
   - Raw responses saved to `sms_logs.response_payload` are stripped of any API keys or tokens before persistence.

---

## 6. Approved SMS Templates Catalog

| Key | Name | Language | Placeholders | Default Message Summary |
| :--- | :--- | :--- | :--- | :--- |
| `today_postpone` | Today Class Postponed | Bengali | `{name}`, `{contact}` | Class postponed today notice with contact number. |
| `tomorrow_postpone` | Tomorrow Class Postponed | Bengali | `{name}`, `{contact}` | Class postponed tomorrow notice with contact number. |
| `due_reminder` | Course Fee Due Reminder | Bengali | `{name}`, `{due}` | Outstanding fee reminder with formatted balance due. |
| `class_schedule` | New Class Schedule | English | `{name}`, `{schedule}` | Weekly batch schedule notification. |
| `class_reminder_today` | Today Class Reminder | Bengali | `{name}`, `{date}`, `{schedule}`, `{course}` | Reminder for class scheduled today. |
| `trainee_qualified` | ASSET Trainee Qualified | English | `{name}`, `{course}`, `{contact}` | Trainee selection congratulations notice. |
| `trainee_status_update` | Trainee Status Update | English | `{name}`, `{course}`, `{status}`, `{contact}` | Application stage status change alert. |
| `fee_payment_receipt` | Fee Payment Receipt | English | `{name}`, `{amount}`, `{due}`, `{course}` | Payment acknowledgment and remaining balance. |
| `user_account_created` | Student Portal Credentials | English | `{name}`, `{email}`, `{password}`, `{login_url}` | Student dashboard login credentials. |

---

## 7. Files Changed & Added

### New Files Created:
1. `database/migrations/2026_10_25_000001_create_sms_logs_table.php` — Migration for SMS delivery audit logging.
2. `app/Models/SmsLog.php` — Eloquent model for SMS delivery records with sender, student, and batch relations.
3. `app/Services/SmsService.php` — Core SMS dispatch, phone normalization, templating, duplicate prevention, and mock transport engine.
4. `app/Http/Requests/Api/V1/SendSmsRequest.php` — Form request for direct SMS dispatch.
5. `app/Http/Requests/Api/V1/SendTemplateSmsRequest.php` — Form request for template-based SMS dispatch.
6. `app/Http/Requests/Api/V1/SendBatchSmsRequest.php` — Form request for batch student SMS notification.
7. `app/Http/Requests/Api/V1/UpdateSettingsRequest.php` — Form request for permitted settings updates.
8. `app/Http/Requests/Api/V1/UpdateSmsSettingsRequest.php` — Form request for SMS provider configuration.
9. `app/Http/Requests/Api/V1/UpdateSmsTemplateRequest.php` — Form request for custom SMS template overrides.
10. `app/Http/Controllers/Api/V1/SettingApiController.php` — REST API controller for public and administrative settings.
11. `app/Http/Controllers/Api/V1/SmsApiController.php` — REST API controller for SMS operations, logs, and templates.
12. `tests/Feature/Api/V1/SmsAndSettingsApiTest.php` — Dedicated test suite with 29 test cases.
13. `docs/modernization/PHASE_2_9_SMS_GATEWAY_SYSTEM_SETTINGS_API_IMPLEMENTATION.md` — Implementation report.

### Existing Files Modified:
1. `app/Models/Setting.php` — Extended with secret masking (`maskSecret`), permitted key verification (`isPermittedKey`), and categorized retrieval.
2. `app/Http/Controllers/Admin/SMSController.php` — Refactored `send_sms` to delegate to `SmsService::sendRaw`.
3. `app/Http/Controllers/Admin/FeesTransectionsController.php` — Refactored inline cURL to delegate to `SmsService::sendRaw`.
4. `app/Http/Controllers/Admin/UsersController.php` — Refactored inline cURL to delegate to `SmsService::sendRaw`.
5. `app/Http/Controllers/Api/V1/FeeTransactionApiController.php` — Refactored inline cURL to delegate to `SmsService::sendRaw`.
6. `routes/api.php` — Registered 14 new REST endpoints under `/api/v1/settings/*` and `/api/v1/sms/*`.
7. `docs/modernization/MODULE_API_REACT_MIGRATION_MAP.md` — Updated Phase 2.9 Communication (SMS) status to Complete.

---

## 8. Automated Testing & Verification Evidence

All tests were executed against an isolated in-memory SQLite database (`:memory:`) with zero network calls and zero MySQL mutations.

### Test Commands Executed:

```bash
# 1. Dedicated SMS Gateway & System Settings Feature Test Suite
php vendor/bin/phpunit tests/Feature/Api/V1/SmsAndSettingsApiTest.php
```

**Output:**
```
PHPUnit 9.6.29 by Sebastian Bergmann and contributors.

.............................                                     29 / 29 (100%)

Time: 00:06.600, Memory: 36.00 MB

OK (29 tests, 134 assertions)
```

```bash
# 2. Complete Application Regression Test Suite
php vendor/bin/phpunit
```

**Output:**
```
PHPUnit 9.6.29 by Sebastian Bergmann and contributors.

...............................................................  63 / 254 ( 24%)
............................................................... 126 / 254 ( 49%)
............................................................... 189 / 254 ( 74%)
............................................................... 252 / 254 ( 99%)
..                                                              254 / 254 (100%)

Time: 00:54.704, Memory: 72.00 MB

OK (254 tests, 1331 assertions)
```

### Verified Coverage Highlights:
- **Authentication & RBAC:** Unauthenticated (401) and student user (403) access barriers verified.
- **Privacy & Secret Protection:** Zero secrets exposed in public settings; all sensitive keys masked in admin responses.
- **Tampering Resistance:** Unpermitted configuration keys rejected with 422; masked placeholders preserved.
- **SMS Number Normalization:** Successfully handled `018...`, `+88018...`, and `88018...` into canonical `8801...`.
- **Idempotency:** 60-second duplicate send protection verified.
- **Provider Resilience:** Provider errors and timeouts handled gracefully with clean 502 envelopes and no stack trace leaks.
- **Audit Logging:** Delivery logs persisted with sender ID, recipient, message, and sanitized provider response.
- **Backward Compatibility:** Legacy `SMSController@send_sms` verified to dispatch and record logs seamlessly.

---

## 9. Known Limitations & Architecture Notes

1. **Two-Way SMS / Webhook Callbacks:**
   - Elitbuzz SMS Gateway is primarily a one-way transactional HTTP SMS gateway. Delivery status is determined synchronously upon submission (`SMS SUBMITTED: <id>`). Asynchronous DLR delivery receipt webhooks are not currently configured in the legacy integration.
2. **SMS Balance Queries:**
   - In production environments, testing connectivity checks account balance via `?type=balance`. If the provider IP whitelist restricts requests, connectivity tests should ensure server egress IP is whitelisted.
3. **Queue Worker vs. Synchronous Send:**
   - Current implementation dispatches SMS synchronously via `SmsService::send()`. For high-volume bulk batch sends (> 200 students), dispatching via a Laravel Queue Job (`SendSmsJob`) is recommended once the production queue worker daemon is enabled.

---

## 10. Final Phase Status

- **Status:** **PASS**
- **Readiness:** Backend APIs for SMS Gateway & System Settings are fully implemented, secured, tested, and ready for React frontend consumption in Phase 3.
