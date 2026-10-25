<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Admission;
use App\Models\Batchs;
use App\Models\CourseCategory;
use App\Models\Courses;
use App\Models\Students;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Class ReactAdmissionsMigrationTest
 *
 * Automated verification suite for Phase 3.5 Online Student Admissions React Migration:
 * - Dedicated mount serving React Admissions interface under /react-admin/admissions
 * - Compiled React bundle verification confirming inclusion of Phase 3.5 admissions components
 * - Public vs Authenticated / RBAC permissions:
 *   - Public inquiry submission (POST /api/v1/academic/admissions)
 *   - Unauthenticated access blocked (401)
 *   - Student user access blocked from admin listing (403)
 *   - Admin permissions enforced: student-list for index/show, student-edit for update
 * - Validation & Server-Side Rules:
 *   - Required fields and course existence
 *   - Duplicate phone prevention (422)
 *   - Lifecycle status updates across states 0 through 6
 *   - Invalid status code rejection (422)
 * - Filtering & Search:
 *   - Filter by courseId, courseType (offline/online), isActive (status code)
 *   - Search query by username, phone, or email
 * - Coexistence with legacy Blade routes:
 *   - Legacy /admin/admission route redirects unauthenticated users to /admin/login
 *
 * Runs strictly on isolated in-memory SQLite database (:memory:) ensuring zero MySQL writes.
 *
 * @package Tests\Feature
 */
class ReactAdmissionsMigrationTest extends TestCase
{
    /**
     * @var Admin
     */
    protected $superAdmin;

    /**
     * @var string
     */
    protected $superAdminToken;

    /**
     * @var Admin
     */
    protected $limitedAdmin;

    /**
     * @var string
     */
    protected $limitedAdminToken;

    /**
     * @var Courses
     */
    protected $testCourse1;

    /**
     * @var Courses
     */
    protected $testCourse2;

    /**
     * @var Batchs
     */
    protected $testBatch;

    /**
     * Set up isolated test environment with in-memory SQLite database.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('app.url', 'http://localhost');
        $this->app['url']->forceRootUrl('http://localhost');

        $this->app['config']->set('database.default', 'sqlite');
        $this->app['config']->set('database.connections.sqlite.database', ':memory:');

        $connection = DB::connection();
        if ($connection->getDriverName() !== 'sqlite' || $connection->getDatabaseName() !== ':memory:') {
            $this->fail('CRITICAL SAFETY CHECK: Test is not running on an isolated SQLite in-memory database!');
        }

        $this->createTestTables();
        $this->seedTestAuth();
    }

    /**
     * Create isolated schema in SQLite in-memory database.
     */
    protected function createTestTables(): void
    {
        Schema::create('admins', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function ($table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('permissions', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('model_has_roles', function ($table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::create('model_has_permissions', function ($table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });

        Schema::create('role_has_permissions', function ($table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('course_categories', function ($table) {
            $table->id();
            $table->string('CourseCategoris');
            $table->tinyInteger('isFeature')->default(0)->nullable();
            $table->tinyInteger('IsActive')->default(1)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('courses', function ($table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('name');
            $table->string('coursefee');
            $table->string('shortTitle')->nullable();
            $table->string('courseduration')->nullable();
            $table->string('lacture')->nullable();
            $table->string('totalmoduls')->nullable();
            $table->string('totalproject')->nullable();
            $table->text('description')->nullable();
            $table->text('overview')->nullable();
            $table->text('curriculum')->nullable();
            $table->string('software')->nullable();
            $table->string('trainee')->nullable();
            $table->string('jobPlacement')->nullable();
            $table->string('jobPosition')->nullable();
            $table->integer('IsActive')->default(1);
            $table->integer('featured')->default(0)->nullable();
            $table->integer('IsCourse')->default(1)->nullable();
            $table->string('image')->nullable();
            $table->string('video')->nullable();
            $table->string('Instructor')->nullable();
            $table->string('Rating')->nullable();
            $table->string('TotalReview')->nullable();
            $table->string('Discount')->nullable();
            $table->integer('views')->default(0)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('batchs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('stuff_id')->nullable();
            $table->string('batchnum');
            $table->string('totalseat');
            $table->date('startdate')->nullable();
            $table->date('enddate')->nullable();
            $table->integer('IsActive')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('students', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('batch_id');
            $table->string('sname');
            $table->string('gender')->default('Male');
            $table->date('dob')->nullable();
            $table->string('bloodgroup')->nullable();
            $table->string('religion')->default('Islam');
            $table->string('height')->nullable();
            $table->string('weight')->nullable();
            $table->string('phone')->nullable();
            $table->string('nid')->nullable();
            $table->string('last_school')->nullable();
            $table->string('f_name')->nullable();
            $table->string('f_phone')->nullable();
            $table->string('m_name')->nullable();
            $table->string('m_phone')->nullable();
            $table->string('g_name');
            $table->string('g_phone');
            $table->string('rel_g');
            $table->string('discount')->nullable();
            $table->string('course_fee');
            $table->string('balance')->nullable();
            $table->string('ref')->nullable();
            $table->string('image')->nullable();
            $table->date('admission_date');
            $table->unsignedInteger('IsRegister')->default(1)->nullable();
            $table->unsignedInteger('IsActive')->default(1)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('admissions', function ($table) {
            $table->id();
            $table->string('username')->nullable();
            $table->string('phone')->unique();
            $table->string('email')->nullable();
            $table->unsignedBigInteger('courseId')->nullable();
            $table->unsignedInteger('courseType')->default(0)->nullable();
            $table->unsignedInteger('isActive')->default(0)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Seed authentication and academic fixtures.
     */
    protected function seedTestAuth(): void
    {
        Permission::create(['name' => 'student-list', 'guard_name' => 'admin']);
        Permission::create(['name' => 'student-create', 'guard_name' => 'admin']);
        Permission::create(['name' => 'student-edit', 'guard_name' => 'admin']);
        Permission::create(['name' => 'student-delete', 'guard_name' => 'admin']);

        $superAdminRole = Role::create(['name' => 'super-admin', 'guard_name' => 'admin']);
        $superAdminRole->givePermissionTo(['student-list', 'student-create', 'student-edit', 'student-delete']);

        $limitedRole = Role::create(['name' => 'limited-clerk', 'guard_name' => 'admin']);
        $limitedRole->givePermissionTo(['student-list']); // Only list, no edit

        $this->superAdmin = Admin::create([
            'name'     => 'Super Administrator',
            'email'    => 'superadmin@innovabd.test',
            'password' => Hash::make('secret123'),
        ]);
        $this->superAdmin->assignRole('super-admin');
        $this->superAdminToken = $this->superAdmin->createToken('admin-token')->plainTextToken;

        $this->limitedAdmin = Admin::create([
            'name'     => 'Clerk Staff',
            'email'    => 'clerk@innovabd.test',
            'password' => Hash::make('secret123'),
        ]);
        $this->limitedAdmin->assignRole('limited-clerk');
        $this->limitedAdminToken = $this->limitedAdmin->createToken('clerk-token')->plainTextToken;

        $category = CourseCategory::create([
            'CourseCategoris' => 'Information Technology',
            'IsActive'        => 1,
        ]);

        $this->testCourse1 = Courses::create([
            'category_id' => $category->id,
            'name'        => 'Web Development & Modern React',
            'shortTitle'  => 'WDMR',
            'coursefee'   => '20000',
            'IsActive'    => 1,
        ]);

        $this->testCourse2 = Courses::create([
            'category_id' => $category->id,
            'name'        => 'Graphics & UI/UX Design',
            'shortTitle'  => 'GIUX',
            'coursefee'   => '15000',
            'IsActive'    => 1,
        ]);

        $this->testBatch = Batchs::create([
            'batchnum'  => 'WD-2026-B1',
            'totalseat' => '30',
            'IsActive'  => 1,
        ]);
    }

    // ==========================================
    // 1. REACT ROUTE AND ASSET BUNDLE TESTS
    // ==========================================

    public function test_react_admin_admissions_route_serves_spa_view_with_data_mode()
    {
        $response = $this->get('/react-admin/admissions');

        $response->assertStatus(200);
        $response->assertSee('id="react-root"', false);
        $response->assertSee('data-app-mode="admin"', false);
    }

    public function test_compiled_react_bundle_contains_phase_3_5_admissions_components()
    {
        $bundlePath = public_path('react/main.js');
        $this->assertFileExists($bundlePath, 'Compiled React production bundle public/react/main.js must exist.');

        $bundleContent = file_get_contents($bundlePath);

        // Verify admissions endpoint, statuses, and routes are bundled
        $this->assertStringContainsString('academic/admissions', $bundleContent);
        $this->assertStringContainsString('Waiting for Contact', $bundleContent);
        $this->assertStringContainsString('Call Not Received', $bundleContent);
        $this->assertStringContainsString('Waiting For Admission', $bundleContent);
        $this->assertStringContainsString('Admission Complete', $bundleContent);
    }

    // ==========================================
    // 2. PUBLIC ADMISSION SUBMISSION & VALIDATION
    // ==========================================

    public function test_public_user_can_submit_online_admission_inquiry()
    {
        $payload = [
            'username'   => 'Prospective Student',
            'phone'      => '01711223344',
            'email'      => 'applicant@test.com',
            'courseId'   => $this->testCourse1->id,
            'courseType' => 0, // Offline
        ];

        $response = $this->postJson('/api/v1/academic/admissions', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Admission inquiry submitted successfully',
                'data'    => [
                    'username'   => 'Prospective Student',
                    'phone'      => '01711223344',
                    'email'      => 'applicant@test.com',
                    'courseId'   => $this->testCourse1->id,
                    'courseType' => 0,
                    'isActive'   => 0, // Starts as Waiting for Contact
                ],
            ]);

        $this->assertDatabaseHas('admissions', [
            'username' => 'Prospective Student',
            'phone'    => '01711223344',
            'isActive' => 0,
        ]);
    }

    public function test_public_submission_validation_errors()
    {
        // Missing all required fields
        $response = $this->postJson('/api/v1/academic/admissions', []);
        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['username', 'phone', 'courseId', 'courseType']]);

        // Nonexistent courseId
        $response = $this->postJson('/api/v1/academic/admissions', [
            'username'   => 'Applicant',
            'phone'      => '01811223344',
            'courseId'   => 9999, // Nonexistent
            'courseType' => 0,
        ]);
        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['courseId']]);

        // Invalid courseType (must be 0 or 1)
        $response = $this->postJson('/api/v1/academic/admissions', [
            'username'   => 'Applicant',
            'phone'      => '01811223344',
            'courseId'   => $this->testCourse1->id,
            'courseType' => 99,
        ]);
        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['courseType']]);
    }

    public function test_duplicate_phone_inquiry_is_rejected_safeguard()
    {
        Admission::create([
            'username'   => 'First Applicant',
            'phone'      => '01799887766',
            'courseId'   => $this->testCourse1->id,
            'courseType' => 0,
            'isActive'   => 0,
        ]);

        $response = $this->postJson('/api/v1/academic/admissions', [
            'username'   => 'Second Applicant',
            'phone'      => '01799887766', // Duplicate phone
            'courseId'   => $this->testCourse1->id,
            'courseType' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['phone']]);
    }

    // ==========================================
    // 3. AUTHENTICATION & RBAC PERMISSIONS
    // ==========================================

    public function test_unauthenticated_request_cannot_list_admissions()
    {
        $response = $this->getJson('/api/v1/academic/admissions');
        $response->assertStatus(401);
    }

    public function test_student_user_token_cannot_access_administrative_admissions_api()
    {
        $studentUser = User::create([
            'name'     => 'Regular Student',
            'email'    => 'student@test.com',
            'password' => Hash::make('secret'),
        ]);
        $studentToken = $studentUser->createToken('student-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $studentToken)
            ->getJson('/api/v1/academic/admissions');

        $response->assertStatus(403);
    }

    public function test_admin_without_student_list_permission_is_forbidden()
    {
        $unpermittedAdmin = Admin::create([
            'name'     => 'Unpermitted Admin',
            'email'    => 'noperms@test.com',
            'password' => Hash::make('secret'),
        ]);
        $token = $unpermittedAdmin->createToken('no-perm-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/academic/admissions');

        $response->assertStatus(403);
    }

    public function test_admin_with_student_list_can_view_paginated_admissions()
    {
        Admission::create([
            'username'   => 'Applicant Alpha',
            'phone'      => '01600000001',
            'email'      => 'alpha@test.com',
            'courseId'   => $this->testCourse1->id,
            'courseType' => 0,
            'isActive'   => 0,
        ]);

        Admission::create([
            'username'   => 'Applicant Beta',
            'phone'      => '01600000002',
            'email'      => 'beta@test.com',
            'courseId'   => $this->testCourse2->id,
            'courseType' => 1,
            'isActive'   => 1,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->getJson('/api/v1/academic/admissions');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Admissions retrieved successfully',
            ])
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'username',
                        'phone',
                        'email',
                        'courseId',
                        'courseType',
                        'isActive',
                        'course' => ['id', 'name', 'shortTitle', 'coursefee'],
                    ],
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);
    }

    // ==========================================
    // 4. FILTERING & SEARCH
    // ==========================================

    public function test_admin_can_filter_admissions_by_course_status_and_delivery_type()
    {
        Admission::create([
            'username'   => 'Course 1 Offline Pending',
            'phone'      => '01600000011',
            'courseId'   => $this->testCourse1->id,
            'courseType' => 0, // Offline
            'isActive'   => 0, // Waiting for Contact
        ]);

        Admission::create([
            'username'   => 'Course 1 Online Complete',
            'phone'      => '01600000012',
            'courseId'   => $this->testCourse1->id,
            'courseType' => 1, // Online
            'isActive'   => 3, // Admission Complete
        ]);

        Admission::create([
            'username'   => 'Course 2 Offline Pending',
            'phone'      => '01600000013',
            'courseId'   => $this->testCourse2->id,
            'courseType' => 0,
            'isActive'   => 0,
        ]);

        // 1. Filter by courseId
        $courseFilterRes = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->getJson('/api/v1/academic/admissions?courseId=' . $this->testCourse2->id);
        $courseFilterRes->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.username', 'Course 2 Offline Pending');

        // 2. Filter by isActive (status code 3)
        $statusFilterRes = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->getJson('/api/v1/academic/admissions?isActive=3');
        $statusFilterRes->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.username', 'Course 1 Online Complete');

        // 3. Filter by courseType (1 = Online)
        $typeFilterRes = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->getJson('/api/v1/academic/admissions?courseType=1');
        $typeFilterRes->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.username', 'Course 1 Online Complete');
    }

    public function test_admin_can_search_admissions_by_name_phone_or_email()
    {
        Admission::create([
            'username'   => 'Zahirul Islam',
            'phone'      => '01755555555',
            'email'      => 'zahir@example.com',
            'courseId'   => $this->testCourse1->id,
            'courseType' => 0,
            'isActive'   => 0,
        ]);

        Admission::create([
            'username'   => 'Kamrul Hasan',
            'phone'      => '01866666666',
            'email'      => 'kamrul@example.com',
            'courseId'   => $this->testCourse1->id,
            'courseType' => 0,
            'isActive'   => 0,
        ]);

        // Search by username
        $searchRes = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->getJson('/api/v1/academic/admissions?search=Zahirul');
        $searchRes->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.username', 'Zahirul Islam');

        // Search by phone
        $searchPhone = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->getJson('/api/v1/academic/admissions?search=01866666666');
        $searchPhone->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.username', 'Kamrul Hasan');

        // Search by email
        $searchEmail = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->getJson('/api/v1/academic/admissions?search=zahir@example.com');
        $searchEmail->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.username', 'Zahirul Islam');
    }

    // ==========================================
    // 5. DETAIL SHOW ENDPOINT
    // ==========================================

    public function test_admin_can_view_single_admission_details_with_course_relationship()
    {
        $admission = Admission::create([
            'username'   => 'Inquirer Detail Test',
            'phone'      => '01712345678',
            'email'      => 'detail@example.com',
            'courseId'   => $this->testCourse1->id,
            'courseType' => 1,
            'isActive'   => 0,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->getJson('/api/v1/academic/admissions/' . $admission->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'id'         => $admission->id,
                    'username'   => 'Inquirer Detail Test',
                    'phone'      => '01712345678',
                    'email'      => 'detail@example.com',
                    'courseType' => 1,
                    'course'     => [
                        'id'   => $this->testCourse1->id,
                        'name' => $this->testCourse1->name,
                    ],
                ],
            ]);

        // Nonexistent ID returns 404
        $notFoundRes = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->getJson('/api/v1/academic/admissions/99999');
        $notFoundRes->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Admission record not found',
            ]);
    }

    // ==========================================
    // 6. ADMINISTRATIVE UPDATE & LIFECYCLE STATUS TRANSITIONS
    // ==========================================

    public function test_admin_without_student_edit_cannot_update_admission()
    {
        $admission = Admission::create([
            'username'   => 'Pending Edit',
            'phone'      => '01500000001',
            'courseId'   => $this->testCourse1->id,
            'courseType' => 0,
            'isActive'   => 0,
        ]);

        // limitedAdmin only has student-list, not student-edit
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->limitedAdminToken)
            ->putJson('/api/v1/academic/admissions/' . $admission->id, [
                'isActive' => 1,
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_with_student_edit_can_update_admission_details()
    {
        $admission = Admission::create([
            'username'   => 'Original Name',
            'phone'      => '01500000002',
            'email'      => 'original@test.com',
            'courseId'   => $this->testCourse1->id,
            'courseType' => 0,
            'isActive'   => 0,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->putJson('/api/v1/academic/admissions/' . $admission->id, [
                'username'   => 'Updated Name',
                'courseId'   => $this->testCourse2->id,
                'courseType' => 1,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Admission record updated successfully',
                'data'    => [
                    'username'   => 'Updated Name',
                    'courseId'   => $this->testCourse2->id,
                    'courseType' => 1,
                ],
            ]);

        $this->assertDatabaseHas('admissions', [
            'id'         => $admission->id,
            'username'   => 'Updated Name',
            'courseId'   => $this->testCourse2->id,
            'courseType' => 1,
        ]);
    }

    public function test_admin_can_update_all_lifecycle_status_states_0_through_6()
    {
        $admission = Admission::create([
            'username'   => 'Status Transition Candidate',
            'phone'      => '01500000003',
            'courseId'   => $this->testCourse1->id,
            'courseType' => 0,
            'isActive'   => 0,
        ]);

        // Valid statuses from legacy Blade index.blade.php & edit.blade.php:
        // 0 = Waiting for Contact
        // 1 = Call Not received
        // 2 = Waiting For Admission
        // 3 = Admission Complete
        // 4 = SMS Send
        // 5 = Not Interested
        // 6 = Restricted
        $validStatuses = [0, 1, 2, 3, 4, 5, 6];

        foreach ($validStatuses as $status) {
            $response = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
                ->putJson('/api/v1/academic/admissions/' . $admission->id, [
                    'isActive' => $status,
                ]);

            $response->assertStatus(200)
                ->assertJsonPath('data.isActive', $status);

            $this->assertDatabaseHas('admissions', [
                'id'       => $admission->id,
                'isActive' => $status,
            ]);
        }

        // Invalid status outside 0-6 must be rejected with 422
        $invalidRes = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->putJson('/api/v1/academic/admissions/' . $admission->id, [
                'isActive' => 99,
            ]);

        $invalidRes->assertStatus(422)
            ->assertJsonStructure(['errors' => ['isActive']]);
    }

    // ==========================================
    // 7. DUPLICATE ENROLLMENT SAFEGUARD
    // ==========================================

    public function test_duplicate_student_enrollment_safeguard()
    {
        // Registered Student in students directory
        Students::create([
            'course_id'      => $this->testCourse1->id,
            'batch_id'       => $this->testBatch->id,
            'sname'          => 'Existing Registered Student',
            'phone'          => '01988887777',
            'gender'         => 'Male',
            'religion'       => 'Islam',
            'g_name'         => 'Guardian',
            'g_phone'        => '01988887778',
            'rel_g'          => 'Father',
            'course_fee'     => '20000',
            'admission_date' => '2026-01-01',
        ]);

        // Prospective applicant submits admission inquiry with same phone
        $inquiry = Admission::create([
            'username'   => 'Existing Student Re-Applying',
            'phone'      => '01988887777',
            'courseId'   => $this->testCourse1->id,
            'courseType' => 0,
            'isActive'   => 0,
        ]);

        // Admin queries students endpoint to check duplicate phone
        $studentCheck = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->getJson('/api/v1/academic/students?search=' . $inquiry->phone);

        $studentCheck->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.phone', '01988887777');
    }

    // ==========================================
    // 8. LEGACY BLADE ROUTE COEXISTENCE
    // ==========================================

    public function test_legacy_blade_admission_routes_coexistence_and_auth_protection()
    {
        // Unauthenticated access to /admin/admission redirects to /admin/login
        $response = $this->get('/admin/admission');
        $response->assertStatus(302);
        $response->assertRedirect('/admin/login');
    }
}
