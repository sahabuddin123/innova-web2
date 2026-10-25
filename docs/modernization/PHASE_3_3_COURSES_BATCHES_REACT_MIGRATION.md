# Phase 3.3 — Courses & Batches React Migration

**Project:** Innova Web  
**Repository:** `https://github.com/sahabuddin123/innova-web2`  
**Local Path:** `C:\wamp64\www\innova-web`  
**Branch:** `feature/phase-1-security-hardening`  
**Previous Reported Commit:** `bb4f72bd50ee83e6bef4305df83d08f1e2680744`  
**Phase Status:** COMPLETE  

---

## 1. Executive Summary & Strict Scope Compliance

Phase 3.3 executes the focused migration of the **Courses** and **Batches** administrative user interfaces from legacy Blade templates to the modern React application mounted under `/react-admin/*`.

### Strict Scope Guarantees:
1. **Incremental Hybrid Migration:** The production Blade administration routes (`/admin/courses/*`, `/admin/batchs/*`, etc.) and all legacy Blade views remain 100% active, untouched, and fully operational without redirects or breaking changes.
2. **Strict Module Boundary:** Only Courses and Batches have been migrated. No other business module—including Students, Admissions, Fees, Accounting, Certificates, Assets/Trainees, SMS, or System Settings—was migrated in this phase.
3. **No Weakening of Security or Authorization:** Laravel remains the authoritative security boundary. Spatie RBAC permissions (`course-list`, `course-create`, `course-edit`, `course-delete`, `batch-list`, `batch-create`, `batch-edit`, `batch-delete`) and multi-guard Sanctum tokens are strictly verified on all API requests. Client-side navigation toggles serve solely for progressive disclosure.
4. **Zero Production Database Mutation:** All test runs and validation suites executed strictly against isolated in-memory SQLite (`:memory:`); zero modifications or destructive DDL queries occurred on MySQL.
5. **No Simulated Writes:** All create, update, and delete actions interact directly with validated Laravel REST APIs and surface server-side validation and business constraint errors.

---

## 2. Existing Workflow & API Readiness Findings

### Existing Blade Workflows
- **Courses Blade (`CoursesController.php` & `backend/courses/`):**
  - Managed under `/admin/courses` with views: `index.blade.php`, `create.blade.php`, `edit.blade.php`, and `view.blade.php`.
  - Schema (`courses` table): `name`, `coursefee`, `category_id`, `shortTitle`, `courseduration`, `lacture`, `totalmoduls`, `totalproject`, `description`, `overview`, `curriculum`, `software`, `trainee`, `jobPlacement`, `jobPosition`, `IsActive`, `featured`, `IsCourse`, `Discount`, `Instructor`.
  - Relation: `CourseCategory` belongs-to `coursecategories` table (`CourseCategoris`).
  - Delete safety: Protected against deletion if active student admissions exist (`StudentInfo()->count() > 0`).

- **Batches Blade (`BatchsController.php` & `backend/batchs/`):**
  - Managed under `/admin/batchs` with views: `index.blade.php`, `create.blade.php`, `edit.blade.php`.
  - Schema (`batchses` table): `batchnum`, `totalseat`, `stuff_id` (foreign key to `teachers.id`), `startdate`, `enddate`, `day1`..`day7` (flags), `schedule1`..`schedule7` (time strings), `IsActive` (0 = Postponed, 1 = Running, 2 = Upcoming, 3 = Completed).
  - Relation: `teacher` belongs-to `teachers` table (`stuff_id` -> `id`).
  - Delete safety: Protected against deletion if active student enrollments exist (`StudentInfo()->count() > 0`).

### API Readiness & Reused Endpoints
Before creating frontend interfaces, the existing REST endpoints under `/api/v1/academic/*` were verified:

| Endpoint | Method | Purpose | Status |
|---|---|---|---|
| `/api/v1/academic/courses` | `GET` | List courses with pagination, search, category, active, & featured filters | Reused (Existing) |
| `/api/v1/academic/courses/{id}` | `GET` | Retrieve full course details with category relation | Reused (Existing) |
| `/api/v1/academic/courses` | `POST` | Create course (validated via `StoreCourseRequest`) | Reused (Existing) |
| `/api/v1/academic/courses/{id}` | `PUT` | Update course (validated via `UpdateCourseRequest`) | Reused (Existing) |
| `/api/v1/academic/courses/{id}` | `DELETE` | Soft-delete course (enforces student enrollment constraint) | Reused (Existing) |
| `/api/v1/academic/categories` | `GET` | List active course categories for dropdowns | Reused (Existing) |
| `/api/v1/academic/batches` | `GET` | List batches with pagination, search, and status filters | Reused (Existing) |
| `/api/v1/academic/batches/{id}` | `GET` | Retrieve single batch details | Reused (Existing) |
| `/api/v1/academic/batches` | `POST` | Create batch (validated via `StoreBatchRequest`) | Reused (Existing) |
| `/api/v1/academic/batches/{id}` | `PUT` | Update batch (validated via `UpdateBatchRequest`) | Reused (Existing) |
| `/api/v1/academic/batches/{id}` | `DELETE` | Soft-delete batch (enforces student enrollment constraint) | Reused (Existing) |
| `/api/v1/academic/teachers` | `GET` | List active instructors for batch teacher assignment | Added (Read-Only) |

### Minimum Backend Additions
To populate the instructor assignment dropdown in batch forms:
1. Created `app/Http/Controllers/Api/V1/TeacherApiController.php` providing a lightweight, read-only endpoint `GET /api/v1/academic/teachers`.
2. Added route `GET teachers` under `/api/v1/academic` group in `routes/api.php` guarded by `auth:admin`.
3. Added `teacher()` `belongsTo` relationship in `app/Models/Batchs.php` and updated `BatchApiController.php` to conditionally eager-load `teacher` when the `teachers` table exists.

---

## 3. React Frontend Architecture & Implementation

### 1. Types & Data Contracts (`resources/js/types/academic.ts`)
- `Course`: Complete type matching Laravel Eloquent attributes, supporting both snake_case (`course_category`) and PascalCase relations.
- `CourseFormData`: Form submission payload with strict typing.
- `CourseCategory`: Lookup model for categories.
- `Teacher`: Lookup model for instructors (`id`, `name`, `designation`, `phone`).
- `Batch`: Complete batch model including seat counts, dates, and weekly schedule matrix (`day1`..`day7`, `schedule1`..`schedule7`).
- `BatchFormData`: Typed form payload.
- `BATCH_STATUS_MAP`: Status dictionary mapping codes 0, 1, 2, 3 to label and badge color token.
- `WEEK_DAYS`: Metadata dictionary defining days 1 to 7 with key, label, and short labels.

### 2. Service Layer (`resources/js/services/courseService.ts`, `batchService.ts`)
- Reuses the typed HTTP client (`apiClient`) with CSRF cookie handling and Bearer token interceptor.
- `courseService`: Methods for `getCourses()`, `getCourse(id)`, `createCourse(data)`, `updateCourse(id, data)`, `deleteCourse(id)`, and `getCategories()`.
- `batchService`: Methods for `getBatches()`, `getBatch(id)`, `createBatch(data)`, `updateBatch(id, data)`, `deleteBatch(id)`, and `getTeachers()`.

### 3. Course Components & Views
- **`CourseListPage` (`resources/js/pages/admin/courses/CourseListPage.tsx`):**
  - Live server-side filtering: search query (debounced), category select, status select (Active / Deactivated), and featured toggle.
  - Table display: row numbering, code badge, category, duration, modules, formatted fee, status badge, and action buttons.
  - State management: Loading skeletons, error state with retry, empty state with create CTA, and pagination controls.
  - Role-aware authorization: Create button (`course-create`), Edit button (`course-edit`), Delete button (`course-delete`).
  - Safe deletion: Confirmation modal displaying course name and handling server-side constraint errors (e.g. HTTP 422 if students enrolled).
- **`CourseDetailModal` (`resources/js/components/courses/CourseDetailModal.tsx`):**
  - Accessible modal dialog presenting full course curriculum, overview, description, software requirements, job placement details, and metrics.
- **`CourseFormModal` (`resources/js/components/courses/CourseFormModal.tsx`):**
  - Multi-tab form (Basic Information, Academic Structure, Marketing & Syllabus, Additional Details).
  - Server-side validation error mapping directly to offending form inputs.
  - Safe dirty state handling and duplicate submission prevention (`isSubmitting` disable).

### 4. Batch Components & Views
- **`BatchListPage` (`resources/js/pages/admin/batches/BatchListPage.tsx`):**
  - Status tabs: All, Running, Upcoming, Completed, Postponed.
  - Search filter: Batch number matching.
  - Table display: Batch number, assigned instructor, seat count, start/end dates, weekly active day schedule badges, status badge, and action buttons.
  - State management: Loading spinner, error alerts, empty states, and pagination.
  - Role-aware authorization: Create (`batch-create`), Edit (`batch-edit`), Delete (`batch-delete`).
  - Safe deletion: Confirmation modal handling student enrollment protection.
- **`BatchDetailModal` (`resources/js/components/batches/BatchDetailModal.tsx`):**
  - Displays batch statistics, instructor info, start/end dates, and the 7-day schedule matrix with time slots.
- **`BatchFormModal` (`resources/js/components/batches/BatchFormModal.tsx`):**
  - Batch number, total seats, instructor selection, start date, end date, status selector, and day/time schedule toggles for each day of the week.
  - Server-side validation error mapping.

### 5. Routing and Navigation Integration
- **`resources/js/routes/AdminRoutes.tsx`:**
  - Replaced Courses placeholder route with `<Route path="courses" element={<CourseListPage />} />`.
  - Replaced Batches placeholder route with `<Route path="batches" element={<BatchListPage />} />`.
  - Remaining routes (`students`, `accounting`, `roles`, `settings`) remain explicitly identified as placeholders.
- **`resources/js/components/admin/AdminNavigation.tsx`:**
  - Removed `isPlaceholder` and `Phase 3.3` badge from Courses and Batches items.
  - Bound permissions: Courses requires `course-list`; Batches requires `batch-list`.
  - Retained placeholder badges for unmigrated modules (`students`, `accounting`, `settings`).

---

## 4. Verification and Automated Testing

### 1. TypeScript & React Build
```bash
npm run typecheck
# Result: 0 errors.

npm run build:react
# Result: 87 modules transformed, built in 337ms.
# Artifacts: public/react/main.js (289.86 kB), public/react/main.css (10.45 kB).
```

### 2. Dedicated Phase 3.3 Feature Test Suite (`ReactCoursesBatchesMigrationTest.php`)
12 tests, 58 assertions:
- `test_unauthenticated_requests_are_rejected_from_courses_and_batches_apis`: 401 Unauthorized verification.
- `test_user_without_course_list_permission_cannot_list_courses`: 403 Forbidden verification.
- `test_admin_with_permission_can_list_courses_with_pagination`: Category eager loading and pagination structure.
- `test_admin_can_filter_courses_by_category_and_status`: Filtering accuracy.
- `test_admin_can_create_course_and_view_details`: Creation and `show` retrieval.
- `test_course_creation_validates_required_fields`: 422 Unprocessable Entity error envelopes.
- `test_admin_can_update_course`: Full update mutation verification.
- `test_admin_can_delete_course_without_enrolled_students`: Safe soft delete.
- `test_course_deletion_is_blocked_when_students_are_enrolled`: Prevents deletion when relations exist.
- `test_admin_can_list_and_filter_batches`: Status and search filtering.
- `test_admin_can_create_and_update_batch_with_weekly_schedule`: Schedule matrix persistence.
- `test_batch_teachers_endpoint_returns_active_instructors`: Instructor dropdown endpoint test.

### 3. Existing Foundation & Core Test Suites
- `tests/Feature/Api/V1/AcademicApiTest.php`: 15 / 15 passed (37 assertions).
- `tests/Feature/ReactFoundationTest.php`: 1 / 1 passed.
- `tests/Feature/ReactAuthFoundationTest.php`: 2 / 2 passed.
- `tests/Feature/ReactAdminShellTest.php`: 1 / 1 passed.
- Full PHPUnit suite: All 285 tests passed without regressions.

---

## 5. Files Created and Modified

### New Files
- `app/Http/Controllers/Api/V1/TeacherApiController.php`
- `resources/js/types/academic.ts`
- `resources/js/services/courseService.ts`
- `resources/js/services/batchService.ts`
- `resources/js/components/courses/CourseFormModal.tsx`
- `resources/js/components/courses/CourseDetailModal.tsx`
- `resources/js/components/courses/index.ts`
- `resources/js/pages/admin/courses/CourseListPage.tsx`
- `resources/js/pages/admin/courses/index.ts`
- `resources/js/components/batches/BatchFormModal.tsx`
- `resources/js/components/batches/BatchDetailModal.tsx`
- `resources/js/components/batches/index.ts`
- `resources/js/pages/admin/batches/BatchListPage.tsx`
- `resources/js/pages/admin/batches/index.ts`
- `tests/Feature/ReactCoursesBatchesMigrationTest.php`
- `docs/modernization/PHASE_3_3_COURSES_BATCHES_REACT_MIGRATION.md`

### Modified Files
- `app/Models/Batchs.php`: Added `teacher()` relation.
- `app/Http/Controllers/Api/V1/BatchApiController.php`: Added teacher eager-loading.
- `routes/api.php`: Added `teachers` route under academic group.
- `resources/js/types/index.ts`: Re-exported academic types.
- `resources/js/services/index.ts`: Re-exported academic services.
- `resources/js/api/endpoints.ts`: Added academic endpoints dictionary.
- `resources/js/pages/admin/index.ts`: Re-exported Courses and Batches pages.
- `resources/js/routes/AdminRoutes.tsx`: Mounted Courses and Batches routes.
- `resources/js/components/admin/AdminNavigation.tsx`: Activated Courses and Batches nav items.
- `resources/js/react/styles/index.css`: Added modal, table, form, tabs, and badge styling tokens.
- `docs/modernization/MODULE_API_REACT_MIGRATION_MAP.md`: Updated Module 2 status and prerequisites.

---

## 6. Deferred Workflows & Strict Stop

The following workflows are intentionally excluded from Phase 3.3 and reserved for subsequent phases:
- **Student Admissions & Enrollments (Phase 3.4):** Batch enrollment links, student admission intake, photo cropping, ID card generation.
- **Attendance & Trainee Logs:** Daily attendance marking and student lifecycle tracking.
- **Fees & Payment Integration:** Student tuition fee vouchers and bKash payments.
- **Accounting & Asset Projects:** Income/expense ledger and SEIP/ASSET trainees.

**Strict Stop:** Phase 3.3 is complete. No additional modules were altered.
