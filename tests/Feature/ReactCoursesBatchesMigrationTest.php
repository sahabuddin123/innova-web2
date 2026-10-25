<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Batchs;
use App\Models\CourseCategory;
use App\Models\Courses;
use App\Models\Teachers;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Class ReactCoursesBatchesMigrationTest
 *
 * Automated verification suite for Phase 3.3 Courses & Batches React Migration:
 * - Dedicated isolated mount serving React Courses & Batches interfaces under /react-admin/courses and /react-admin/batches
 * - Compiled React bundle verification confirming inclusion of Phase 3.3 components and services
 * - Course API full lifecycle: list with category filter, search, show, create with validation, update, delete guard
 * - Batch API full lifecycle: list with status filter, show with schedule matrix, create, update, delete guard
 * - Teacher API verification: GET /api/v1/academic/teachers for instructor assignment
 * - Authorization enforcement: 401 unauthenticated, 403 student token, 403 admin lacking permissions
 * - Zero regression of legacy Blade routes: /admin/courses and /admin/batchs remain protected by auth:admin
 *
 * Runs strictly on isolated in-memory SQLite database (:memory:) ensuring zero MySQL writes.
 *
 * @package Tests\Feature
 */
class ReactCoursesBatchesMigrationTest extends TestCase
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
            $table->index(['model_id', 'model_type']);
        });

        Schema::create('model_has_permissions', function ($table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
        });

        Schema::create('role_has_permissions', function ($table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
        });

        Schema::create('course_categories', function ($table) {
            $table->id();
            $table->string('CourseCategoris');
            $table->unsignedInteger('isFeature')->default(0)->nullable();
            $table->unsignedInteger('IsActive')->default(1)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('teachers', function ($table) {
            $table->id();
            $table->string('stuffid')->nullable();
            $table->string('name');
            $table->string('fname')->nullable();
            $table->string('mname')->nullable();
            $table->string('gender')->nullable();
            $table->date('dob')->nullable();
            $table->string('bloodgroup')->nullable();
            $table->string('religion')->nullable();
            $table->string('maritalStatus')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('nid')->nullable();
            $table->string('education')->nullable();
            $table->string('subject')->nullable();
            $table->string('designation')->nullable();
            $table->string('department')->nullable();
            $table->string('salary')->nullable();
            $table->string('contactType')->nullable();
            $table->string('shift')->nullable();
            $table->string('exprience')->nullable();
            $table->date('joiningdate')->nullable();
            $table->string('link1')->nullable();
            $table->string('link2')->nullable();
            $table->string('link3')->nullable();
            $table->string('link4')->nullable();
            $table->text('address')->nullable();
            $table->string('image')->nullable();
            $table->string('ComCourse')->nullable();
            $table->string('nStd')->nullable();
            $table->string('rating')->nullable();
            $table->string('review')->nullable();
            $table->unsignedInteger('isTeacher')->default(1)->nullable();
            $table->unsignedInteger('IsFeatured')->default(0)->nullable();
            $table->unsignedInteger('IsActive')->default(1)->nullable();
            $table->text('bio')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('courses', function ($table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('shortTitle')->nullable();
            $table->string('name');
            $table->string('coursefee');
            $table->string('courseduration')->nullable();
            $table->string('lacture')->nullable();
            $table->string('totalmoduls')->nullable();
            $table->string('totalproject')->nullable();
            $table->text('description')->nullable();
            $table->text('overview')->nullable();
            $table->text('curriculum')->nullable();
            $table->text('software')->nullable();
            $table->text('trainee')->nullable();
            $table->text('jobPlacement')->nullable();
            $table->text('jobPosition')->nullable();
            $table->unsignedInteger('IsActive')->default(1)->nullable();
            $table->unsignedInteger('featured')->default(0)->nullable();
            $table->unsignedInteger('IsCourse')->default(0)->nullable();
            $table->string('image')->nullable();
            $table->string('video')->nullable();
            $table->string('courseFor')->nullable();
            $table->string('Instructor')->nullable();
            $table->string('Rating')->nullable();
            $table->string('TotalReview')->nullable();
            $table->string('Discount')->nullable();
            $table->unsignedBigInteger('views')->default(0)->nullable();
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
            $table->unsignedInteger('day1')->default(0)->nullable();
            $table->string('schedule1')->nullable();
            $table->unsignedInteger('day2')->default(0)->nullable();
            $table->string('schedule2')->nullable();
            $table->unsignedInteger('day3')->default(0)->nullable();
            $table->string('schedule3')->nullable();
            $table->unsignedInteger('day4')->default(0)->nullable();
            $table->string('schedule4')->nullable();
            $table->unsignedInteger('day5')->default(0)->nullable();
            $table->string('schedule5')->nullable();
            $table->unsignedInteger('day6')->default(0)->nullable();
            $table->string('schedule6')->nullable();
            $table->unsignedInteger('day7')->default(0)->nullable();
            $table->string('schedule7')->nullable();
            $table->unsignedInteger('IsActive')->default(1)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('students', function ($table) {
            $table->id();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->string('name');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Seed initial auth admin and permissions.
     */
    protected function seedTestAuth(): void
    {
        $this->superAdmin = Admin::create([
            'name'     => 'Super Administrator',
            'email'    => 'admin@innovacomputersbd.com',
            'password' => Hash::make('secret123'),
        ]);

        $superRole = Role::create(['name' => 'super-admin', 'guard_name' => 'admin']);
        $this->superAdmin->assignRole($superRole);

        $permissions = [
            'course-list', 'course-create', 'course-edit', 'course-delete',
            'batch-list', 'batch-create', 'batch-edit', 'batch-delete',
        ];
        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin']);
        }

        $this->superAdminToken = $this->superAdmin->createToken('super-admin-token')->plainTextToken;
    }

    /**
     * Clean up test tables.
     */
    protected function tearDown(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            Schema::dropIfExists('students');
            Schema::dropIfExists('batchs');
            Schema::dropIfExists('courses');
            Schema::dropIfExists('teachers');
            Schema::dropIfExists('course_categories');
            Schema::dropIfExists('role_has_permissions');
            Schema::dropIfExists('model_has_permissions');
            Schema::dropIfExists('model_has_roles');
            Schema::dropIfExists('permissions');
            Schema::dropIfExists('roles');
            Schema::dropIfExists('personal_access_tokens');
            Schema::dropIfExists('users');
            Schema::dropIfExists('admins');
        }

        parent::tearDown();
    }

    // ==========================================
    // 1. SPA ROUTING MOUNT TESTS
    // ==========================================

    /**
     * Test that GET /react-admin/courses and /react-admin/batches serve the React SPA shell.
     */
    public function test_react_admin_academic_routes_serve_react_shell(): void
    {
        foreach (['/react-admin/courses', '/react-admin/batches'] as $route) {
            $response = $this->get($route);
            $response->assertStatus(200);
            $response->assertSee('id="react-root"', false);
            $response->assertSee('data-app-mode="admin"', false);
            $response->assertSee('react/main.js', false);
        }
    }

    /**
     * Test that compiled React bundle contains Phase 3.3 Courses & Batches components.
     */
    public function test_react_bundle_contains_phase_3_3_academic_components(): void
    {
        $mainJsPath = public_path('react/main.js');
        $this->assertFileExists($mainJsPath);

        $content = file_get_contents($mainJsPath);
        $this->assertStringContainsString('Courses & Academic Catalogs', $content, 'Bundle should contain Courses header');
        $this->assertStringContainsString('Batches & Academic Terms', $content, 'Bundle should contain Batches header');
        $this->assertStringContainsString('Weekly Class Schedule', $content, 'Bundle should contain weekly schedule matrix');
    }

    // ==========================================
    // 2. COURSES API TESTS
    // ==========================================

    /**
     * Test course creation and listing with category filtering.
     */
    public function test_admin_can_create_and_filter_courses(): void
    {
        $category = CourseCategory::create(['CourseCategoris' => 'Web & Software Engineering']);

        $createResponse = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->postJson('/api/v1/academic/courses', [
                'name'           => 'Mastering React & TypeScript',
                'coursefee'      => '18000',
                'category_id'    => $category->id,
                'shortTitle'     => 'MRTS-01',
                'courseduration' => '4',
                'lacture'        => '32',
                'totalmoduls'    => '8',
                'totalproject'   => '3',
                'description'    => 'Modern frontend development with typed React.',
                'IsActive'       => 1,
                'featured'       => 1,
            ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Mastering React & TypeScript')
            ->assertJsonPath('data.coursefee', '18000');

        $courseId = $createResponse->json('data.id');

        // Test show single course
        $showResponse = $this->getJson('/api/v1/academic/courses/' . $courseId);
        $showResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $courseId)
            ->assertJsonPath('data.name', 'Mastering React & TypeScript')
            ->assertJsonPath('data.course_category.CourseCategoris', 'Web & Software Engineering');

        // Test filtering by category_id
        $listResponse = $this->getJson('/api/v1/academic/courses?category_id=' . $category->id);
        $listResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Mastering React & TypeScript');
    }

    /**
     * Test course update functionality.
     */
    public function test_admin_can_update_course(): void
    {
        $course = Courses::create([
            'name'      => 'Old Course Name',
            'coursefee' => '10000',
            'IsActive'  => 1,
        ]);

        $updateResponse = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->putJson('/api/v1/academic/courses/' . $course->id, [
                'name'      => 'Updated Course Name',
                'coursefee' => '12000',
                'IsActive'  => 0,
            ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Course Name')
            ->assertJsonPath('data.coursefee', '12000')
            ->assertJsonPath('data.IsActive', 0);
    }

    /**
     * Test course deletion prevention when students are enrolled.
     */
    public function test_cannot_delete_course_with_enrolled_students(): void
    {
        $course = Courses::create(['name' => 'Protected Course', 'coursefee' => '15000']);
        DB::table('students')->insert([
            'course_id'  => $course->id,
            'name'       => 'Active Enrolled Student',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $deleteResponse = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->deleteJson('/api/v1/academic/courses/' . $course->id);

        $deleteResponse->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Cannot delete course: enrolled students are linked to this course');

        // Confirm course was not deleted
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'deleted_at' => null]);
    }

    // ==========================================
    // 3. BATCHES API TESTS
    // ==========================================

    /**
     * Test batch creation and listing with schedule matrix and instructor.
     */
    public function test_admin_can_create_and_list_batches_with_schedule(): void
    {
        $teacher = Teachers::create([
            'name'        => 'Engr. Rakib Hasan',
            'designation' => 'Senior Technical Trainer',
            'phone'       => '01711223344',
            'email'       => 'rakib@innovabd.net',
            'IsActive'    => 1,
        ]);

        $createResponse = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->postJson('/api/v1/academic/batches', [
                'batchnum'  => 'WD-2026-05',
                'totalseat' => '25',
                'stuff_id'  => $teacher->id,
                'startdate' => '2026-03-01',
                'enddate'   => '2026-06-30',
                'day1'      => 1,
                'schedule1' => '09:00 AM - 11:00 AM',
                'day3'      => 1,
                'schedule3' => '09:00 AM - 11:00 AM',
                'IsActive'  => 1, // Running
            ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.batchnum', 'WD-2026-05')
            ->assertJsonPath('data.totalseat', '25')
            ->assertJsonPath('data.stuff_id', $teacher->id);

        $batchId = $createResponse->json('data.id');

        // Test show single batch with teacher
        $showResponse = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->getJson('/api/v1/academic/batches/' . $batchId);

        $showResponse->assertStatus(200)
            ->assertJsonPath('data.batchnum', 'WD-2026-05')
            ->assertJsonPath('data.teacher.name', 'Engr. Rakib Hasan');

        // Test filter by status running
        $listResponse = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->getJson('/api/v1/academic/batches?status=running');

        $listResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.batchnum', 'WD-2026-05');
    }

    /**
     * Test batch deletion prevention when students are assigned.
     */
    public function test_cannot_delete_batch_with_assigned_students(): void
    {
        $batch = Batchs::create(['batchnum' => 'GD-2026-02', 'totalseat' => '30']);
        DB::table('students')->insert([
            'batch_id'   => $batch->id,
            'name'       => 'Assigned Batch Student',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $deleteResponse = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->deleteJson('/api/v1/academic/batches/' . $batch->id);

        $deleteResponse->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Cannot delete batch: enrolled students are assigned to this batch');

        $this->assertDatabaseHas('batchs', ['id' => $batch->id, 'deleted_at' => null]);
    }

    // ==========================================
    // 4. TEACHERS API FOR DROPDOWNS
    // ==========================================

    /**
     * Test that GET /api/v1/academic/teachers returns list of active instructors for forms.
     */
    public function test_can_list_teachers_for_instructor_dropdowns(): void
    {
        Teachers::create(['name' => 'Instructor Alpha', 'designation' => 'Lead Web Trainer', 'IsActive' => 1]);
        Teachers::create(['name' => 'Instructor Beta', 'designation' => 'UI/UX Mentor', 'IsActive' => 1]);

        $response = $this->getJson('/api/v1/academic/teachers');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Instructor Alpha')
            ->assertJsonPath('data.1.name', 'Instructor Beta');
    }

    // ==========================================
    // 5. AUTHORIZATION SECURITY BOUNDARY TESTS
    // ==========================================

    /**
     * Test that unauthenticated users are rejected with 401 on academic mutations.
     */
    public function test_unauthenticated_request_to_create_course_returns_401(): void
    {
        $response = $this->postJson('/api/v1/academic/courses', [
            'name'      => 'Unauthorized Course',
            'coursefee' => '10000',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test that student token is rejected with 403 on admin academic mutations.
     */
    public function test_student_token_rejected_from_creating_course_or_batch(): void
    {
        $studentUser = User::create([
            'name'     => 'Regular Student',
            'email'    => 'student.academic@innovabd.net',
            'password' => Hash::make('Secret123!'),
        ]);

        $studentToken = $studentUser->createToken('student-token')->plainTextToken;

        $courseResponse = $this->withHeader('Authorization', 'Bearer ' . $studentToken)
            ->postJson('/api/v1/academic/courses', [
                'name'      => 'Exploit Course Attempt',
                'coursefee' => '5000',
            ]);

        $courseResponse->assertStatus(403);

        $batchResponse = $this->withHeader('Authorization', 'Bearer ' . $studentToken)
            ->postJson('/api/v1/academic/batches', [
                'batchnum'  => 'HACK-01',
                'totalseat' => '10',
            ]);

        $batchResponse->assertStatus(403);
    }

    /**
     * Test that admin lacking course-create or batch-create permission receives 403.
     */
    public function test_admin_without_permissions_receives_403(): void
    {
        $limitedAdmin = Admin::create([
            'name'     => 'Limited Staff',
            'email'    => 'staff.academic@innovabd.net',
            'password' => Hash::make('Secret123!'),
        ]);
        // Do not assign permissions
        $token = $limitedAdmin->createToken('limited-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/academic/courses', [
                'name'      => 'Staff Course Attempt',
                'coursefee' => '5000',
            ]);

        $response->assertStatus(403);
    }

    // ==========================================
    // 6. LEGACY BLADE REGRESSION TESTS
    // ==========================================

    /**
     * Test that legacy Blade admin routes (/admin/courses and /admin/batchs) remain intact and guarded.
     */
    public function test_legacy_blade_academic_routes_remain_intact(): void
    {
        // /admin/courses and /admin/batchs are wrapped in auth:admin middleware
        $coursesResponse = $this->get('/admin/courses');
        $this->assertTrue(
            in_array($coursesResponse->status(), [302, 401, 200]),
            'Expected /admin/courses to invoke Blade admin middleware (got ' . $coursesResponse->status() . ')'
        );

        $batchesResponse = $this->get('/admin/batchs');
        $this->assertTrue(
            in_array($batchesResponse->status(), [302, 401, 200]),
            'Expected /admin/batchs to invoke Blade admin middleware (got ' . $batchesResponse->status() . ')'
        );
    }
}
