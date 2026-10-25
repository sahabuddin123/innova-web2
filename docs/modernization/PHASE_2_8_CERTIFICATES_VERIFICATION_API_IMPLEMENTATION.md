# Phase 2.8 — Certificates & Verification API Implementation Report

**Project:** INNOVA Computers BD Web System Modernization  
**Repository:** `sahabuddin123/innova-web2`  
**Working Directory:** `C:\wamp64\www\innova-web`  
**Branch:** `feature/phase-1-security-hardening`  
**Phase:** 2.8 — Certificates & Verification API Implementation  
**Status:** COMPLETE (PASS)  
**Date:** October 25, 2026  
**Architect:** Senior Software Architect, Security Specialist & QA Engineer  

---

## 1. Executive Summary

Phase 2.8 implements a production-grade, secure, and privacy-preserving REST API suite under `/api/v1/certificates/*` for **Certificate Generation, Management, and Public Authenticity Verification**.

The implementation unifies both institutional and government board certificate workflows:
1. **Institutional Certificates (INNOVA Computers BD - ICBD):** Stored in `certificates_icbd`, generating unique `ICBDxxxx` serial numbers, recording curriculum classroom theory, practical lab, and internship hours, delivering QR code verification links, and providing printable HTML and downloadable A4 landscape PDF formats via DomPDF.
2. **BTEB Board Certificates:** Stored in `certificates`, recording Bangladesh Technical Education Board registration numbers (`regNo`), board certificate numbers (`cno`), passing dates (`pDate`), and examination grades (`Result`).
3. **Student Eligibility Verification:** Validates student active status, enrollment records, course/batch associations, financial dues status, and duplicate issuance guards before permitting certificate generation.
4. **Public Verification & Strict Data Minimization:** Unauthenticated endpoint validating certificate authenticity, resolving both ICBD serial numbers and BTEB board certificate/registration numbers while strictly stripping candidate PII (phone numbers, NIDs, physical addresses, and guardian details) to protect trainee privacy.

All functionality was verified on isolated in-memory SQLite (`:memory:`) with zero writes to production MySQL (`icbd_web`), passing 21 dedicated tests (114 assertions) and 225 total application tests (1197 assertions) with 100% success.

---

## 2. Existing Functionality Reused

### 2.1 Database Models & Schema Integration
1. **`App\Models\Certificate_icbd`:**
   - Table `certificates_icbd` (capturing legacy 38 production records).
   - Added canonical lifecycle status constants (`STATUS_DELIVERED = 1`, `STATUS_PROCESSING = 2`, `STATUS_PRINTING_QUEUE = 3`, `STATUS_REVOKED = 4`).
   - Corrected `batch()` relationship from legacy `Branch::class` to `Batchs::class` (`batch_id` -> `batchs.id`).
   - Added `status_label`, `total_hours`, and `verification_url` accessors.
   - Refined `creating` boot hook with collision-resistant unique `certificate_no` auto-generation (`ICBD` + padded numeric sequence), evaluating soft-deleted records to guarantee sequence uniqueness.
2. **`App\Models\Certificates`:**
   - Table `certificates` (capturing legacy 128 production records).
   - Added `SoftDeletes` trait and status constants (`STATUS_ACTIVE = 1`, `STATUS_INACTIVE = 0`).
   - Added standard relationship aliases: `student()` (`StudentsInfo()`) and `session()` (`StudentsSessions()`).
3. **`App\Models\Students`:**
   - Added `certificatesIcbd()` and `btebCertificates()` Eloquent relationships.
   - Reused legacy registration flag logic: setting `IsRegister = 2` upon certificate issuance, and reverting to `IsRegister = 1` when certificates are revoked or deleted.

### 2.2 Template & Library Reuse
- **QR Code Generation (`simplesoftwareio/simple-qrcode: ^4.2`):**
  - Integrated QR code generation pointing to canonical verification URL (`/certifiocate-verify/{certificate_no}`).
  - Built-in fallback: because `ext-imagick` is not installed on standard Windows WAMP PHP runtimes, `QrCode::format('png')` is supplemented with native SVG data URI generation (`data:image/svg+xml;base64,...`), preventing 500 crashes across all environments.
- **PDF Generation (`barryvdh/laravel-dompdf: 1.0`):**
  - Reused printable certificate layout in `resources/views/admin/certificate_icbd/print.blade.php`.
  - Configured A4 landscape PDF rendering via `Barryvdh\DomPDF\Facade\Pdf`.

---

## 3. Implemented Endpoints & API Contract

### 3.1 Public Verification (Unauthenticated)

| HTTP Method | Route URI | Action | Description & Security |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/certificates/verify/{certificateNo?}` | `CertificateApiController@verify` | Public certificate verification. Resolves ICBD serial numbers (e.g. `ICBD0001`) and BTEB certificate/registration numbers. Enforces strict data minimization (excludes phone, NID, address). Supports query parameter `?certificate_no=...`. |

#### Verification Payload Contract (Sample):
```json
{
  "success": true,
  "message": "Certificate is authentic and valid.",
  "data": {
    "is_valid": true,
    "status": "Delivered",
    "certificate_type": "Institutional (ICBD)",
    "certificate_no": "ICBD0001",
    "student_name": "Md. Tanvir Hossain",
    "course_name": "Web & Software Development",
    "course_duration": "6 Months",
    "curriculum_hours": {
      "classroom_theory": 80,
      "practical_lab": 120,
      "internship": 40,
      "total_course_hours": 240
    },
    "result": "A+",
    "issue_date": "2026-06-30",
    "institution": {
      "name": "INNOVA Computers BD",
      "tagline": "Computer Training & Technical Development Institute",
      "website": "https://innovacomputersbd.com"
    },
    "verification_url": "http://localhost/certifiocate-verify/ICBD0001",
    "verified_at": "2026-10-25T12:35:00+06:00"
  }
}
```

### 3.2 Student Eligibility & Overview (Protected: `auth:sanctum`, `admin.identity`)

| HTTP Method | Route URI | Action | Description & Guards |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/certificates/reports/summary` | `CertificateApiController@summaryReport` | Aggregated report of ICBD and BTEB certificates across lifecycle statuses and courses. |
| `GET` | `/api/v1/certificates/eligibility/{studentId}` | `CertificateApiController@eligibility` | Evaluates student status, active enrollment, outstanding financial balance, existing certificates, and flags duplicate issuance blocks. |

### 3.3 Institutional (ICBD) Certificate Management (Protected: `auth:sanctum`, `admin.identity`)

| HTTP Method | Route URI | Action | Description & Guards |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/certificates` | `CertificateApiController@index` | Paginated listing with search (`search`), course (`course_id`), batch (`batch_id`), status (`is_active`), result (`result`), and date range filters. |
| `POST` | `/api/v1/certificates` | `CertificateApiController@store` | Issues new ICBD certificate. **Guards:** Blocks duplicate issuance for same student and course (`409 Conflict` with `DUPLICATE_CERTIFICATE_ISSUANCE`). Auto-generates unique `ICBDxxxx` serial number. Sets student `IsRegister = 2`. |
| `GET` | `/api/v1/certificates/{id}` | `CertificateApiController@show` | Retrieves certificate details with student, course, batch, QR code link, and PDF download URLs. |
| `PUT` | `/api/v1/certificates/{id}` | `CertificateApiController@update` | Updates certificate metadata, curriculum hours, receiver, and result. |
| `DELETE` | `/api/v1/certificates/{id}` | `CertificateApiController@destroy` | Soft-deletes certificate. Reverts student `IsRegister = 1` if no other active certificates remain. |
| `POST` | `/api/v1/certificates/{id}/status` | `CertificateApiController@updateStatus` | Transitions certificate status (`1` = Delivered, `2` = Processing, `3` = Printing Queue, `4` = Revoked). |
| `GET` | `/api/v1/certificates/{id}/qr` | `CertificateApiController@qr` | Returns verification URL, QR code SVG string, and SVG base64 data URI. |
| `GET` | `/api/v1/certificates/{id}/print` | `CertificateApiController@print` | Returns printable HTML certificate layout with embedded QR verification code. |
| `GET` | `/api/v1/certificates/{id}/pdf` | `CertificateApiController@pdf` | Generates and streams downloadable A4 landscape PDF certificate. |

### 3.4 BTEB Board Certificate Management (Protected: `auth:sanctum`, `admin.identity`)

| HTTP Method | Route URI | Action | Description & Guards |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/certificates/bteb` | `BtebCertificateApiController@index` | Paginated listing of BTEB board certificates with session, active, result, and search filters. |
| `POST` | `/api/v1/certificates/bteb` | `BtebCertificateApiController@store` | Records BTEB certificate. **Guards:** Blocks duplicate issuance for same student and session (`409 Conflict` with `DUPLICATE_BTEB_CERTIFICATE`). Sets student `IsRegister = 2`. |
| `GET` | `/api/v1/certificates/bteb/{id}` | `BtebCertificateApiController@show` | Retrieves BTEB certificate details with student and session relationships. |
| `PUT` | `/api/v1/certificates/bteb/{id}` | `BtebCertificateApiController@update` | Updates BTEB certificate registration number, serial, result, or delivery date. |
| `DELETE` | `/api/v1/certificates/bteb/{id}` | `BtebCertificateApiController@destroy` | Soft-deletes BTEB certificate. |

---

## 4. Security, Privacy & Certificate Integrity

1. **RBAC & Admin Guard:**
   - All management endpoints require Sanctum authentication and the `EnsureAdminIdentity` (`admin.identity`) middleware.
   - Non-admin tokens (e.g. students/users) receive `403 Forbidden` (`Forbidden: Admin access required`).
2. **Duplicate Issuance Prevention:**
   - Business rules prohibit issuing multiple active certificates to the same student for the same course or academic session.
   - ICBD: `Certificate_icbd::where('student_id', $sId)->where('course_id', $cId)->where('is_active', '!=', 4)->exists()` -> `409 Conflict` (`DUPLICATE_CERTIFICATE_ISSUANCE`).
   - BTEB: `Certificates::where('student_id', $sId)->where('session_id', $sessId)->where('IsActive', 1)->exists()` -> `409 Conflict` (`DUPLICATE_BTEB_CERTIFICATE`).
3. **Strict Data Minimization on Public Verification:**
   - The public verification route (`/api/v1/certificates/verify/{certificateNo}`) is open to verify authenticity by employers, institutions, or students.
   - **Exposed Data:** Student Name, Course Name, Duration, Curriculum Hours Breakdown, Grade/Result, Issue Date, Institution Details, Validity Status.
   - **Protected Data:** Phone numbers, parent/guardian phone numbers, National ID (NID), physical addresses, date of birth, blood group, course fees, discounts, and financial balance are **never** returned in the public payload.
4. **Collision-Resistant Unique Serial Generation:**
   - `Certificate_icbd` auto-generates sequential identifiers (`ICBD0001`, `ICBD0002`, ...) using a loop over `withTrashed()` records to ensure no duplicate serials can be generated even after deletions.
5. **Revocation & Invalidation Handling:**
   - Revoked (`is_active = 4`) and soft-deleted certificates return `is_valid: false` and `status: "Revoked"`, maintaining historical audit trails while clearly communicating invalidity to third-party verifiers.
   - Non-existent certificates return `404 Not Found` with `CERTIFICATE_NOT_FOUND`.

---

## 5. Automated Testing & Verification

All automated tests execute against an isolated in-memory SQLite database (`:memory:`), guaranteeing zero writes to production MySQL (`icbd_web`).

### 5.1 Dedicated Certificate Feature Test Suite
- **File:** `tests/Feature/Api/V1/CertificateApiTest.php`
- **Execution Command:**
  ```powershell
  php vendor/bin/phpunit tests/Feature/Api/V1/CertificateApiTest.php
  ```
- **Results:**
  ```text
  PHPUnit 9.6.29 by Sebastian Bergmann and contributors.

  .....................                                             21 / 21 (100%)

  Time: 00:03.499, Memory: 44.00 MB

  OK (21 tests, 114 assertions)
  ```

### 5.2 Test Coverage Details
1. `test_unauthenticated_requests_are_denied_for_protected_certificate_endpoints`: Validates 401 on 16 protected routes.
2. `test_non_admin_identity_is_forbidden_for_protected_certificate_endpoints`: Validates 403 when authenticated as a non-admin user.
3. `test_student_eligibility_assessment_for_eligible_student`: Verifies eligibility check passes for active, uncertified students.
4. `test_student_eligibility_assessment_detects_outstanding_dues_and_inactive_status`: Verifies financial dues warnings and inactive student blocks.
5. `test_can_issue_icbd_certificate_and_auto_generates_unique_certificate_no`: Tests certificate issuance, `ICBD0001` auto-generation, and `IsRegister = 2` update.
6. `test_duplicate_issuance_prevention_guards_against_same_student_and_course`: Confirms 409 Conflict rejection for duplicate course certificates.
7. `test_can_list_certificates_with_filters_search_and_pagination`: Tests pagination, course, status, and keyword search filters.
8. `test_can_view_certificate_details_with_appends`: Verifies `status_label`, `total_hours`, and URL appends.
9. `test_can_update_certificate_metadata`: Tests updating hours, result, and receiver.
10. `test_can_update_certificate_status`: Tests status transition to Revoked (4).
11. `test_can_delete_certificate_and_resets_student_registration_flag`: Tests soft-deletion and reverting `IsRegister` to 1.
12. `test_can_generate_qr_code_for_verification_url`: Tests QR code generation, data URI, and format.
13. `test_can_render_print_html_view`: Tests rendering the printable HTML certificate template.
14. `test_can_generate_and_download_pdf`: Tests A4 landscape DomPDF generation and download response.
15. `test_public_verification_for_valid_certificate_with_strict_data_minimization`: Verifies authenticity payload and asserts absence of private PII (phone, NID, address, balance).
16. `test_public_verification_for_revoked_certificate_returns_revoked_status`: Verifies `is_valid: false` and `status: "Revoked"` on cancelled certificates.
17. `test_public_verification_for_nonexistent_certificate_returns_404`: Confirms 404 response for invalid certificate numbers.
18. `test_can_record_list_update_and_delete_bteb_certificate`: Verifies full CRUD lifecycle for BTEB board certificates.
19. `test_duplicate_bteb_certificate_prevention_guards_against_same_student_and_session`: Confirms 409 Conflict on duplicate BTEB certificates.
20. `test_public_verification_resolves_bteb_certificate_by_cno_or_regNo`: Verifies public resolution of BTEB certificates via certificate or registration number.
21. `test_summary_report_computes_aggregated_statistics`: Tests aggregated reporting counts across ICBD and BTEB.

### 5.3 Full Application Regression Test Suite
- **Execution Command:**
  ```powershell
  php vendor/bin/phpunit
  ```
- **Results:**
  ```text
  PHPUnit 9.6.29 by Sebastian Bergmann and contributors.

  ...............................................................  63 / 225 ( 28%)
  ............................................................... 126 / 225 ( 56%)
  ............................................................... 189 / 225 ( 84%)
  ....................................                            225 / 225 (100%)

  Time: 00:42.065, Memory: 70.00 MB

  OK (225 tests, 1197 assertions)
  ```

---

## 6. Files Changed & Created

### 6.1 Eloquent Models
- `app/Models/Certificate_icbd.php`: Status constants, corrected `batch()` relation, appends, and collision-resistant `certificate_no` generator.
- `app/Models/Certificates.php`: Added `SoftDeletes`, status constants, and relationship aliases.
- `app/Models/Students.php`: Added `certificatesIcbd()` and `btebCertificates()` relationships.

### 6.2 Blade Templates
- `resources/views/admin/certificate_icbd/print.blade.php`: Added resilient QR code rendering with SVG fallback to avoid imagick dependency crashes.

### 6.3 Form Requests
- `app/Http/Requests/Api/V1/StoreCertificateIcbdRequest.php`
- `app/Http/Requests/Api/V1/UpdateCertificateIcbdRequest.php`
- `app/Http/Requests/Api/V1/UpdateCertificateStatusRequest.php`
- `app/Http/Requests/Api/V1/StoreBtebCertificateRequest.php`
- `app/Http/Requests/Api/V1/UpdateBtebCertificateRequest.php`

### 6.4 API Controllers
- `app/Http/Controllers/Api/V1/CertificateApiController.php`
- `app/Http/Controllers/Api/V1/BtebCertificateApiController.php`

### 6.5 Routes
- `routes/api.php`: Registered public verification endpoint and 14 protected management endpoints under `/api/v1/certificates/*`.

### 6.6 Automated Tests
- `tests/Feature/Api/V1/CertificateApiTest.php`

### 6.7 Documentation
- `docs/modernization/PHASE_2_8_CERTIFICATES_VERIFICATION_API_IMPLEMENTATION.md`
- `docs/modernization/MODULE_API_REACT_MIGRATION_MAP.md`

---

## 7. Known Limitations & Unresolved Issues

1. **Storage Engine Collation in Production MySQL:**
   - In production MySQL, `certificates_icbd` uses `latin1_swedish_ci` collation. Normalization to standard UTF-8 is planned for Phase 4 (PostgreSQL Data Migration).
2. **React Frontend Migration (Phase 3):**
   - The user-facing Blade views (`resources/views/admin/certificate/*` and `resources/views/admin/certificate_icbd/*`) remain fully active. Migration to React components is scheduled for Phase 3.

---

## 8. Final Implementation Status

**Status: PASS (100% COMPLETE)**

Phase 2.8 is fully implemented, verified, tested, and documented. All 21 dedicated tests pass with 0 errors or failures. The complete application regression suite (225 tests, 1197 assertions) passes with 100% success.
