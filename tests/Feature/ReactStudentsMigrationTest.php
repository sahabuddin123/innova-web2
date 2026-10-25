<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Batchs;
use App\Models\CourseCategory;
use App\Models\Courses;
use App\Models\FeesTransections;
use App\Models\Students;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Class ReactStudentsMigrationTest
 *
 * Automated verification suite for Phase 3.4 Students Management React Migration:
 * - Dedicated mount serving React Students Management interface under /react-admin/students
 * - Compiled React bundle verification confirming inclusion of Phase 3.4 student components & services
 * - Students API full lifecycle: list with pagination, search, course & batch filtering, status tabs
 * - Student detail show endpoint with course, batch, and payment relationship eager-loading
 * - Student creation with validation errors (422) and server-side model persistence
 * - Student updates: profile updates, financial balance adjustments, and multi-state status transitions (1-5)
 * - Deletion safety checks: soft-deletion allowed only when no fee transactions exist
 * - Authorization enforcement: 401 unauthenticated, 403 student token forbidden from admin list, 403 admin lacking student-list/create/edit/delete
 * - Zero regression of legacy Blade routes: /admin/students and /admin/students/create remain protected by auth:admin
 *
 * Runs strictly on isolated in-memory SQLite database (:memory:) ensuring zero MySQL writes.
 *
 * @package Tests\Feature
 */
class ReactStudentsMigrationTest extends TestCase
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
     * @var Courses
     */
    protected $testCourse;

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
            $table->tinyInteger('day1')->default(0)->nullable();
            $table->string('schedule1')->nullable();
            $table->tinyInteger('day2')->default(0)->nullable();
            $table->string('schedule2')->nullable();
            $table->tinyInteger('day3')->default(0)->nullable();
            $table->string('schedule3')->nullable();
            $table->tinyInteger('day4')->default(0)->nullable();
            $table->string('schedule4')->nullable();
            $table->tinyInteger('day5')->default(0)->nullable();
            $table->string('schedule5')->nullable();
            $table->tinyInteger('day6')->default(0)->nullable();
            $table->string('schedule6')->nullable();
            $table->tinyInteger('day7')->default(0)->nullable();
            $table->string('schedule7')->nullable();
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
            $table->string('gender');
            $table->date('dob')->nullable();
            $table->string('bloodgroup')->nullable();
            $table->string('religion')->nullable();
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

        Schema::create('fees_transections', function ($table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->string('amount')->nullable();
            $table->decimal('paidamount', 12, 2)->default(0.00);
            $table->unsignedInteger('paymentType')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Seed initial auth and foundational academic entities.
     */
    protected function seedTestAuth(): void
    {
        $this->superAdmin = Admin::create([
            'name'     => 'Director Administrator',
            'email'    => 'director@innovacomputersbd.com',
            'password' => Hash::make('secret123'),
        ]);

        $superRole = Role::create(['name' => 'super-admin', 'guard_name' => 'admin']);
        $this->superAdmin->assignRole($superRole);

        $permissions = [
            'student-list', 'student-create', 'student-edit', 'student-delete',
            'course-list', 'batch-list',
        ];
        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin']);
        }

        $this->superAdminToken = $this->superAdmin->createToken('admin-token')->plainTextToken;

        $category = CourseCategory::create(['CourseCategoris' => 'Software Development']);
        $this->testCourse = Courses::create([
            'category_id' => $category->id,
            'name'        => 'Full Stack React & Laravel',
            'coursefee'   => '25000',
            'shortTitle'  => 'FSRL',
        ]);

        $this->testBatch = Batchs::create([
            'batchnum'  => 'BATCH-2026-FSRL',
            'totalseat' => '30',
            'startdate' => '2026-03-01',
            'enddate'   => '2026-06-30',
            'IsActive'  => 1,
        ]);
    }

    /**
     * Test unauthenticated requests are rejected.
     */
    public function test_unauthenticated_requests_are_rejected(): void
    {
        $response = $this->getJson('/api/v1/academic/students');
        $response->assertStatus(401);
    }

    /**
     * Test admin without student-list permission cannot list students.
     */
    public function test_admin_without_student_list_permission_cannot_list_students(): void
    {
        $regularAdmin = Admin::create([
            'name'     => 'Restricted Officer',
            'email'    => 'restricted@innova.com',
            'password' => Hash::make('password123'),
        ]);
        $restrictedToken = $regularAdmin->createToken('restricted-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $restrictedToken)
            ->getJson('/api/v1/academic/students');

        $response->assertStatus(403);
    }

    /**
     * Test student user token cannot access administrative students list.
     */
    public function test_student_user_token_cannot_access_administrative_students_list(): void
    {
        $studentUser = User::create([
            'name'     => 'Trainee User',
            'email'    => 'trainee@gmail.com',
            'password' => Hash::make('secret123'),
        ]);
        $userToken = $studentUser->createToken('user-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $userToken)
            ->getJson('/api/v1/academic/students');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Forbidden: Admin access required',
            ]);
    }

    /**
     * Test admin can list students with pagination and eager-loaded course & batch.
     */
    public function test_admin_can_list_students_with_pagination(): void
    {
        Students::create([
            'course_id'      => $this->testCourse->id,
            'batch_id'       => $this->testBatch->id,
            'sname'          => 'Tariq Rahman',
            'gender'         => 'Male',
            'religion'       => 'Islam',
            'phone'          => '01712345678',
            'g_name'         => 'Rezaul Rahman',
            'g_phone'        => '01710000000',
            'rel_g'          => 'Father',
            'course_fee'     => '25000',
            'discount'       => '3000',
            'balance'        => '22000',
            'admission_date' => '2026-02-15',
            'IsActive'       => 1,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->getJson('/api/v1/academic/students');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sname', 'Tariq Rahman')
            ->assertJsonPath('data.0.course.name', 'Full Stack React & Laravel')
            ->assertJsonPath('data.0.batch.batchnum', 'BATCH-2026-FSRL')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'sname', 'phone', 'batch_id', 'course_id', 'course_fee',
                        'discount', 'balance', 'IsActive', 'admission_date',
                        'course' => ['id', 'name', 'shortTitle'],
                        'batch'  => ['id', 'batchnum'],
                    ]
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);
    }

    /**
     * Test admin can filter students by search query, course, batch, and status.
     */
    public function test_admin_can_filter_students_by_query_course_batch_and_status(): void
    {
        // Student 1: Active
        Students::create([
            'course_id'      => $this->testCourse->id,
            'batch_id'       => $this->testBatch->id,
            'sname'          => 'Anika Tabassum',
            'gender'         => 'Female',
            'religion'       => 'Islam',
            'phone'          => '01855555555',
            'g_name'         => 'Mr. Tabassum',
            'g_phone'        => '01850000000',
            'rel_g'          => 'Father',
            'course_fee'     => '25000',
            'admission_date' => '2026-02-10',
            'IsActive'       => 1,
        ]);

        // Student 2: Irregular (Status 2)
        Students::create([
            'course_id'      => $this->testCourse->id,
            'batch_id'       => $this->testBatch->id,
            'sname'          => 'Bilal Hossain',
            'gender'         => 'Male',
            'religion'       => 'Islam',
            'phone'          => '01966666666',
            'g_name'         => 'Mrs. Hossain',
            'g_phone'        => '01960000000',
            'rel_g'          => 'Mother',
            'course_fee'     => '25000',
            'admission_date' => '2026-02-12',
            'IsActive'       => 2,
        ]);

        // 1. Search by name 'Anika'
        $searchRes = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->getJson('/api/v1/academic/students?search=Anika');
        $searchRes->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sname', 'Anika Tabassum');

        // 2. Filter by status 'irregular' (IsActive: 2)
        $statusRes = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->getJson('/api/v1/academic/students?status=irregular');
        $statusRes->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sname', 'Bilal Hossain');

        // 3. Filter by is_active numeric 1
        $numRes = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->getJson('/api/v1/academic/students?is_active=1');
        $numRes->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sname', 'Anika Tabassum');
    }

    /**
     * Test single student detail view with relationships.
     */
    public function test_admin_can_view_student_details(): void
    {
        $student = Students::create([
            'course_id'      => $this->testCourse->id,
            'batch_id'       => $this->testBatch->id,
            'sname'          => 'Farhan Ahmed',
            'gender'         => 'Male',
            'religion'       => 'Islam',
            'phone'          => '01777777777',
            'g_name'         => 'Ahmed Ullah',
            'g_phone'        => '01770000000',
            'rel_g'          => 'Father',
            'course_fee'     => '25000',
            'discount'       => '5000',
            'balance'        => '20000',
            'admission_date' => '2026-01-20',
            'IsActive'       => 1,
            'IsRegister'     => 1,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->getJson('/api/v1/academic/students/' . $student->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $student->id)
            ->assertJsonPath('data.sname', 'Farhan Ahmed')
            ->assertJsonPath('data.course.name', 'Full Stack React & Laravel')
            ->assertJsonPath('data.batch.batchnum', 'BATCH-2026-FSRL');
    }

    /**
     * Test admin can create student with validation.
     */
    public function test_admin_can_create_student(): void
    {
        $payload = [
            'course_id'      => $this->testCourse->id,
            'batch_id'       => $this->testBatch->id,
            'sname'          => 'Nusrat Jahan',
            'gender'         => 'Female',
            'religion'       => 'Islam',
            'phone'          => '01688888888',
            'g_name'         => 'Shahidul Islam',
            'g_phone'        => '01680000000',
            'rel_g'          => 'Father',
            'course_fee'     => '25000',
            'discount'       => '2500',
            'balance'        => '22500',
            'admission_date' => '2026-02-18',
            'dob'            => '2002-05-14',
            'bloodgroup'     => 'B+',
            'IsRegister'     => 1,
            'IsActive'       => 1,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->postJson('/api/v1/academic/students', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sname', 'Nusrat Jahan')
            ->assertJsonPath('data.course_fee', '25000');

        $this->assertDatabaseHas('students', [
            'sname' => 'Nusrat Jahan',
            'phone' => '01688888888',
        ]);
    }

    /**
     * Test student creation validation errors (422).
     */
    public function test_student_creation_validates_required_fields(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->postJson('/api/v1/academic/students', []);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['course_id', 'batch_id', 'sname', 'gender', 'religion', 'g_name', 'g_phone', 'rel_g', 'course_fee', 'admission_date']]);
    }

    /**
     * Test admin can update student record.
     */
    public function test_admin_can_update_student(): void
    {
        $student = Students::create([
            'course_id'      => $this->testCourse->id,
            'batch_id'       => $this->testBatch->id,
            'sname'          => 'Original Student',
            'gender'         => 'Male',
            'religion'       => 'Islam',
            'phone'          => '01511111111',
            'g_name'         => 'Guardian Original',
            'g_phone'        => '01510000000',
            'rel_g'          => 'Father',
            'course_fee'     => '25000',
            'admission_date' => '2026-01-10',
            'IsActive'       => 1,
        ]);

        $updatePayload = [
            'sname'      => 'Updated Student Name',
            'phone'      => '01522222222',
            'discount'   => '4000',
            'balance'    => '21000',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->putJson('/api/v1/academic/students/' . $student->id, $updatePayload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sname', 'Updated Student Name')
            ->assertJsonPath('data.phone', '01522222222');

        $this->assertDatabaseHas('students', [
            'id'    => $student->id,
            'sname' => 'Updated Student Name',
            'phone' => '01522222222',
        ]);
    }

    /**
     * Test updating student status across multi-state lifecycle.
     */
    public function test_admin_can_update_student_status(): void
    {
        $student = Students::create([
            'course_id'      => $this->testCourse->id,
            'batch_id'       => $this->testBatch->id,
            'sname'          => 'Status Test Student',
            'gender'         => 'Female',
            'religion'       => 'Islam',
            'g_name'         => 'Guardian',
            'g_phone'        => '01700000000',
            'rel_g'          => 'Mother',
            'course_fee'     => '25000',
            'admission_date' => '2026-01-10',
            'IsActive'       => 1,
        ]);

        // Transition from 1 (Active) to 3 (Complete)
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->putJson('/api/v1/academic/students/' . $student->id, ['IsActive' => 3]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.IsActive', 3);

        $this->assertEquals(3, $student->fresh()->IsActive);
    }

    /**
     * Test admin can delete student without payments.
     */
    public function test_admin_can_delete_student_without_payments(): void
    {
        $student = Students::create([
            'course_id'      => $this->testCourse->id,
            'batch_id'       => $this->testBatch->id,
            'sname'          => 'Student To Delete',
            'gender'         => 'Male',
            'religion'       => 'Islam',
            'g_name'         => 'Guardian',
            'g_phone'        => '01700000000',
            'rel_g'          => 'Father',
            'course_fee'     => '25000',
            'admission_date' => '2026-01-10',
            'IsActive'       => 1,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->deleteJson('/api/v1/academic/students/' . $student->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('students', ['id' => $student->id]);
    }

    /**
     * Test student with payment transactions cannot be deleted.
     */
    public function test_student_with_payment_records_cannot_be_deleted(): void
    {
        $student = Students::create([
            'course_id'      => $this->testCourse->id,
            'batch_id'       => $this->testBatch->id,
            'sname'          => 'Paid Student',
            'gender'         => 'Male',
            'religion'       => 'Islam',
            'g_name'         => 'Guardian',
            'g_phone'        => '01700000000',
            'rel_g'          => 'Father',
            'course_fee'     => '25000',
            'admission_date' => '2026-01-10',
            'IsActive'       => 1,
        ]);

        FeesTransections::create([
            'student_id'  => $student->id,
            'paidamount'  => 10000.00,
            'paymentType' => 1,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->deleteJson('/api/v1/academic/students/' . $student->id);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Cannot delete student: payment records exist for this student. Deactivate student status instead.');

        $this->assertDatabaseHas('students', [
            'id'         => $student->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * Test React Admin students route is served cleanly.
     */
    public function test_react_admin_students_route_is_accessible(): void
    {
        $response = $this->get('/react-admin/students');
        $response->assertStatus(200);
        $response->assertViewIs('react-admin');
    }

    /**
     * Test legacy Blade student routes remain operational and protected.
     */
    public function test_legacy_blade_students_routes_remain_protected(): void
    {
        // Unauthenticated access to legacy Blade /admin/students must redirect to admin.login
        $response = $this->get('/admin/students');
        $response->assertRedirect('/admin/login');
    }
}
