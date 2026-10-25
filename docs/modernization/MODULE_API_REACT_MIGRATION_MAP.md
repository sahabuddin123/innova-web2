# MODULE API & REACT MIGRATION MAP
**Project:** INNOVA Computers BD Web System Modernization  
**Phase:** Phase 1 — Complete Current System Architecture Audit  
**Status:** COMPLETE (Planning & Mapping Baseline)  
**Date:** October 24, 2026  
**Auditor:** Senior Software Architect, Laravel Backend Architect, React Migration Specialist & Database Migration Planner  

---

## 1. Master Module Migration Matrix

The table below catalogs all 12 verified business modules, their current implementation status, their API readiness, frontend status, dependencies, recommended migration sequence, and key risk factors.

| Module | Existing Functionality | Backend/API Status | Blade/Frontend Status | Dependencies | Planned Migration Order | Key Risks |
| :--- | :--- | :--- | :--- | :--- | :---: | :--- |
| **1. Authentication & RBAC** | Multi-guard auth (`admin` & `web`), Spatie roles & permissions, password resets, rate limiting | **Partial**<br>(Dual session guards exist; API token guards defined in config; needs Sanctum / token endpoints) | **Not Ready**<br>(Blade forms with CSRF tokens; session flashes) | None (Foundational) | **Stage 1 (API)**<br>**Stage 2 (React)** | Token issuance security, session vs token coexistence during hybrid migration, Spatie permission synchronization. |
| **2. Academic & Course Mgmt** | Categories, Courses, Sessions (academic terms), Batches (seats/times), Instructors | **Complete**<br>(Academic REST APIs & read-only teacher endpoints verified) | **Complete**<br>(Courses & Batches React SPA interfaces migrated in Phase 3.3 under `/react-admin/*`) | Module 1 (RBAC) | **Stage 2 (API: Completed)**<br>**Stage 3 (React: Completed)** | Low risk. Clean relational model; provides foundational catalog data for all downstream modules. |
| **3. Student Lifecycle & Admissions** | Student admissions, enrollments, multi-state status tracking (Active/Drop), registrations, ID card generation | **Complete**<br>(Student REST APIs verified & covered by 25+ automated feature tests) | **Partial**<br>(Students Management React SPA migrated in Phase 3.4 under `/react-admin/students`; Admissions wizard deferred) | Module 1, Module 2 | **Stage 3 (API: Completed)**<br>**Stage 4 (React: In Progress)** | Multipart image upload handling, cascading status changes affecting batch seat allocations. |
| **4. Certificates & Verification** | BTEB & internal institute certificate generation, public QR code verification, serial tracking | **Complete**<br>(Phase 2.8 REST APIs fully implemented, tested & verified) | **Not Ready**<br>(Printable Blade certificates with CSS absolute positioning; scheduled for Phase 3) | Module 2, Module 3 | **Stage 4 (API: Completed)**<br>**Stage 5 (React)** | Latin1 collation on `certificate_icbd`, DomPDF A4 landscape download, public verification with strict data minimization (no student PII exposed), isolated SQLite test suite (21/21 tests passing). |
| **5. Fees Collection & Receipts** | Fee installment receipts, discount calculations, balance dues, payment histories, printable vouchers | **Not Ready**<br>(Complex accounting logic in `FeesTransectionsController`) | **Not Ready**<br>(Printable voucher Blade views, Bangla number converter) | Module 1, Module 2, Module 3 | **Stage 5 (API)**<br>**Stage 6 (React)** | Concurrency during receipt creation, unmigrated drift columns (`channel`, `trxID`), monetary `VARCHAR` precision. |
| **6. Institutional Accounting** | Incomes, expenses, categories, multi-branch ledger, daily/monthly cash balance reporting | **Not Ready**<br>(Heavily coupled to `ReportController` and raw SQL/queries) | **Not Ready**<br>(20+ report Blade templates, custom date filters, print views) | Module 1 (RBAC), Branch | **Stage 6 (API)**<br>**Stage 7 (React)** | Large dataset aggregation performance (12,000+ financial records), calculation parity with historical reports. |
| **7. Shareholders & Loans** | Partner equity, capital investments, dividend withdrawals, bank loans, amortized EMI repayments | **Complete**<br>(Phase 2.7.1, Phase 2.7.2, and Phase 2.7.3 REST APIs fully implemented, tested & verified) | **Not Ready**<br>(CRUD Blade views, EMI schedule tables; scheduled for Phase 3) | Module 1 (RBAC), Module 10 (SMS) | **Stage 7 (API: Completed)**<br>**Stage 8 (React)** | 4 reconciliation migrations created, MyISAM tables guarded via atomic query transitions and idempotency, SMS dispatch decoupled. |
| **8. Government / Asset Project** | Specialized grant training pipeline (trainee enrollment, questionnaires, feedback) | **Complete**<br>(Phase 2.7.4 ASSET / SEIP Projects REST APIs fully implemented, tested & verified) | **Not Ready**<br>(Specialized intake forms in Blade; scheduled for Phase 3) | Module 1 (RBAC) | **Stage 8 (API: Completed)**<br>**Stage 9 (React)** | 13 canonical status lifecycles, 5-stage call milestones, duplicate enrollment guards (NID/mobile), course deletion integrity protection, isolated in-memory SQLite automated test suite (17/17 tests passing). |
| **9. Payment Gateway (bKash)** | Online fee checkout, token grant, payment creation, execution, query, refund, webhook callbacks | **Partial**<br>(Structured API integration in `BkashController`; web redirect flow) | **Not Ready**<br>(Redirects to bKash portal, Blade success/failure landing) | Module 5 (Fees) | **Stage 5 (API)**<br>**Stage 6 (React)** | External gateway timeout handling, redirect URL compatibility with decoupled Single Page Application (SPA). |
| **10. Communication (SMS) & Settings** | Single and bulk transactional SMS to students and trainees, approved templates, audit logs, and public/admin system settings | **Complete**<br>(Phase 2.9 REST APIs fully implemented, tested & verified) | **Not Ready**<br>(Blade forms with recipient selector; scheduled for Phase 3) | Module 1, Module 3, Module 7 | **Stage 3 (API: Completed)**<br>**Stage 4 (React)** | Gateway credit exhaustion, international phone formatting, rate limiting to prevent spam, sensitive credential masking, isolated SQLite test suite (29/29 tests passing). |
| **11. Student Self-Service Portal** | Authenticated student dashboard: enrolled batch details, fees payment history, online dues payment | **Not Ready**<br>(Web controller rendering user views under `users/` namespace) | **Not Ready**<br>(Bootstrap user dashboard Blade templates) | Module 1, Module 3, Module 5, Module 9 | **Stage 5 (API)**<br>**Stage 1 (React)** | Multi-guard token authentication, isolating student permissions from administrative scopes. |
| **12. Public Website CMS & Lead Capture** | Homepage, course catalog, seminars, blogs, team members, contact forms, ReCaptcha | **Not Ready**<br>(`FrontendController` returning Blade views) | **Not Ready**<br>(56 public Blade views, dynamic menus, sliders, modals) | Module 2 (Courses) | **Stage 2 (API)**<br>**Stage 1 (React)** | SEO metadata preservation, ReCaptcha verification via REST API, public image asset delivery. |

---

## 2. Recommended API Development Order (Phase 2)

Phase 2 will develop clean, versioned REST endpoints (`/api/v1/*`) using the existing Laravel backend and MySQL database without altering the underlying database schema.

```
[Phase 2.1: Auth & Core Infrastructure]
   └── Token authentication (Sanctum/JWT), admin & student login, profile, permissions
       │
[Phase 2.2: Academic & Course Catalogs (Read/Write)]
   └── Course categories, courses, academic sessions, batches, teachers
       │
[Phase 2.3: Public CMS & Lead Generation]
   └── Public course listings, seminar registrations, contact inquiries, ReCaptcha verification
       │
[Phase 2.4: Student Lifecycle & Management]
   └── Student admission, profile updates, batch enrollments, document uploads, SMS alerts
       │
[Phase 2.5: Fees Collection & bKash Payment API]
   └── Fee calculation, cash receipt recording, bKash initiate/execute endpoints, student invoices
       │
[Phase 2.6: Institutional Accounting & Reporting]
   └── Incomes, expenses, categories, branches, daily cash reconciliation, ledger summaries
       │
[Phase 2.7: Shareholders, Loans & Asset Project]
   └── Capital ledger, dividend records, bank loans, EMI tracking, grant trainee records
```

### Rationale for this Sequence:
1. **Security First:** Establishing token authentication and authorization primitives upfront ensures every subsequent endpoint is properly protected.
2. **Entity Foundation:** Academic and course data are prerequisites for student admissions and fee receipts.
3. **Financial Protection:** Isolating financial calculations (fees, bKash, ledgers) until student and batch entities are fully stabilized guarantees data integrity.

---

## 3. Recommended Blade-to-React Conversion Order (Phase 3)

Phase 3 introduces a modern React (TypeScript + Vite) frontend, consuming the validated `/api/v1/*` endpoints while coexisting with the existing Laravel application.

```
+--------------------------------------------------------------------------------------------------+
| STEP 1: Public Website & Student Portal (Lowest Risk, Immediate User Impact)                      |
| - Marketing pages, Course Explorer, Blog, Seminar registration, Contact forms                    |
| - Student Self-Service Portal (Dashboard, Invoice review, bKash fee checkout)                    |
+--------------------------------------------------------------------------------------------------+
                                               │
                                               ▼
+--------------------------------------------------------------------------------------------------+
| STEP 2: Academic & Student Administration (Core Daily Operations)                                |
| - Course, Batch, and Session management                                                          |
| - Student admission, batch enrollment, ID card generation, SMS messaging                         |
+--------------------------------------------------------------------------------------------------+
                                               │
                                               ▼
+--------------------------------------------------------------------------------------------------+
| STEP 3: Financial & Accounting Management (High Complexity Financial Ledgers)                    |
| - Fee collection counter, discount authorization, receipt printing                               |
| - Income / Expense entry, multi-branch daily cash reports, printable PDF summaries              |
+--------------------------------------------------------------------------------------------------+
                                               │
                                               ▼
+--------------------------------------------------------------------------------------------------+
| STEP 4: Executive Administration & Partner Portals                                               |
| - Shareholder capital, dividend distributions, bank loans, EMI schedules                         |
| - Role and permission management, system settings                                                |
+--------------------------------------------------------------------------------------------------+
```

### Rationale for this Sequence:
- **Zero Disruption to Daily Operations:** Replacing the public website and student self-service screens first allows the internal administrative staff to continue processing daily tuition payments and student admissions using the familiar Blade admin portal while React stabilizes.
- **Progressive Modernization:** Avoids a "big bang" cutover. Front-office staff can pilot new screens while back-office staff remain uninterrupted.

---

## 4. High-Level Final PostgreSQL Migration Approach (Phase 4)

The database migration from MySQL 8.0 to PostgreSQL 16 will take place only **after** the API layer and frontend are validated and stable.

### Strategy: Clean-Slate Reconstructed Schema with Data ETL

```
[Existing MySQL 8.0 Database] (Production Source of Truth)
            │
            ▼
[Data Profiling & Extraction Pipeline]
  - Extract data to intermediate formats (JSON / CSV / SQL dump)
  - Sanitize 27 date anomaly records (century typos e.g. 0202 -> 2022)
  - Normalize monetary columns: VARCHAR(191) -> NUMERIC(15,2)
  - Clean discount string ('50.' -> 50.00)
            │
            ▼
[Clean PostgreSQL 16 Reconstructed Schema]
  - Clean Laravel migrations natively supporting PostgreSQL data types
  - Unified UTF-8 collation across all tables (resolving latin1_swedish_ci)
  - Standard PostgreSQL sequences (`SERIAL` / `BIGSERIAL`) replacing MySQL auto-increments
  - Foreign key constraints enforced across all relational tables
            │
            ▼
[Automated Data Ingestion & Integrity Verification]
  - Load sanitized records with foreign keys temporarily deferred
  - Re-enable constraints and re-seed sequence current values (`setval()`)
  - Run checksum and row-count verification (100% record parity target)
  - Execute end-to-end API test suites against PostgreSQL
```

---

## 5. Main Dependencies to Resolve Before Each Stage

### Prerequisites for Phase 2 (Backend APIs on MySQL):
- [ ] Implement API authentication token mechanism (e.g. Laravel Sanctum) supporting both `Admin` and `User` models.
- [ ] Standardize API error formatting and HTTP status codes across all endpoints using `ApiController`.
- [ ] Establish automated Feature testing for every newly authored API route.

### Prerequisites for Phase 3 (React + TypeScript):
- [x] Initialize Vite 8 + React 18 + TypeScript build environment (`vite.config.ts`, `tsconfig.json`) in parallel with Laravel Mix 4 (Phase 3.0).
- [x] Establish typed API client and Axios interceptors for CSRF / Bearer tokens and dynamic base URL resolution (`resources/js/api/client.ts`, `resources/js/utils/env.ts`) (Phase 3.0).
- [x] Establish Design System CSS tokens, foundational utilities, and isolated verification mount (`/react-health`) with automated tests passing (Phase 3.0).
- [x] Establish typed React Authentication & Access Foundation (`authService.ts`, `AuthContext.tsx`, `useAuth.ts`, `AuthBoundary.tsx`) consuming existing Sanctum multi-guard APIs with token/storage hardening (Phase 3.1).
- [x] Implement Courses and Batches React SPA components and pages mounted under `/react-admin/*` with full search, filters, CRUD modals, and permission checks (Phase 3.3).
- [x] Implement Students Management React SPA interface mounted under `/react-admin/students` with search, course/batch filters, lifecycle status tabs, profile view, CRUD modals, and deletion protection (Phase 3.4).
- [ ] Implement remaining module-by-module React SPA components (Admissions, Fees, Accounting, Certificates, SMS) (Scheduled for Phase 3.5+).

### Prerequisites for Phase 4 (PostgreSQL Migration):
- [ ] Complete approval of Owner Decision Gates 1 through 8.
- [ ] Perform disposable-database mock restoration and validation.
- [ ] Finalize data cleaning scripts for monetary types and date typos.

---

## 6. List of Decisions Requiring Owner Approval

Before executing destructive changes or irreversible data transformations in subsequent phases, the following formal owner decision gates must be approved:

1. **Decision Gate 1: Unmigrated Tables Resolution**
   - *Status:* PENDING OWNER DECISION
   - *Scope:* Formalize migrations for `bank_loans`, `dividend_withdraws`, `emi_installments`, and `investment_withdraws`.
2. **Decision Gate 2: Monetary Column Normalization**
   - *Status:* PENDING OWNER DECISION
   - *Scope:* Convert `VARCHAR(191)` monetary columns to `NUMERIC(15,2)`.
3. **Decision Gate 3: Date Anomaly Remediation**
   - *Status:* PENDING OWNER DECISION
   - *Scope:* Approval of remediation rules for 27 date anomaly records (century typos e.g. 0202, 2029, zero dates).
4. **Decision Gate 4: Malformed Discount Record Treatment**
   - *Status:* PENDING OWNER DECISION
   - *Scope:* Convert `students.discount = '50.'` to numeric `50.00`.
5. **Decision Gate 5: Duplicate Migration Class Resolution**
   - *Status:* PENDING OWNER DECISION
   - *Scope:* Reconstruct migration history into an ordered, deduplicated migration suite.
6. **Decision Gate 6: Storage Engine & Collation Harmonization**
   - *Status:* PENDING OWNER DECISION
   - *Scope:* Convert 14 MyISAM tables and 2 `latin1` tables to InnoDB / PostgreSQL UTF-8.
7. **Decision Gate 7: Academic Sessions vs HTTP Sessions Table Collision**
   - *Status:* PENDING OWNER DECISION
   - *Scope:* Retain existing academic table as `sessions` or rename to `academic_sessions` to prevent Laravel database session driver conflict.
8. **Decision Gate 8: Disposable Database Validation Requirement**
   - *Status:* PENDING OWNER DECISION
   - *Scope:* Enforce pre-migration backup restore on a disposable database before touching production data.
