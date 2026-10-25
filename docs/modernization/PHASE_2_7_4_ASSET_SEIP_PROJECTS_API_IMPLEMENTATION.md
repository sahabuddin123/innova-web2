# Phase 2.7.4 — ASSET / SEIP Projects API Implementation Report

**Project:** INNOVA Computers BD Web System Modernization  
**Repository:** `sahabuddin123/innova-web2`  
**Working Directory:** `C:\wamp64\www\innova-web`  
**Branch:** `feature/phase-1-security-hardening`  
**Phase:** 2.7.4 — ASSET / SEIP Projects API Implementation  
**Status:** COMPLETE (PASS)  
**Date:** October 25, 2026  
**Architect:** Senior Software Architect, Security Specialist & QA Engineer  

---

## 1. Executive Summary

Phase 2.7.4 implements a production-ready, secure, and backward-compatible REST API suite under `/api/v1/projects/*` for the **ASSET (Accelerating and Strengthening Skills for Economic Transformation)** and **SEIP (Skills for Employment Investment Program)** government-funded skill development initiatives.

The implementation preserves the existing data structures, models, and business logic while introducing modern REST interfaces with Sanctum authentication, administrative RBAC enforcement, trainee qualification lifecycle transitions, multi-step interview feedback tracking, duplicate enrollment prevention, course relational integrity deletion protection, decoupled external communications (SMS/WhatsApp notifications), and aggregated project reporting.

All endpoints were validated using PHPUnit on isolated in-memory SQLite (`:memory:`), confirming zero writes to production MySQL (`icbd_web`), passing 17 dedicated tests (113 assertions) and 204 total application tests (1083 assertions) with 100% success.

---

## 2. Existing Functionality Reused & Workflow Distinctions

### 2.1 ASSET vs SEIP Project Distinctions
- **SEIP (Skills for Employment Investment Program):** Historical national workforce initiative under the Ministry of Finance / ADB.
- **ASSET (Accelerating and Strengthening Skills for Economic Transformation):** Successor government training project actively operated by INNOVA Computers BD.
- **Operational Reality:** The production database manages government-funded candidates and courses through the `asset_*` schema entities (`asset_courses`, `asset_registrations`, and `asset_registration_feedbacks`).
- **Showcase / Project Showcase Entities:** The `assets` table provides project showcase records, portfolios, and organizational assets.

### 2.2 Eloquent Models & Schema Reused
1. **`App\Models\AssetRegistration`:**
   - Primary trainee intake entity holding candidate demographic data (`full_name`, `father_name`, `mother_name`, `mobile`, `whatsapp`, `email`, `religion`, `gender`, `dob`, `nid`, `education`, `employment_status`, `address`, `board`, `subject`, `passing_year`, `photo`, `registration_status`, `call_type`).
   - Extended with `ALLOWED_STATUSES` (13 canonical statuses: `Pending`, `Contacted`, `Converted`, `Follow Up`, `Follow Up Later`, `Lost`, `Not Qualified`, `Qualified`, `Service`, `Wrong Number`, `Not Interested`, `Canceled`, `Don't Call Again`).
   - Extended with `ALLOWED_CALL_TYPES` (5 canonical screening milestones: `1st call`, `2nd call`, `3rd call`, `4th call`, `5th call`).
   - Added `assetCourse()` Eloquent relationship (`course` -> `name`).

2. **`App\Models\AssetCourse`:**
   - Training curriculum catalog (`name`, `slug`, `short_description`, `level`, `icon`, `button_color`, `course_outline`, `photo`, `status`).
   - Reused with `trainees()` relationship (`hasMany(AssetRegistration::class, 'course', 'name')`).

3. **`App\Models\AssetRegistrationFeedback`:**
   - Multi-step interview and trainee evaluation notes (`asset_registration_id`, `feedback_step`, `feedback`).
   - Reused with `registration()` relationship.

4. **`App\Models\Asset`:**
   - Project entity showcase records (`title`, `slug`, `client`, `service`, `duration`, `cost`, `description`, `details`, `image`, `video`, `status`).

5. **`App\Models\AssetStudent`:**
   - Bridge model linking asset trainees to internal student admission IDs.

---

## 3. Implemented Endpoints & API Contract

All endpoints are registered under `routes/api.php` under `/api/v1/projects/*`:

### 3.1 Public Candidate Intake

| HTTP Method | Route URI | Controller Action | Description & Guards |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/v1/projects/public-register` | `AssetTraineeApiController@publicRegister` | Public candidate self-registration. Validates fields, sets initial `registration_status = 'Pending'`, and prevents duplicate enrollment on NID/mobile for the same course (`409 Conflict`). |

### 3.2 Program Reporting & Metrics

| HTTP Method | Route URI | Controller Action | Description & Guards |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/projects/reports/summary` | `AssetProjectApiController@summaryReport` | Returns aggregated metrics: total registrations, breakdown across 13 qualification statuses, breakdown across 5 call milestones, and course-wise trainee distribution. Protected by `auth:sanctum` & `admin.identity`. |

### 3.3 Project Showcase Entities API

| HTTP Method | Route URI | Controller Action | Description & Guards |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/projects/assets` | `AssetProjectApiController@index` | Paginated listing with search (`search`), status filter (`status`), and sorting. Protected by `auth:sanctum` & `admin.identity`. |
| `POST` | `/api/v1/projects/assets` | `AssetProjectApiController@store` | Creates a new project entity with auto-generated unique slug. Protected by `auth:sanctum` & `admin.identity`. |
| `GET` | `/api/v1/projects/assets/{id}` | `AssetProjectApiController@show` | Returns project entity details. Protected by `auth:sanctum` & `admin.identity`. |
| `PUT` | `/api/v1/projects/assets/{id}` | `AssetProjectApiController@update` | Updates project entity fields with slug uniqueness validation. Protected by `auth:sanctum` & `admin.identity`. |
| `DELETE` | `/api/v1/projects/assets/{id}` | `AssetProjectApiController@destroy` | Deletes project showcase entity. Protected by `auth:sanctum` & `admin.identity`. |

### 3.4 Project Training Courses API

| HTTP Method | Route URI | Controller Action | Description & Guards |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/projects/courses` | `AssetCourseApiController@index` | Lists courses with `trainees_count` aggregated metric, status filter, and keyword search. Protected by `auth:sanctum` & `admin.identity`. |
| `POST` | `/api/v1/projects/courses` | `AssetCourseApiController@store` | Creates a new training course with auto-slug generation. Protected by `auth:sanctum` & `admin.identity`. |
| `GET` | `/api/v1/projects/courses/{id}` | `AssetCourseApiController@show` | Returns course details, outline, and associated trainee summary count. Protected by `auth:sanctum` & `admin.identity`. |
| `PUT` | `/api/v1/projects/courses/{id}` | `AssetCourseApiController@update` | Updates course attributes. Protected by `auth:sanctum` & `admin.identity`. |
| `DELETE` | `/api/v1/projects/courses/{id}` | `AssetCourseApiController@destroy` | **Integrity Guard:** Checks for existing trainee registrations referencing the course name. Rejects deletion with `409 Conflict` (`COURSE_HAS_ACTIVE_REGISTRATIONS`) if active trainees exist. |

### 3.5 Trainee Management & Lifecycle API

| HTTP Method | Route URI | Controller Action | Description & Guards |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/projects/trainees` | `AssetTraineeApiController@index` | Paginated listing with multi-field filters (`course`, `registration_status`, `call_type`, `gender`, `employment_status`, `search` by name, mobile, NID). Protected by `auth:sanctum` & `admin.identity`. |
| `POST` | `/api/v1/projects/trainees` | `AssetTraineeApiController@store` | Admin manual trainee intake. Enforces duplicate enrollment prevention (`409 Conflict`). Protected by `auth:sanctum` & `admin.identity`. |
| `GET` | `/api/v1/projects/trainees/{id}` | `AssetTraineeApiController@show` | Retrieves trainee profile with feedbacks and course details. Protected by `auth:sanctum` & `admin.identity`. |
| `PUT` | `/api/v1/projects/trainees/{id}` | `AssetTraineeApiController@update` | Updates trainee demographics and educational qualifications. Protected by `auth:sanctum` & `admin.identity`. |
| `DELETE` | `/api/v1/projects/trainees/{id}` | `AssetTraineeApiController@destroy` | Deletes trainee application record and cascades deletion of interview feedbacks. Protected by `auth:sanctum` & `admin.identity`. |
| `POST` | `/api/v1/projects/trainees/{id}/status` | `AssetTraineeApiController@updateStatus` | Transitions candidate lifecycle status across 13 canonical states. When transitioning to `Qualified`, triggers WhatsApp SMS dispatch in a failure-resilient `try/catch` wrapper. Protected by `auth:sanctum` & `admin.identity`. |
| `POST` | `/api/v1/projects/trainees/{id}/call-type` | `AssetTraineeApiController@updateCallType` | Updates screening milestone across 5 call stages (`1st call` to `5th call`). Protected by `auth:sanctum` & `admin.identity`. |
| `GET` | `/api/v1/projects/trainees/{id}/feedbacks` | `AssetTraineeApiController@getFeedbacks` | Retrieves all evaluation feedback steps (1 to 5) for candidate. Protected by `auth:sanctum` & `admin.identity`. |
| `POST` | `/api/v1/projects/trainees/{id}/feedbacks` | `AssetTraineeApiController@storeFeedback` | Upserts interview feedback note for a given step (`1` to `5`). Protected by `auth:sanctum` & `admin.identity`. |

---

## 4. Security, Authorization & Data Integrity Safeguards

1. **RBAC & Authentication Barrier:**
   - All management endpoints are protected by `['auth:sanctum', 'admin.identity']`.
   - Unauthenticated requests receive `401 Unauthorized`.
   - Authenticated non-admin identities (e.g. standard `users` / students) receive `403 Forbidden` (`FORBIDDEN_ADMIN_ONLY`).
   - The public candidate intake endpoint (`/api/v1/projects/public-register`) is intentionally accessible without credentials to support public website admissions.

2. **Duplicate Enrollment Prevention:**
   - Business rules prohibit duplicate trainee enrollment for the same course.
   - The API verifies whether an existing candidate record with the same NID or mobile number already exists for that course.
   - Violations are rejected with `409 Conflict` and error code `DUPLICATE_TRAINEE_ENROLLMENT`.

3. **Referential Integrity on Course Deletion:**
   - A training course cannot be deleted if candidate applications reference that course name.
   - The deletion handler executes a pre-check query against `asset_registrations` and returns `409 Conflict` (`COURSE_HAS_ACTIVE_REGISTRATIONS`) if active trainees are associated.

4. **External Communications Decoupling:**
   - Trainee qualification triggers an SMS/WhatsApp notification (`App\Helpers\Helper::sms()`).
   - External gateway communications are enclosed in a localized `try/catch` block, ensuring network latency or gateway credit exhaustion never rolls back or crashes the candidate status transition.

---

## 5. Automated Testing & Verification

All automated tests execute against an isolated in-memory SQLite database (`:memory:`), ensuring zero writes to the production MySQL database (`icbd_web`).

### 5.1 Dedicated Feature Test Suite
- **File:** `tests/Feature/Api/V1/AssetProjectApiTest.php`
- **Execution Command:**
  ```powershell
  php vendor/bin/phpunit tests/Feature/Api/V1/AssetProjectApiTest.php
  ```
- **Results:**
  ```text
  PHPUnit 9.6.29 by Sebastian Bergmann and contributors.

  .................                                                 17 / 17 (100%)

  Time: 00:02.703, Memory: 34.00 MB

  OK (17 tests, 113 assertions)
  ```

### 5.2 Test Coverage Details
1. `test_unauthenticated_requests_are_denied_for_protected_endpoints`: Confirms 401 on protected routes without tokens.
2. `test_non_admin_identity_is_forbidden_for_protected_endpoints`: Confirms 403 on protected routes when using non-admin tokens.
3. `test_can_create_project_entity`: Verifies project creation and auto-slug generation.
4. `test_can_list_show_update_and_delete_project_entity`: Complete CRUD lifecycle verification for project showcase entities.
5. `test_can_list_courses_with_trainees_count_and_filters`: Validates course listing, search filters, and trainee count aggregation.
6. `test_can_create_and_update_course_with_auto_slug`: Validates course creation and slug collision handling.
7. `test_cannot_delete_course_with_active_trainee_registrations`: Confirms 409 Conflict guard preventing deletion of courses with active trainees.
8. `test_can_delete_course_without_active_trainee_registrations`: Confirms successful course deletion when no trainees are enrolled.
9. `test_can_enroll_trainee_via_admin_endpoint`: Validates administrative trainee enrollment.
10. `test_public_online_registration_endpoint_works_without_authentication`: Confirms public intake endpoint functions without authentication.
11. `test_duplicate_enrollment_prevention_guards_against_same_nid_or_mobile_for_course`: Confirms 409 Conflict rejection for duplicate NID or mobile for the same course.
12. `test_can_filter_and_search_trainees`: Tests multi-criteria search by name, NID, mobile, status, and course.
13. `test_can_transition_trainee_qualification_status`: Verifies valid lifecycle transition and WhatsApp SMS notification trigger.
14. `test_invalid_status_transition_is_rejected`: Confirms 422 validation rejection when supplying an invalid status.
15. `test_can_update_call_follow_up_milestone`: Tests screening call stage updates (`1st call` through `5th call`).
16. `test_can_store_and_retrieve_multistep_assessment_feedbacks`: Validates step-by-step interview feedback upserts (steps 1 through 5).
17. `test_program_summary_report_computes_aggregated_statistics`: Verifies aggregated program reporting metrics across statuses, call types, and courses.

### 5.3 Full Application Regression Test Suite
- **Execution Command:**
  ```powershell
  php vendor/bin/phpunit
  ```
- **Results:**
  ```text
  PHPUnit 9.6.29 by Sebastian Bergmann and contributors.

  ...............................................................  63 / 204 ( 30%)
  ............................................................... 126 / 204 ( 61%)
  ............................................................... 189 / 204 ( 92%)
  ...............                                                 204 / 204 (100%)

  Time: 00:25.848, Memory: 58.00 MB

  OK (204 tests, 1083 assertions)
  ```

---

## 6. Files Changed & Created

### 6.1 Eloquent Models
- `app/Models/AssetRegistration.php`: Added status and call type constants, and `assetCourse()` relationship.

### 6.2 Form Requests
- `app/Http/Requests/Api/V1/StoreAssetProjectRequest.php`
- `app/Http/Requests/Api/V1/UpdateAssetProjectRequest.php`
- `app/Http/Requests/Api/V1/StoreAssetCourseRequest.php`
- `app/Http/Requests/Api/V1/UpdateAssetCourseRequest.php`
- `app/Http/Requests/Api/V1/StoreAssetTraineeRequest.php`
- `app/Http/Requests/Api/V1/UpdateAssetTraineeRequest.php`
- `app/Http/Requests/Api/V1/UpdateTraineeStatusRequest.php`
- `app/Http/Requests/Api/V1/UpdateTraineeCallTypeRequest.php`
- `app/Http/Requests/Api/V1/StoreTraineeFeedbackRequest.php`

### 6.3 API Controllers
- `app/Http/Controllers/Api/V1/AssetProjectApiController.php`
- `app/Http/Controllers/Api/V1/AssetCourseApiController.php`
- `app/Http/Controllers/Api/V1/AssetTraineeApiController.php`

### 6.4 Routes
- `routes/api.php`: Registered public and protected `/api/v1/projects/*` endpoints.

### 6.5 Automated Tests
- `tests/Feature/Api/V1/AssetProjectApiTest.php`

### 6.6 Documentation
- `docs/modernization/PHASE_2_7_4_ASSET_SEIP_PROJECTS_API_IMPLEMENTATION.md`
- `docs/modernization/MODULE_API_REACT_MIGRATION_MAP.md`

---

## 7. Known Limitations & Unresolved Issues

1. **Production Table Engine:**
   - In production MySQL, `asset_courses`, `asset_registrations`, and `asset_registration_feedbacks` use MyISAM. Foreign key constraints are not enforced at the database storage engine layer. The API compensates by enforcing application-level relational checks (e.g., course deletion prevention and feedback cascade cleanup).
2. **React Frontend Conversion (Phase 3):**
   - The user-facing Blade views (`resources/views/admin/asset/*`) remain active and functional. React frontend conversion will be handled in Phase 3.
3. **Database Migration Standardization (Phase 4):**
   - Migration normalization (converting MyISAM tables to InnoDB / PostgreSQL UTF-8) is scheduled under Phase 4 data migration.

---

## 8. Final Implementation Status

**Status: PASS (100% COMPLETE)**

Phase 2.7.4 is fully implemented, verified, tested, and documented. All 17 dedicated tests pass without errors or warnings. Full regression test suite of 204 tests passes with 100% success.
