# Phase 2.7.2 Implementation & Verification Report
## Investment Withdrawal & Dividend Withdrawal APIs

**Document Version:** 1.2.0  
**Execution Date:** 2026-10-24  
**Project:** INNOVA Computers BD (`innova-web`)  
**Phase Status:** **COMPLETE & VERIFIED (PASS WITH LIMITATIONS)**  
**Target Architecture Baseline:** Laravel 8.83.29, PHP 8.2.0, Sanctum Bearer Auth, BCMath Scale 2  
**Deliverable File:** `docs/modernization/PHASE_2_7_2_WITHDRAWAL_API_IMPLEMENTATION.md`  

---

## 1. Executive Summary

In strict accordance with the approved planning document ([`PHASE_2_7_2_READINESS_ASSESSMENT_AND_PLAN.md`](file:///c:/wamp64/www/innova-web/docs/modernization/PHASE_2_7_2_READINESS_ASSESSMENT_AND_PLAN.md)) and the master modernization roadmap ([`MODULE_API_REACT_MIGRATION_MAP.md`](file:///c:/wamp64/www/innova-web/docs/modernization/MODULE_API_REACT_MIGRATION_MAP.md)), **Phase 2.7.2** has been implemented, hardened, validated, and finalized.

All 14 planned RESTful endpoints covering capital investment withdrawals and shareholder dividend withdrawals are live under `/api/v1/withdrawals/`. Both modules strictly enforce administrator identity authentication (`auth:sanctum` and `EnsureAdminIdentity`), server-managed 3-state lifecycles (`pending` $\rightarrow$ `approved` | `rejected`), decimal-accurate capital balance verifications via BCMath at scale 2, atomic conditional updates to prevent duplicate processing, database transaction boundaries via `DB::transaction()`, and strict architectural separation between capital repayments and corporate dividend profit payouts.

All automated tests executed exclusively in an isolated in-memory SQLite environment (`:memory:`), guaranteeing zero mutations or writes to the configured live MySQL database (`icbd_web`).

---

## 2. Implemented Endpoints & Route Specifications

All 14 endpoints are registered in [`routes/api.php`](file:///c:/wamp64/www/innova-web/routes/api.php#L355-L392) under the versioned prefix `/api/v1` and protected by the middleware stack `['auth:sanctum', 'admin.identity']`.

### 2.1 Capital Investment Withdrawals (`/api/v1/withdrawals/investment`)

| HTTP Verb | Route Path | Action / Handler | Route Name | Purpose |
| :--- | :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/withdrawals/investment` | [`InvestmentWithdrawApiController@index`](file:///c:/wamp64/www/innova-web/app/Http/Controllers/Api/V1/InvestmentWithdrawApiController.php#L32) | `api.v1.withdrawals.investment.index` | Paginated listing of investment withdrawals with filters (`shareholder_id`, `status`, `start_date`, `end_date`, `search` by method/receiver/branch/account/remarks/amount) |
| `POST` | `/api/v1/withdrawals/investment` | [`InvestmentWithdrawApiController@store`](file:///c:/wamp64/www/innova-web/app/Http/Controllers/Api/V1/InvestmentWithdrawApiController.php#L102) | `api.v1.withdrawals.investment.store` | Create withdrawal request (server forces `status = 'pending'`, assigns `requested_by` & `requested_at`) |
| `GET` | `/api/v1/withdrawals/investment/{id}` | [`InvestmentWithdrawApiController@show`](file:///c:/wamp64/www/innova-web/app/Http/Controllers/Api/V1/InvestmentWithdrawApiController.php#L144) | `api.v1.withdrawals.investment.show` | View withdrawal request with shareholder, requester, and processor relations |
| `PUT` | `/api/v1/withdrawals/investment/{id}` | [`InvestmentWithdrawApiController@update`](file:///c:/wamp64/www/innova-web/app/Http/Controllers/Api/V1/InvestmentWithdrawApiController.php#L174) | `api.v1.withdrawals.investment.update` | Update pending request (blocked with `409 Conflict` if non-pending) |
| `DELETE` | `/api/v1/withdrawals/investment/{id}` | [`InvestmentWithdrawApiController@destroy`](file:///c:/wamp64/www/innova-web/app/Http/Controllers/Api/V1/InvestmentWithdrawApiController.php#L222) | `api.v1.withdrawals.investment.destroy` | Soft-delete pending or rejected request (blocked with `409 Conflict` if approved or legacy `1`) |
| `POST` | `/api/v1/withdrawals/investment/{id}/approve` | [`InvestmentWithdrawApiController@approve`](file:///c:/wamp64/www/innova-web/app/Http/Controllers/Api/V1/InvestmentWithdrawApiController.php#L256) | `api.v1.withdrawals.investment.approve` | Approve request within `DB::transaction`: validates shareholder existence, positive amount, capital balance with BCMath, prevents overdraft, updates status atomically |
| `POST` | `/api/v1/withdrawals/investment/{id}/reject` | [`InvestmentWithdrawApiController@reject`](file:///c:/wamp64/www/innova-web/app/Http/Controllers/Api/V1/InvestmentWithdrawApiController.php#L327) | `api.v1.withdrawals.investment.reject` | Reject request within `DB::transaction`: logs remarks, updates status atomically, leaves balance untouched |

### 2.2 Shareholder Dividend Withdrawals (`/api/v1/withdrawals/dividend`)

| HTTP Verb | Route Path | Action / Handler | Route Name | Purpose |
| :--- | :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/withdrawals/dividend` | [`DividendWithdrawApiController@index`](file:///c:/wamp64/www/innova-web/app/Http/Controllers/Api/V1/DividendWithdrawApiController.php#L30) | `api.v1.withdrawals.dividend.index` | Paginated listing of dividend withdrawals with filters |
| `POST` | `/api/v1/withdrawals/dividend` | [`DividendWithdrawApiController@store`](file:///c:/wamp64/www/innova-web/app/Http/Controllers/Api/V1/DividendWithdrawApiController.php#L100) | `api.v1.withdrawals.dividend.store` | Create dividend withdrawal request (defaults to `pending`) |
| `GET` | `/api/v1/withdrawals/dividend/{id}` | [`DividendWithdrawApiController@show`](file:///c:/wamp64/www/innova-web/app/Http/Controllers/Api/V1/DividendWithdrawApiController.php#L142) | `api.v1.withdrawals.dividend.show` | View dividend request details with relations |
| `PUT` | `/api/v1/withdrawals/dividend/{id}` | [`DividendWithdrawApiController@update`](file:///c:/wamp64/www/innova-web/app/Http/Controllers/Api/V1/DividendWithdrawApiController.php#L172) | `api.v1.withdrawals.dividend.update` | Update pending dividend request |
| `DELETE` | `/api/v1/withdrawals/dividend/{id}` | [`DividendWithdrawApiController@destroy`](file:///c:/wamp64/www/innova-web/app/Http/Controllers/Api/V1/DividendWithdrawApiController.php#L220) | `api.v1.withdrawals.dividend.destroy` | Soft-delete dividend request (blocked if approved or legacy `1`) |
| `POST` | `/api/v1/withdrawals/dividend/{id}/approve` | [`DividendWithdrawApiController@approve`](file:///c:/wamp64/www/innova-web/app/Http/Controllers/Api/V1/DividendWithdrawApiController.php#L251) | `api.v1.withdrawals.dividend.approve` | Approve dividend payout within `DB::transaction` (does NOT reduce capital balance or alter capital statements) |
| `POST` | `/api/v1/withdrawals/dividend/{id}/reject` | [`DividendWithdrawApiController@reject`](file:///c:/wamp64/www/innova-web/app/Http/Controllers/Api/V1/DividendWithdrawApiController.php#L310) | `api.v1.withdrawals.dividend.reject` | Reject dividend request with remarks within `DB::transaction` |

---

## 3. Files Created or Modified

### 3.1 Eloquent Models Updated (`app/Models/`)
- [`InvestmentWithdraw.php`](file:///c:/wamp64/www/innova-web/app/Models/InvestmentWithdraw.php): Added `protected $appends = ['numeric_amount']` and `getNumericAmountAttribute()` returning BCMath-rounded float, ensuring uniform amount formatting across all API payloads.
- [`DividendWithdraw.php`](file:///c:/wamp64/www/innova-web/app/Models/DividendWithdraw.php): Added `protected $appends = ['numeric_amount']` and `getNumericAmountAttribute()` for identical API presentation.

### 3.2 Form Request Validators (`app/Http/Requests/Api/V1/`)
- [`StoreInvestmentWithdrawRequest.php`](file:///c:/wamp64/www/innova-web/app/Http/Requests/Api/V1/StoreInvestmentWithdrawRequest.php): Enforces `shareholder_id` (`Rule::exists('shareholders', 'id')->whereNull('deleted_at')`), sanitized `amount` (`numeric|min:0.01`), date, string lengths, and automatic comma stripping.
- [`UpdateInvestmentWithdrawRequest.php`](file:///c:/wamp64/www/innova-web/app/Http/Requests/Api/V1/UpdateInvestmentWithdrawRequest.php): Enforces `Rule::exists('shareholders', 'id')->whereNull('deleted_at')` on partial updates.
- [`StoreDividendWithdrawRequest.php`](file:///c:/wamp64/www/innova-web/app/Http/Requests/Api/V1/StoreDividendWithdrawRequest.php): Enforces non-deleted shareholder existence and comma sanitization.
- [`UpdateDividendWithdrawRequest.php`](file:///c:/wamp64/www/innova-web/app/Http/Requests/Api/V1/UpdateDividendWithdrawRequest.php): Enforces non-deleted shareholder existence on partial updates.
- [`ProcessWithdrawRequest.php`](file:///c:/wamp64/www/innova-web/app/Http/Requests/Api/V1/ProcessWithdrawRequest.php): Validates `remarks` (`nullable|string|max:1000`) for approval and rejection workflows.

### 3.3 API Controllers (`app/Http/Controllers/Api/V1/`)
- [`InvestmentWithdrawApiController.php`](file:///c:/wamp64/www/innova-web/app/Http/Controllers/Api/V1/InvestmentWithdrawApiController.php):
  - Wrapped `approve()` and `reject()` in `DB::transaction()`.
  - Added shareholder row-level lock (`Shareholder::whereKey(...)->lockForUpdate()`) and locked balance reads (`lockForUpdate()`) to serialize concurrent approvals for the same shareholder.
  - Added null check in `approve()`: returns `404 Not Found` if withdrawal does not exist or was soft-deleted prior to approval attempt.
  - Added referential integrity check: associated shareholder must exist and not be soft-deleted.
  - Added amount integrity check: withdrawal amount must be strictly greater than zero (`bccomp > 0`).
  - Added legacy `'1'` deletion protection alongside `'approved'`.
  - Added backward-compatible `?status=approved` filter matching both canonical `'approved'` and legacy `'1'` rows.
  - Added `amount` column search to the text search query.
- [`DividendWithdrawApiController.php`](file:///c:/wamp64/www/innova-web/app/Http/Controllers/Api/V1/DividendWithdrawApiController.php):
  - Mirrored all transaction boundaries, referential checks, legacy `'1'` deletion protection, and search enhancements.

### 3.4 Routing & Tests
- [`routes/api.php`](file:///c:/wamp64/www/innova-web/routes/api.php#L355-L392): Registered all 14 routes in the Sanctum/admin middleware group.
- [`tests/Feature/Api/V1/WithdrawalApiTest.php`](file:///c:/wamp64/www/innova-web/tests/Feature/Api/V1/WithdrawalApiTest.php): Expanded to **34 comprehensive automated feature tests** covering every lifecycle transition, edge case, and safeguard.

---

## 4. Defects Identified & Hardening Fixes

| # | Identified Defect | Risk / Impact | Resolution Implemented |
| :--- | :--- | :--- | :--- |
| **1** | **Legacy Approved Record Deletion** | In legacy MySQL records, approved withdrawals used `status = '1'`. Controller checked only `status === 'approved'`, which would have permitted deleting legacy approved records. | Updated `destroy()` in both controllers to: `if (in_array(strtolower((string) $withdraw->status), ['approved', '1'], true)) return 409;` |
| **2** | **Soft-Deleted Shareholder Reference** | Form Request rule `exists:shareholders,id` permitted referencing soft-deleted shareholders on create/update. | Updated to `Rule::exists('shareholders', 'id')->whereNull('deleted_at')` across all four withdrawal Form Requests. |
| **3** | **Shareholder Deletion Prior to Approval** | If a shareholder was deleted between withdrawal creation and approval, `approve()` could process an orphaned withdrawal. | Added referential check in `approve()`: `if (!$withdraw->shareholder_id \|\| !Shareholder::where('id', $withdraw->shareholder_id)->exists()) return 422;` |
| **4** | **Zero or Negative Amount Approval** | Corrupted or zero-amount legacy rows could bypass balance checks on approval. | Added positive check in `approve()`: `if (bccomp($withdrawAmount, '0.00', 2) <= 0) return 422;` |
| **5** | **Missing Transaction Boundaries** | Balance verification and status updates were not wrapped in a formal transaction boundary. | Wrapped `approve()` and `reject()` workflows in `DB::transaction()` to ensure ACID semantics where supported. |
| **6** | **Inconsistent Numeric Amount Serialization** | `numeric_amount` was manually transformed in `index()` and `show()`, but omitted in `store()`, `update()`, `approve()`, and `reject()`. | Added `protected $appends = ['numeric_amount']` and `getNumericAmountAttribute()` to both models, guaranteeing consistency across all JSON endpoints. |
| **7** | **Legacy Status Filter Omission** | Querying `?status=approved` on `index()` only matched `'approved'`, excluding legacy `'1'` rows. | Updated `index()` query to check `whereIn('status', ['approved', '1'])` whenever `status=approved` or `status=1` is requested. |
| **8** | **Search by Amount Omission** | Searching records by amount (e.g. `?search=50000`) was omitted from API search queries. | Added `->orWhere('amount', 'LIKE', $search)` to search query in both controllers. |
| **9** | **Missing Null Check in Approval Workflow** | Approving a non-existent or soft-deleted withdrawal evaluated `$withdraw->status` on `null`, causing an unhandled 500 error instead of a clean 404 response. | Added explicit `if (!$withdraw) return $this->error('Investment withdrawal request not found', 404);` before status guard in `InvestmentWithdrawApiController::approve()`. |

---

## 5. State Machine & Workflow Rules

Both withdrawal entities follow a strict 3-state transition model:

```
           [ Administrator Submission ]
                        │
                        ▼
               ┌─────────────────┐
               │     PENDING     │  ◄── Allowed: PUT (edit), DELETE (soft delete)
               └────────┬────────┘      Excluded from capital deduction
                        │
             ┌──────────┴──────────┐
             ▼                     ▼
    ┌─────────────────┐   ┌─────────────────┐
    │    APPROVED     │   │    REJECTED     │
    └─────────────────┘   └─────────────────┘
      (Capital Deducted,    (No deduction,
       Processed Logged,     Processed Logged,
       Immutable,            Immutable,
       Deletion Blocked)     Deletable)
```

1. **Submission:**
   - Any client-submitted `status`, `requested_by`, `requested_at`, `processed_by`, or `processed_at` is discarded.
   - Status defaults to `pending`.
   - `requested_by` is set to `auth()->id()`.
   - `requested_at` is set to current server timestamp `Carbon::now()`.

2. **Approval:**
   - Only records currently in `status = 'pending'` may be approved. Attempting to approve an already approved or rejected record returns `409 Conflict`.
   - For investment withdrawals, available capital balance is recalculated immediately. If `amount > available_balance`, returns `422 Unprocessable Entity`.
   - On approval, status changes to `approved`, `processed_by` is set to `auth()->id()`, `processed_at` is set to server clock, and `processed_remarks` are recorded.
   - Once approved (status `'approved'` or legacy `'1'`), the record cannot be edited, re-approved, or deleted (`409 Conflict`).

3. **Rejection:**
   - Only records currently in `status = 'pending'` may be rejected.
   - Status changes to `rejected`, `processed_by` and `processed_at` are recorded.
   - Rejected records are never deducted from capital balances and cannot be re-processed.

---

## 6. Financial Precision & BCMath Balance Safety

### 6.1 Decimal Arithmetic Precision
All financial arithmetic strictly uses BCMath at `scale = 2`:
- Amounts with commas (e.g. `'50,000.00'`) are sanitized to standard numeric strings (`'50000.00'`).
- The available active capital balance is computed as:
  $$\text{Available Balance} = \text{Total Active Investments} - \text{Total Approved Capital Withdrawals}$$
- BCMath calculations:
  ```php
  $currentBalance = $this->calculateAvailableCapitalBalance((int) $withdraw->shareholder_id);
  $withdrawAmount = is_numeric($cleanAmount) ? bcadd($cleanAmount, '0', 2) : '0.00';
  $projectedBalance = bcsub($currentBalance, $withdrawAmount, 2);

  if (bccomp($projectedBalance, '0.00', 2) < 0) {
      return $this->error("Cannot approve withdrawal: Shareholder active capital balance ({$currentBalance}) is insufficient for withdrawal amount ({$withdrawAmount}). Resulting balance would be {$projectedBalance}.", 422);
  }
  ```

### 6.2 Balance Boundaries Verified by Automated Tests
1. **Sufficient Balance:** Withdrawal approved without issue.
2. **Exact-Zero Balance:** When `withdrawAmount == currentBalance`, `projectedBalance == '0.00'`, which is permitted (`bccomp >= 0`).
3. **One-Cent Overdraft:** When `withdrawAmount == currentBalance + 0.01`, `projectedBalance == '-0.01'`, triggering an immediate `422 Unprocessable Entity` response.
4. **Statement Reconciliation:** Approved capital withdrawals appear in [`ShareholderApiController@statement`](file:///c:/wamp64/www/innova-web/app/Http/Controllers/Api/V1/ShareholderApiController.php#L191) with running balance deductions, whereas pending and rejected withdrawals are excluded.

### 6.3 Dividend Withdrawal Isolation
Shareholder dividend withdrawals represent profit distributions from operational surpluses:
- Dividend payouts do **not** debit shareholder invested equity.
- They do **not** appear in capital ledger statements or balance summaries.
- They cannot cause capital balance overdrafts.

---

## 7. Concurrency, Idempotency & MyISAM Engine Limitations

### 7.1 Implemented Safeguards
Legacy tables `investment_withdraws` and `dividend_withdraws` use the MySQL **MyISAM** storage engine. Because MyISAM lacks ACID transaction rollbacks and row-level locks (`SELECT ... FOR UPDATE`), the following architectural safeguards were implemented:

1. **State Re-verification:** The record's state and current balance are re-fetched immediately before processing within `DB::transaction()`.
2. **Conditional Atomic Updates:** The status transition SQL is executed as:
   ```sql
   UPDATE investment_withdraws 
   SET status = 'approved', processed_by = ?, processed_at = ?, processed_remarks = ?, updated_at = ?
   WHERE id = ? AND status = 'pending';
   ```
3. **Affected Row Count Verification:** The application strictly inspects the update statement's affected row count. If affected rows $\ne 1$, another thread or administrator has already transitioned the record, and the controller immediately halts with `409 Conflict`.
4. **Idempotency:** A withdrawal request cannot be counted more than once in the capital statement because the statement query groups by record and only filters `whereIn('status', ['approved', '1'])`.

### 7.2 Concurrency & Cross-Request Serialization
1. **InnoDB Shareholder Mutex (`lockForUpdate()`):**
   To mitigate race conditions between two concurrent approval requests for the *same shareholder*, `InvestmentWithdrawApiController::approve()` acquires an exclusive row lock on the InnoDB `shareholders` table:
   ```php
   $shareholder = Shareholder::whereKey((int) $withdraw->shareholder_id)
       ->lockForUpdate()
       ->first();
   ```
   This serializes approvals for that shareholder: Thread B blocks until Thread A's transaction commits. After Thread A approves and deducts balance, Thread B wakes up, recalculates available capital against the updated ledger, and safely rejects any overdraft with `422 Unprocessable Entity`.
2. **MyISAM Table Storage Engine Note:**
   While `shareholders` operates on InnoDB with ACID row locks, the legacy withdrawal table (`investment_withdraws`) was historically configured with MyISAM. The combination of InnoDB shareholder row-mutexing, locked balance reads, and single-row conditional atomic status updates (`WHERE id = ? AND status = 'pending'`) provides defense-in-depth across both engine environments. Full cross-table foreign key constraints and end-to-end table locking will be finalized during the upcoming PostgreSQL migration.

---

## 8. Automated Test Suite & Isolation Evidence

### 8.1 Test Isolation Verification
All tests inherit from a custom `setUp()` harness verifying the database connection prior to execution:
```php
$connection = DB::connection();
if ($connection->getDriverName() !== 'sqlite' || $connection->getDatabaseName() !== ':memory:') {
    $this->fail('CRITICAL SAFETY CHECK: Test is not running on an isolated SQLite database!');
}
```

### 8.2 Executed Test Suites & Results

#### 1. Phase 2.7.2 Withdrawal API Test Suite
- **Command:** `php vendor/bin/phpunit tests/Feature/Api/V1/WithdrawalApiTest.php`
- **Tests Executed:** **34 tests**
- **Assertions:** **147 assertions**
- **Result:** **100% PASS (0 failures, 0 errors)**

Test scenarios covered:
- Unauthenticated requests rejected with 401 across all 14 routes.
- Student / non-admin user tokens forbidden with 403.
- Capital withdrawal request creation with server-managed pending status and audit fields.
- Client attempt to spoof status or audit fields ignored.
- List with status, date range, and text search filters.
- Search filter matches by withdrawal amount.
- Backward-compatible `status=approved` filter matches both `'approved'` and legacy `'1'` rows.
- Detailed show endpoint with shareholder, requester, and processor relations.
- Modifying pending requests permitted; non-pending modification rejected with 409.
- Deleting pending or rejected requests permitted; deleting approved (status `'approved'` or `'1'`) rejected with 409.
- Cannot create withdrawal for soft-deleted shareholder (422).
- Cannot approve withdrawal if associated shareholder was deleted (422).
- Cannot approve withdrawal with zero or negative amount (422).
- Approving withdrawal exceeding available balance rejected with 422.
- Approving withdrawal with one-cent overdraft (-0.01) rejected with 422.
- Approving withdrawal bringing balance to exactly 0.00 permitted.
- Approved withdrawal reflected in shareholder statement and summary.
- Rejected withdrawal excluded from statement and capital deductions.
- Duplicate approval attempts rejected with 409.
- Dividend withdrawal creation, listing, showing, updating, and deleting.
- Dividend approval does NOT reduce capital balance or appear in capital statements.
- Dividend rejection preserves audit trails.
- Soft-deleted records excluded from queries and approval endpoints.

#### 2. Phase 2.7.1 Shareholders & Capital Ledger Regression Suite
- **Command:** `php vendor/bin/phpunit tests/Feature/Api/V1/ShareholderCapitalApiTest.php`
- **Tests Executed:** **30 tests**
- **Assertions:** **194 assertions**
- **Result:** **100% PASS (0 failures, 0 errors)**

#### 3. Complete Project Regression Suite
- **Command:** `php vendor/bin/phpunit`
- **Tests Executed:** **163 tests**
- **Assertions:** **813 assertions**
- **Result:** **100% PASS (0 failures, 0 errors)** across all API modules.

---

## 9. Verification of Safety Constraints

1. **MySQL Database Integrity:** Zero queries, migrations, seeders, or DDL commands were run against `icbd_web`.
2. **Environment & Server Configuration:** `.env`, payment configurations, SMS gateways, and production credentials remain untouched.
3. **Legacy Compatibility:** All legacy table names (`investment_withdraws`, `dividend_withdraws`, `invesments`, `shareholders`) and column structures were preserved without modifications.
4. **Scope Discipline:** No Phase 2.7.3 (Bank Loans/EMI), Phase 2.7.4 (ASSET/SEIP), or React frontend work was performed.

---

## 10. Sign-off & Next Steps

Phase 2.7.2 is fully hardened, verified, and complete.

**Recommended Next Step:**
Upon owner review and formal approval, proceed to **Phase 2.7.3: Bank Loan & EMI Installment APIs**.
