<?php

namespace Tests\Feature\Api\V1;

use App\Models\Admin;
use App\Models\Asset;
use App\Models\AssetCourse;
use App\Models\AssetRegistration;
use App\Models\AssetRegistrationFeedback;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Class AssetProjectApiTest
 *
 * Automated regression and functional test suite for Phase 2.7.4:
 * 1. ASSET / SEIP Projects & Showcases API
 * 2. Project Training Courses API
 * 3. Trainee Registrations & Intake API (Admin & Public)
 * 4. Assessment Feedbacks, Call Follow-up Milestones & Status Lifecycle Transitions
 * 5. Aggregated Program Reporting
 *
 * Runs exclusively in isolated in-memory SQLite (:memory:).
 *
 * @package Tests\Feature\Api\V1
 */
class AssetProjectApiTest extends TestCase
{
    protected Admin $admin;
    protected User $studentUser;

    protected string $adminToken;
    protected string $studentToken;

    protected AssetCourse $course1;
    protected AssetCourse $course2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('app.url', 'http://localhost');
        $this->app['url']->forceRootUrl('http://localhost');

        $this->app['config']->set('database.default', 'sqlite');
        $this->app['config']->set('database.connections.sqlite.database', ':memory:');

        $connection = DB::connection();
        if ($connection->getDriverName() !== 'sqlite' || $connection->getDatabaseName() !== ':memory:') {
            $this->fail('CRITICAL SAFETY CHECK: Test is not running on an isolated SQLite database!');
        }

        $this->createTestTables();
        $this->seedTestData();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('asset_registration_feedbacks');
        Schema::dropIfExists('asset_registrations');
        Schema::dropIfExists('asset_courses');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('admins');

        parent::tearDown();
    }

    protected function resetAuthGuards(): void
    {
        app('auth')->forgetGuards();
    }

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

        Schema::create('assets', function ($table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table->string('level')->nullable();
            $table->text('short_description')->nullable();
            $table->string('button_text')->nullable();
            $table->string('button_url')->nullable();
            $table->string('image')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('IsActive')->default(1)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('asset_courses', function ($table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('short_description');
            $table->string('level')->nullable();
            $table->string('icon')->nullable();
            $table->string('button_color')->nullable();
            $table->string('course_outline')->nullable();
            $table->string('photo')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->timestamps();
        });

        Schema::create('asset_registrations', function ($table) {
            $table->id();
            $table->string('course');
            $table->string('full_name');
            $table->string('father_name');
            $table->string('mother_name');
            $table->string('mobile');
            $table->string('whatsapp')->nullable();
            $table->string('call_type')->nullable();
            $table->string('email');
            $table->string('religion');
            $table->string('gender');
            $table->date('dob');
            $table->string('nid');
            $table->string('employment_status');
            $table->longText('address');
            $table->string('education');
            $table->string('board');
            $table->string('subject');
            $table->string('passing_year');
            $table->string('photo')->nullable();
            $table->string('registration_status')->default('Pending');
            $table->timestamps();
        });

        Schema::create('asset_registration_feedbacks', function ($table) {
            $table->id();
            $table->unsignedBigInteger('asset_registration_id');
            $table->unsignedTinyInteger('feedback_step');
            $table->longText('feedback');
            $table->timestamps();
        });
    }

    protected function seedTestData(): void
    {
        $this->admin = Admin::create([
            'name'     => 'Project Director Admin',
            'email'    => 'project.director@example.com',
            'password' => Hash::make('Secret123!'),
        ]);
        $this->adminToken = $this->admin->createToken('admin-token')->plainTextToken;

        $this->studentUser = User::create([
            'name'     => 'Regular User',
            'email'    => 'user@example.com',
            'password' => Hash::make('Secret123!'),
        ]);
        $this->studentToken = $this->studentUser->createToken('user-token')->plainTextToken;

        $this->course1 = AssetCourse::create([
            'name'              => 'Web Design & Development For Freelancing',
            'slug'              => 'web-design-and-development-for-freelancing',
            'short_description' => 'Comprehensive government certified full-stack web training',
            'level'             => '4',
            'button_color'      => '#071875',
            'status'            => 1,
        ]);

        $this->course2 = AssetCourse::create([
            'name'              => 'Graphic Design for Freelancing',
            'slug'              => 'graphic-design-for-freelancing',
            'short_description' => 'Professional visual branding and UI design curriculum',
            'level'             => '4',
            'button_color'      => '#071875',
            'status'            => 1,
        ]);
    }

    // =========================================================================
    // 1. AUTHENTICATION & AUTHORIZATION BARRIER TESTS
    // =========================================================================

    public function test_unauthenticated_requests_are_denied_for_protected_endpoints(): void
    {
        $this->resetAuthGuards();

        $routes = [
            ['GET', '/api/v1/projects/reports/summary'],
            ['GET', '/api/v1/projects/assets'],
            ['POST', '/api/v1/projects/assets'],
            ['GET', '/api/v1/projects/assets/1'],
            ['PUT', '/api/v1/projects/assets/1'],
            ['DELETE', '/api/v1/projects/assets/1'],
            ['GET', '/api/v1/projects/courses'],
            ['POST', '/api/v1/projects/courses'],
            ['GET', '/api/v1/projects/courses/1'],
            ['PUT', '/api/v1/projects/courses/1'],
            ['DELETE', '/api/v1/projects/courses/1'],
            ['GET', '/api/v1/projects/trainees'],
            ['POST', '/api/v1/projects/trainees'],
            ['GET', '/api/v1/projects/trainees/1'],
            ['PUT', '/api/v1/projects/trainees/1'],
            ['DELETE', '/api/v1/projects/trainees/1'],
            ['POST', '/api/v1/projects/trainees/1/status'],
            ['POST', '/api/v1/projects/trainees/1/call-type'],
            ['GET', '/api/v1/projects/trainees/1/feedbacks'],
            ['POST', '/api/v1/projects/trainees/1/feedbacks'],
        ];

        foreach ($routes as [$method, $uri]) {
            $response = $this->json($method, $uri);
            $this->assertEquals(
                401,
                $response->status(),
                "Protected route [{$method} {$uri}] should be 401 Unauthorized for unauthenticated requests."
            );
        }
    }

    public function test_non_admin_identity_is_forbidden_for_protected_endpoints(): void
    {
        $this->resetAuthGuards();

        $routes = [
            ['GET', '/api/v1/projects/reports/summary'],
            ['GET', '/api/v1/projects/assets'],
            ['POST', '/api/v1/projects/assets'],
            ['GET', '/api/v1/projects/courses'],
            ['POST', '/api/v1/projects/courses'],
            ['GET', '/api/v1/projects/trainees'],
            ['POST', '/api/v1/projects/trainees'],
            ['POST', '/api/v1/projects/trainees/1/status'],
            ['POST', '/api/v1/projects/trainees/1/call-type'],
        ];

        foreach ($routes as [$method, $uri]) {
            $response = $this->withHeader('Authorization', 'Bearer ' . $this->studentToken)
                ->json($method, $uri);

            $this->assertEquals(
                403,
                $response->status(),
                "Route [{$method} {$uri}] should be 403 Forbidden for non-admin token."
            );
        }
    }

    // =========================================================================
    // 2. PROJECT SHOWCASE / ENTITIES CRUD
    // =========================================================================

    public function test_can_create_project_entity(): void
    {
        $this->resetAuthGuards();

        $payload = [
            'title'             => 'ASSET Government Training Project',
            'level'             => 'National Skill Standard 4',
            'short_description' => 'World Bank & GoB supported skill acceleration',
            'button_text'       => 'Apply Online',
            'button_url'        => 'https://example.com/apply',
            'sort_order'        => 1,
            'IsActive'          => 1,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/projects/assets', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'ASSET Government Training Project')
            ->assertJsonPath('data.level', 'National Skill Standard 4');

        $this->assertDatabaseHas('assets', [
            'title' => 'ASSET Government Training Project',
        ]);
    }

    public function test_can_list_show_update_and_delete_project_entity(): void
    {
        $this->resetAuthGuards();

        $project = Asset::create([
            'title'             => 'SEIP Phase 2 Project',
            'level'             => 'Level 3',
            'short_description' => 'Tranche 2 Training Scheme',
            'sort_order'        => 2,
            'IsActive'          => 1,
        ]);

        // List
        $resList = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/projects/assets');
        $resList->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.data');

        // Show
        $resShow = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson("/api/v1/projects/assets/{$project->id}");
        $resShow->assertStatus(200)
            ->assertJsonPath('data.title', 'SEIP Phase 2 Project');

        // Update
        $resUpdate = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->putJson("/api/v1/projects/assets/{$project->id}", [
                'title' => 'SEIP Phase 2 Project Extended',
            ]);
        $resUpdate->assertStatus(200)
            ->assertJsonPath('data.title', 'SEIP Phase 2 Project Extended');

        // Delete
        $resDelete = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->deleteJson("/api/v1/projects/assets/{$project->id}");
        $resDelete->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('assets', ['id' => $project->id]);
    }

    // =========================================================================
    // 3. PROJECT TRAINING COURSES CRUD & DELETION GUARDS
    // =========================================================================

    public function test_can_list_courses_with_trainees_count_and_filters(): void
    {
        $this->resetAuthGuards();

        // Enroll a trainee in course1
        AssetRegistration::create([
            'course'            => $this->course1->name,
            'full_name'         => 'Hasan Mahmud',
            'father_name'       => 'Ali Mahmud',
            'mother_name'       => 'Fatema Begum',
            'mobile'            => '01711000111',
            'email'             => 'hasan@example.com',
            'religion'          => 'Islam',
            'gender'            => 'Male',
            'dob'               => '1998-05-12',
            'nid'               => '19981234567890123',
            'employment_status' => 'Unemployed',
            'address'           => 'Dhaka, Bangladesh',
            'education'         => 'HSC',
            'board'             => 'Dhaka',
            'subject'           => 'Science',
            'passing_year'      => '2018',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/projects/courses');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.data');

        // Check trainees count
        $items = collect($response->json('data.data'));
        $course1Data = $items->firstWhere('name', $this->course1->name);
        $this->assertEquals(1, $course1Data['registrations_count']);

        $course2Data = $items->firstWhere('name', $this->course2->name);
        $this->assertEquals(0, $course2Data['registrations_count']);
    }

    public function test_can_create_and_update_course_with_auto_slug(): void
    {
        $this->resetAuthGuards();

        $payload = [
            'name'              => 'Cybersecurity Fundamentals & Defense',
            'short_description' => 'Network defense and ethical penetration testing',
            'level'             => 'Level 4',
            'button_color'      => '#1A202C',
            'status'            => 1,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/projects/courses', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Cybersecurity Fundamentals & Defense')
            ->assertJsonPath('data.slug', 'cybersecurity-fundamentals-defense');

        $courseId = $response->json('data.id');

        // Update
        $resUpdate = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->putJson("/api/v1/projects/courses/{$courseId}", [
                'level' => 'Level 5 Expert',
            ]);

        $resUpdate->assertStatus(200)
            ->assertJsonPath('data.level', 'Level 5 Expert');
    }

    public function test_cannot_delete_course_with_active_trainee_registrations(): void
    {
        $this->resetAuthGuards();

        AssetRegistration::create([
            'course'            => $this->course1->name,
            'full_name'         => 'Nasir Uddin',
            'father_name'       => 'Kabir Uddin',
            'mother_name'       => 'Salma Begum',
            'mobile'            => '01811000222',
            'email'             => 'nasir@example.com',
            'religion'          => 'Islam',
            'gender'            => 'Male',
            'dob'               => '1999-07-20',
            'nid'               => '19991234567890124',
            'employment_status' => 'Unemployed',
            'address'           => 'Chittagong, Bangladesh',
            'education'         => 'Diploma in Engg',
            'board'             => 'BTEB',
            'subject'           => 'Computer',
            'passing_year'      => '2020',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->deleteJson("/api/v1/projects/courses/{$this->course1->id}");

        $response->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors', 'COURSE_HAS_ACTIVE_REGISTRATIONS');

        $this->assertDatabaseHas('asset_courses', ['id' => $this->course1->id]);
    }

    public function test_can_delete_course_without_active_trainee_registrations(): void
    {
        $this->resetAuthGuards();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->deleteJson("/api/v1/projects/courses/{$this->course2->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('asset_courses', ['id' => $this->course2->id]);
    }

    // =========================================================================
    // 4. TRAINEE INTAKE, DUPLICATE ENROLLMENT & PUBLIC REGISTRATION
    // =========================================================================

    public function test_can_enroll_trainee_via_admin_endpoint(): void
    {
        $this->resetAuthGuards();

        $payload = [
            'course'              => $this->course1->name,
            'full_name'           => 'Tariqul Islam',
            'father_name'         => 'Monirul Islam',
            'mother_name'         => 'Rashida Begum',
            'mobile'              => '01911223344',
            'whatsapp'            => '01911223344',
            'email'               => 'tariqul@example.com',
            'religion'            => 'Islam',
            'gender'              => 'Male',
            'dob'                 => '2000-01-15',
            'nid'                 => '20001234567890125',
            'employment_status'   => 'Job Seeker',
            'address'             => 'Mirpur, Dhaka',
            'education'           => 'B.Sc in CSE',
            'board'               => 'Dhaka',
            'subject'             => 'Computer Science',
            'passing_year'        => '2023',
            'registration_status' => 'Pending',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/projects/trainees', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.full_name', 'Tariqul Islam')
            ->assertJsonPath('data.course', $this->course1->name)
            ->assertJsonPath('data.registration_status', 'Pending');
    }

    public function test_public_online_registration_endpoint_works_without_authentication(): void
    {
        $this->resetAuthGuards();

        $payload = [
            'course'              => $this->course1->name,
            'full_name'           => 'Sumaiya Akter',
            'father_name'         => 'Anisur Rahman',
            'mother_name'         => 'Jahanara Begum',
            'mobile'              => '01511223344',
            'whatsapp'            => '01511223344',
            'email'               => 'sumaiya@example.com',
            'religion'            => 'Islam',
            'gender'              => 'Female',
            'dob'                 => '2001-03-25',
            'nid'                 => '20011234567890126',
            'employment_status'   => 'Student',
            'address'             => 'Uttara, Dhaka',
            'education'           => 'HSC',
            'board'               => 'Dhaka',
            'subject'             => 'Commerce',
            'passing_year'        => '2021',
        ];

        $response = $this->postJson('/api/v1/projects/public-register', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.full_name', 'Sumaiya Akter')
            ->assertJsonPath('data.registration_status', 'Pending');
    }

    public function test_duplicate_enrollment_prevention_guards_against_same_nid_or_mobile_for_course(): void
    {
        $this->resetAuthGuards();

        AssetRegistration::create([
            'course'            => $this->course1->name,
            'full_name'         => 'Sohail Rana',
            'father_name'       => 'Zahirul Rana',
            'mother_name'       => 'Rokeya Rana',
            'mobile'            => '01799887766',
            'email'             => 'sohail@example.com',
            'religion'          => 'Islam',
            'gender'            => 'Male',
            'dob'               => '1997-11-05',
            'nid'               => '19971234567890999',
            'employment_status' => 'Unemployed',
            'address'           => 'Gazipur, Dhaka',
            'education'         => 'BBA',
            'board'             => 'National University',
            'subject'           => 'Marketing',
            'passing_year'      => '2020',
        ]);

        // Attempting to register again with same NID for same course
        $duplicateNidPayload = [
            'course'              => $this->course1->name,
            'full_name'           => 'Sohail Rana Clone',
            'father_name'         => 'Zahirul Rana',
            'mother_name'         => 'Rokeya Rana',
            'mobile'              => '01711223399', // Different mobile
            'email'               => 'sohail.clone@example.com',
            'religion'            => 'Islam',
            'gender'              => 'Male',
            'dob'                 => '1997-11-05',
            'nid'                 => '19971234567890999', // Same NID!
            'employment_status'   => 'Unemployed',
            'address'             => 'Gazipur, Dhaka',
            'education'           => 'BBA',
            'board'               => 'National University',
            'subject'             => 'Marketing',
            'passing_year'        => '2020',
        ];

        $resDuplicateNid = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/projects/trainees', $duplicateNidPayload);

        $resDuplicateNid->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors', 'DUPLICATE_TRAINEE_ENROLLMENT');

        // Attempting to register again with same mobile for same course
        $duplicateMobilePayload = [
            'course'              => $this->course1->name,
            'full_name'           => 'Sohail Rana Brother',
            'father_name'         => 'Zahirul Rana',
            'mother_name'         => 'Rokeya Rana',
            'mobile'              => '01799887766', // Same mobile!
            'email'               => 'brother@example.com',
            'religion'            => 'Islam',
            'gender'              => 'Male',
            'dob'                 => '1999-11-05',
            'nid'                 => '19998888777766665', // Different NID
            'employment_status'   => 'Unemployed',
            'address'             => 'Gazipur, Dhaka',
            'education'           => 'HSC',
            'board'               => 'Dhaka',
            'subject'             => 'Arts',
            'passing_year'        => '2021',
        ];

        $resDuplicateMobile = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/projects/trainees', $duplicateMobilePayload);

        $resDuplicateMobile->assertStatus(409)
            ->assertJsonPath('errors', 'DUPLICATE_TRAINEE_ENROLLMENT');
    }

    public function test_can_filter_and_search_trainees(): void
    {
        $this->resetAuthGuards();

        AssetRegistration::create([
            'course'              => $this->course1->name,
            'full_name'           => 'Anik Roy',
            'father_name'         => 'Biplob Roy',
            'mother_name'         => 'Shampa Roy',
            'mobile'              => '01712345678',
            'email'               => 'anik@example.com',
            'religion'            => 'Hinduism',
            'gender'              => 'Male',
            'dob'                 => '1996-08-14',
            'nid'                 => '19961122334455667',
            'employment_status'   => 'Unemployed',
            'address'             => 'Khulna, Bangladesh',
            'education'           => 'B.Sc',
            'board'               => 'Khulna',
            'subject'             => 'Physics',
            'passing_year'        => '2019',
            'registration_status' => 'Qualified',
            'call_type'           => '2nd call',
        ]);

        AssetRegistration::create([
            'course'              => $this->course2->name,
            'full_name'           => 'Farzana Haque',
            'father_name'         => 'Enamul Haque',
            'mother_name'         => 'Nasima Haque',
            'mobile'              => '01812345678',
            'email'               => 'farzana@example.com',
            'religion'            => 'Islam',
            'gender'              => 'Female',
            'dob'                 => '1998-12-10',
            'nid'                 => '19981122334455668',
            'employment_status'   => 'Housewife',
            'address'             => 'Rajshahi, Bangladesh',
            'education'           => 'BA',
            'board'               => 'Rajshahi',
            'subject'             => 'English',
            'passing_year'        => '2020',
            'registration_status' => 'Pending',
            'call_type'           => '1st call',
        ]);

        // Filter by course
        $resCourse = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson("/api/v1/projects/trainees?course=" . urlencode($this->course1->name));
        $resCourse->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.full_name', 'Anik Roy');

        // Filter by status
        $resStatus = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/projects/trainees?registration_status=Qualified');
        $resStatus->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.full_name', 'Anik Roy');

        // Search by name
        $resSearch = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/projects/trainees?search=Farzana');
        $resSearch->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.full_name', 'Farzana Haque');
    }

    // =========================================================================
    // 5. STATUS LIFECYCLE, CALL MILESTONES & 5-STEP EVALUATION FEEDBACK
    // =========================================================================

    public function test_can_transition_trainee_qualification_status(): void
    {
        $this->resetAuthGuards();

        $trainee = AssetRegistration::create([
            'course'              => $this->course1->name,
            'full_name'           => 'Mamunur Rashid',
            'father_name'         => 'Harunur Rashid',
            'mother_name'         => 'Momtaz Begum',
            'mobile'              => '01711220033',
            'whatsapp'            => '01711220033',
            'email'               => 'mamun@example.com',
            'religion'            => 'Islam',
            'gender'              => 'Male',
            'dob'                 => '1999-04-10',
            'nid'                 => '19991234000011112',
            'employment_status'   => 'Unemployed',
            'address'             => 'Sylhet, Bangladesh',
            'education'           => 'HSC',
            'board'               => 'Sylhet',
            'subject'             => 'Science',
            'passing_year'        => '2019',
            'registration_status' => 'Pending',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/v1/projects/trainees/{$trainee->id}/status", [
                'registration_status' => 'Qualified',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.registration_status', 'Qualified');

        $this->assertEquals('Qualified', $trainee->fresh()->registration_status);
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $this->resetAuthGuards();

        $trainee = AssetRegistration::create([
            'course'              => $this->course1->name,
            'full_name'           => 'Kamal Hossain',
            'father_name'         => 'Jamal Hossain',
            'mother_name'         => 'Ayesha Begum',
            'mobile'              => '01811220044',
            'email'               => 'kamal@example.com',
            'religion'            => 'Islam',
            'gender'              => 'Male',
            'dob'                 => '1998-02-18',
            'nid'                 => '19981234000011113',
            'employment_status'   => 'Unemployed',
            'address'             => 'Barisal, Bangladesh',
            'education'           => 'BA',
            'board'               => 'Barisal',
            'subject'             => 'History',
            'passing_year'        => '2019',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/v1/projects/trainees/{$trainee->id}/status", [
                'registration_status' => 'NonExistentStatus',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['registration_status']);
    }

    public function test_can_update_call_follow_up_milestone(): void
    {
        $this->resetAuthGuards();

        $trainee = AssetRegistration::create([
            'course'              => $this->course1->name,
            'full_name'           => 'Sadia Jahan',
            'father_name'         => 'Jasim Uddin',
            'mother_name'         => 'Nazma Begum',
            'mobile'              => '01911220055',
            'email'               => 'sadia@example.com',
            'religion'            => 'Islam',
            'gender'              => 'Female',
            'dob'                 => '2000-09-09',
            'nid'                 => '20001234000011114',
            'employment_status'   => 'Student',
            'address'             => 'Dhaka, Bangladesh',
            'education'           => 'HSC',
            'board'               => 'Dhaka',
            'subject'             => 'Science',
            'passing_year'        => '2020',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/v1/projects/trainees/{$trainee->id}/call-type", [
                'call_type' => '2nd call',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.call_type', '2nd call');

        $this->assertEquals('2nd call', $trainee->fresh()->call_type);
    }

    public function test_can_store_and_retrieve_multistep_assessment_feedbacks(): void
    {
        $this->resetAuthGuards();

        $trainee = AssetRegistration::create([
            'course'              => $this->course1->name,
            'full_name'           => 'Rubel Ahmed',
            'father_name'         => 'Mustafa Ahmed',
            'mother_name'         => 'Rehana Ahmed',
            'mobile'              => '01611220066',
            'email'               => 'rubel@example.com',
            'religion'            => 'Islam',
            'gender'              => 'Male',
            'dob'                 => '1997-06-30',
            'nid'                 => '19971234000011115',
            'employment_status'   => 'Job Seeker',
            'address'             => 'Comilla, Bangladesh',
            'education'           => 'Diploma',
            'board'               => 'BTEB',
            'subject'             => 'Electronics',
            'passing_year'        => '2019',
        ]);

        // Step 1: Initial screening call
        $resStep1 = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/v1/projects/trainees/{$trainee->id}/feedbacks", [
                'feedback_step' => 1,
                'feedback'      => 'Candidate expressed strong interest; has personal laptop.',
            ]);
        $resStep1->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.feedback_step', 1);

        // Step 2: Document verification
        $resStep2 = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/v1/projects/trainees/{$trainee->id}/feedbacks", [
                'feedback_step' => 2,
                'feedback'      => 'NID and diploma certificate verified successfully.',
            ]);
        $resStep2->assertStatus(201);

        // Update Step 1 (upsert test)
        $resStep1Update = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/v1/projects/trainees/{$trainee->id}/feedbacks", [
                'feedback_step' => 1,
                'feedback'      => 'Candidate confirmed personal laptop and stable internet connection.',
            ]);
        $resStep1Update->assertStatus(201);

        // Retrieve feedbacks list
        $resFeedbacks = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson("/api/v1/projects/trainees/{$trainee->id}/feedbacks");

        $resFeedbacks->assertStatus(200)
            ->assertJsonCount(2, 'data');

        $this->assertEquals(2, AssetRegistrationFeedback::where('asset_registration_id', $trainee->id)->count());
        $this->assertEquals(
            'Candidate confirmed personal laptop and stable internet connection.',
            AssetRegistrationFeedback::where('asset_registration_id', $trainee->id)
                ->where('feedback_step', 1)
                ->value('feedback')
        );
    }

    // =========================================================================
    // 6. PROGRAM SUMMARY REPORTING
    // =========================================================================

    public function test_program_summary_report_computes_aggregated_statistics(): void
    {
        $this->resetAuthGuards();

        AssetRegistration::create([
            'course'              => $this->course1->name,
            'full_name'           => 'Trainee 1',
            'father_name'         => 'Father 1',
            'mother_name'         => 'Mother 1',
            'mobile'              => '01700000001',
            'email'               => 't1@example.com',
            'religion'            => 'Islam',
            'gender'              => 'Male',
            'dob'                 => '2000-01-01',
            'nid'                 => '20000000000000001',
            'employment_status'   => 'Unemployed',
            'address'             => 'Dhaka',
            'education'           => 'HSC',
            'board'               => 'Dhaka',
            'subject'             => 'Science',
            'passing_year'        => '2020',
            'registration_status' => 'Qualified',
            'call_type'           => '2nd call',
        ]);

        AssetRegistration::create([
            'course'              => $this->course1->name,
            'full_name'           => 'Trainee 2',
            'father_name'         => 'Father 2',
            'mother_name'         => 'Mother 2',
            'mobile'              => '01700000002',
            'email'               => 't2@example.com',
            'religion'            => 'Islam',
            'gender'              => 'Female',
            'dob'                 => '2001-01-01',
            'nid'                 => '20000000000000002',
            'employment_status'   => 'Unemployed',
            'address'             => 'Dhaka',
            'education'           => 'HSC',
            'board'               => 'Dhaka',
            'subject'             => 'Science',
            'passing_year'        => '2021',
            'registration_status' => 'Converted',
            'call_type'           => '3rd call',
        ]);

        AssetRegistration::create([
            'course'              => $this->course2->name,
            'full_name'           => 'Trainee 3',
            'father_name'         => 'Father 3',
            'mother_name'         => 'Mother 3',
            'mobile'              => '01700000003',
            'email'               => 't3@example.com',
            'religion'            => 'Islam',
            'gender'              => 'Male',
            'dob'                 => '1999-01-01',
            'nid'                 => '20000000000000003',
            'employment_status'   => 'Unemployed',
            'address'             => 'Dhaka',
            'education'           => 'B.Sc',
            'board'               => 'Dhaka',
            'subject'             => 'BBA',
            'passing_year'        => '2021',
            'registration_status' => 'Pending',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/projects/reports/summary');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_trainees', 3)
            ->assertJsonPath('data.active_courses', 2)
            ->assertJsonPath('data.status_breakdown.Qualified', 1)
            ->assertJsonPath('data.status_breakdown.Converted', 1)
            ->assertJsonPath('data.status_breakdown.Pending', 1)
            ->assertJsonPath('data.qualification_rate', 66.67);
    }
}
