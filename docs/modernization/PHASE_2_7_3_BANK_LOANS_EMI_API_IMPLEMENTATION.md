# Phase 2.7.3 — Bank Loans & EMI API Implementation Report

**Project:** INNOVA Computers BD Web System Modernization  
**Repository:** `sahabuddin123/innova-web2`  
**Working Directory:** `C:\wamp64\www\innova-web`  
**Branch:** `feature/phase-1-security-hardening`  
**Phase:** 2.7.3 — Bank Loans & EMI API Implementation  
**Status:** COMPLETE (PASS)  
**Date:** October 25, 2026  
**Architect:** Senior Software Architect, Financial Systems Specialist & QA Engineer  

---

## 1. Executive Summary

Phase 2.7.3 implements a production-grade, backward-compatible REST API suite under `/api/v1/loans/*` for **Bank Loans** and **EMI Installment Schedules / Repayments**.

The implementation extends the existing Laravel 8 application architecture, building upon the forward-reconciliation database migrations (`bank_loans` and `emi_installments`) and aligning with the Spatie/Sanctum authentication infrastructure established in earlier phases.

All monetary calculations use BCMath with 2 decimal precision, exact remainder distribution for installment schedule generation, single-row conditional atomic transitions to ensure idempotency and double-payment prevention across both InnoDB and MyISAM database storage engines, and strict audit deletion guards to prevent orphaned or compromised financial audit trails.

---

## 2. Existing Functionality Reused

1. **Database Schema & Eloquent Models:**
   - `App\Models\BankLoan`: Enhanced with BCMath methods (`calculateTotalInterest()`, `calculateTotalPayable()`, `totalEmiPaid()`, `remainingEmi()`, `totalSavings()`, `getMonthlyEmiSavings()`, `getMonthlySavingsAmount()`), relationship methods (`shareholder()`, `emiInstallments()`, `paidInstallments()`, `pendingInstallments()`, `creator()`), and numeric accessors.
   - `App\Models\EmiInstallment`: Enhanced with relationship methods (`bankLoan()`, `shareholder()`, `paidByUser()`, `receivedByUser()`) and numeric accessors.
   - `App\Models\Shareholder`: Enhanced with `bankLoans()` and `emiInstallments()` Eloquent relationships.
   - Preserved all existing legacy column names (`loan_amount`, `interest_rate`, `loan_term_months`, `installment_count`, `savings_amount`, `emi_amount`, `paid_by`, `received_by`, `paid_at`, `payment_method`, etc.).

2. **Controllers & Business Rules Reused:**
   - Logic from `App\Http\Controllers\Admin\BankLoanController` and `App\Http\Controllers\Admin\EmiInstallmentController` was extracted and modernized.
   - Blade view calculation rules from `resources/views/admin/bank_loan/statement.blade.php` and `resources/views/admin/bank_loan/emi_statement.blade.php` were formalized into canonical BCMath formulas.

3. **Authentication & Architecture Reused:**
   - Multi-guard Sanctum token authentication with `admin.identity` middleware barrier.
   - Base `App\Http\Controllers\Api\V1\ApiController` standardized JSON envelope (`success`, `message`, `data`, `meta`, `errors`).
   - Base `App\Http\Requests\Api\V1\ApiRequest` for standardized 422 JSON validation exception responses.

---

## 3. Implemented Endpoints & API Contract

All 13 endpoints are registered under `routes/api.php` with prefix `/api/v1` and protected by `['auth:sanctum', 'admin.identity']`:

### 3.1 Bank Loans Management Endpoints

| HTTP Method | Route URI | Action | Description & Guards |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/loans/bank-loans` | `BankLoanApiController@index` | Paginated listing with search (`search`), status filter (`status`), shareholder filter (`shareholder_id`), and date range (`start_date`, `end_date`). Decorates items with financial summaries. |
| `POST` | `/api/v1/loans/bank-loans` | `BankLoanApiController@store` | Creates a new loan. Validates shareholder existence, positive amounts, sanitizes commas, sets `status = 'active'`, records `created_by`. |
| `GET` | `/api/v1/loans/bank-loans/{id}` | `BankLoanApiController@show` | Retrieves loan details, relationships, and computed metrics (`total_interest`, `total_payable`, `total_paid`, `remaining_balance`, `total_savings`). Returns 404 if missing or soft-deleted. |
| `PUT` | `/api/v1/loans/bank-loans/{id}` | `BankLoanApiController@update` | Updates loan fields. Protects against arbitrary modifications if loan is closed unless explicitly reopened. Sanitizes numeric amounts. |
| `DELETE` | `/api/v1/loans/bank-loans/{id}` | `BankLoanApiController@destroy` | Soft-deletes loan. **Audit Guard:** Returns `409 Conflict` (`LOAN_HAS_PAID_INSTALLMENTS`) if any paid installments exist. Automatically cascades soft-delete to pending installments. |
| `GET` | `/api/v1/loans/bank-loans/{id}/statement` | `BankLoanApiController@statement` | Returns comprehensive loan statement including loan details, chronological list of installments, and `financial_summary` metrics. |
| `POST` | `/api/v1/loans/bank-loans/{id}/generate-schedule` | `BankLoanApiController@generateSchedule` | Generates equal monthly installments. Distributes cent remainder so sum matches `total_payable` exactly. Guards against regenerating if paid installments exist (`409 Conflict`). Overwrites pending when `overwrite_pending = true`. |

### 3.2 EMI Installments Management Endpoints

| HTTP Method | Route URI | Action | Description & Guards |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/loans/installments` | `EmiInstallmentApiController@index` | Paginated listing with filters (`bank_loan_id`, `shareholder_id`, `status`, `payment_method`, date range, search). |
| `POST` | `/api/v1/loans/installments` | `EmiInstallmentApiController@store` | Manually records an installment. Validates active bank loan, matching shareholder, and positive amounts. |
| `GET` | `/api/v1/loans/installments/{id}` | `EmiInstallmentApiController@show` | Retrieves installment details with parent bank loan, shareholder, and audit users. |
| `PUT` | `/api/v1/loans/installments/{id}` | `EmiInstallmentApiController@update` | Updates installment details. **Audit Guard:** Blocks modifying monetary amounts (`emi_amount`, `savings_amount`) if already paid (`409 Conflict` with `PAID_INSTALLMENT_AMOUNT_IMMUTABLE`). |
| `DELETE` | `/api/v1/loans/installments/{id}` | `EmiInstallmentApiController@destroy` | Soft-deletes pending installment. **Audit Guard:** Returns `409 Conflict` (`CANNOT_DELETE_PAID_INSTALLMENT`) if status is `paid`. |
| `POST` | `/api/v1/loans/installments/{id}/pay` | `EmiInstallmentApiController@pay` | Transitions installment status from `pending` to `paid`. Sets `paid_at`, `received_by`, `payment_method`. **Concurrency Guard:** Single-row conditional atomic update prevents double-payment. **Auto-Close:** Automatically transitions parent loan to `closed` when remaining balance reaches zero and all installments are paid. |

---

## 4. Financial Calculations & Precision Rules

All monetary and amortization arithmetic uses PHP BCMath (`scale = 2`):

1. **Total Interest:**
   $$\text{Total Interest} = \frac{P \times R \times M}{1200}$$
   Where:
   - $P$ = `loan_amount` (Principal)
   - $R$ = `interest_rate` (Annual percentage rate)
   - $M$ = `loan_term_months` (Duration in months)

2. **Total Payable:**
   $$\text{Total Payable} = P + \text{Total Interest}$$

3. **Total EMI Paid:**
   $$\text{Total EMI Paid} = \sum (\text{emi\_amount of all paid installments})$$

4. **Remaining Balance:**
   $$\text{Remaining Balance} = \max\left(0.00, \; \text{Total Payable} - \text{Total EMI Paid}\right)$$

5. **Total Savings:**
   $$\text{Total Savings} = \text{Initial Loan Savings} + \sum (\text{savings\_amount of all paid installments})$$

6. **Automated Amortization Schedule & Cent Remainder Distribution:**
   - Division of total payable by installment count $N$:
     $$\text{Base EMI} = \lfloor (\text{Total Payable} / N) \rfloor_{2}$$
   - Cent remainder calculation:
     $$\text{Remainder} = \text{Total Payable} - (\text{Base EMI} \times N)$$
   - The cent remainder is added to the final ($N$-th) installment:
     $$\text{Final Installment EMI} = \text{Base EMI} + \text{Remainder}$$
   - **Guaranteed Invariant:** The sum of all installment amounts matches `total_payable` down to the exact cent ($0.01$).

---

## 5. Storage Engine & Concurrency Safeguards

1. **Storage Engine Realities:**
   - In the live database, `bank_loans` and `emi_installments` are legacy MyISAM tables, whereas `shareholders` is InnoDB. Clean forward-reconciliation migrations default to InnoDB.
   - Because MyISAM does not support multi-statement atomic rollbacks, the API implements defense-in-depth single-row conditional atomic updates:
     ```php
     $affected = DB::table('emi_installments')
         ->where('id', $id)
         ->where('status', 'pending')
         ->whereNull('deleted_at')
         ->update($updateData);

     if ($affected !== 1) {
         return $this->error('Installment could not be paid...', 409, 'INSTALLMENT_ALREADY_PAID');
     }
     ```
   - This prevents race conditions and duplicate payments even if table locks or transaction rollbacks are unavailable at the database engine level.

2. **Soft Delete Safety:**
   - Both models use Laravel `SoftDeletes`.
   - Soft-deleted loans and installments are excluded from queries and cannot be paid, updated, or manipulated.

---

## 6. Files Changed

### Enhanced Models
- `app/Models/BankLoan.php`
- `app/Models/EmiInstallment.php`
- `app/Models/Shareholder.php`

### Form Requests Created
- `app/Http/Requests/Api/V1/StoreBankLoanRequest.php`
- `app/Http/Requests/Api/V1/UpdateBankLoanRequest.php`
- `app/Http/Requests/Api/V1/GenerateEmiScheduleRequest.php`
- `app/Http/Requests/Api/V1/StoreEmiInstallmentRequest.php`
- `app/Http/Requests/Api/V1/UpdateEmiInstallmentRequest.php`
- `app/Http/Requests/Api/V1/PayEmiInstallmentRequest.php`

### API Controllers Created
- `app/Http/Controllers/Api/V1/BankLoanApiController.php`
- `app/Http/Controllers/Api/V1/EmiInstallmentApiController.php`

### Routes Registered
- `routes/api.php` (Added Phase 2.7.3 Bank Loans & EMI routes)

### Automated Test Suite Created
- `tests/Feature/Api/V1/BankLoanApiTest.php`

### Documentation Updated
- `docs/modernization/MODULE_API_REACT_MIGRATION_MAP.md` (Module 7 updated to Complete)
- `docs/modernization/PHASE_2_7_3_BANK_LOANS_EMI_API_IMPLEMENTATION.md` (This document)

---

## 7. Automated Testing & Verification Results

All tests were executed against isolated in-memory SQLite (`:memory:`) with pre-flight safety checks verifying the driver and database name before execution. Zero writes or DDL statements were executed against the MySQL database.

### 7.1 Bank Loans & EMI Feature Suite
```
Command: php vendor/bin/phpunit tests/Feature/Api/V1/BankLoanApiTest.php
Result:  OK (23 tests, 154 assertions)
Execution Time: 00:02.694
```
**Test Coverage:**
- 401 Unauthenticated barrier across all 13 endpoints.
- 403 Non-admin identity barrier across all 13 endpoints.
- Bank loan creation with comma sanitization and audit trails.
- Store validation errors (required fields, negative amounts, missing shareholder).
- Search, filtering (bank name, status, shareholder), and pagination.
- Show endpoint with computed metrics (`total_interest`, `total_payable`, `total_paid`, `remaining_balance`, `total_savings`).
- Update loan fields and status guard.
- Deletion guard on bank loan with paid installments (`409 Conflict`).
- Cascade soft-deletion of pending installments upon loan deletion.
- Statement endpoint computation precision down to 2 decimals.
- Schedule generation with uneven cent division ($100,000 / 3) and exact sum reconciliation.
- Guard against schedule regeneration when paid installments exist (`409 Conflict`).
- Overwrite pending installments when `overwrite_pending = true`.
- Installments CRUD (list, store manual, show, update, delete).
- Deletion guard on paid installment (`409 Conflict`).
- Amount immutability guard on paid installment (`409 Conflict`).
- Payment transition (`pending` -> `paid`), audit metadata recording (`paid_at`, `received_by`).
- Idempotency & double-payment prevention.
- Automatic parent bank loan status transition to `closed` upon full repayment.
- Soft-deleted installment payment denial (`404 Not Found`).

### 7.2 Regression Verification Suite
```
Command: php vendor/bin/phpunit tests/Feature/Api/V1/WithdrawalApiTest.php
Result:  OK (35 tests, 150 assertions)

Command: php vendor/bin/phpunit tests/Feature/Api/V1/ShareholderCapitalApiTest.php
Result:  OK (30 tests, 194 assertions)

Command: php vendor/bin/phpunit
Result:  OK (187 tests, 970 assertions)
Execution Time: 00:42.969
```
**Zero regressions observed across the entire application.**

---

## 8. Known Limitations & Production Notes

1. **Storage Engine Parity in Production:**
   - In production MySQL, `bank_loans` and `emi_installments` remain MyISAM until Decision Gate 6 (Engine Conversion to InnoDB) is executed. The API guards against concurrency anomalies using atomic queries, but full table conversion to InnoDB is recommended during Phase 4.
2. **SMS Notification Integration:**
   - Automatic SMS alert dispatch upon EMI payment is decoupled and can be hooked into an event listener or background queue in Phase 2.8 without altering the core payment API.

---

## 9. Final Phase Status

**PHASE 2.7.3 STATUS: PASS (100% COMPLETE)**  
The Bank Loans & EMI API implementation satisfies all security, financial precision, architectural, testing, and documentation requirements.
