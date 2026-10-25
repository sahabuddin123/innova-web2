<?php

namespace Tests\Feature\Api\V1;

use App\Models\Admin;
use App\Models\Batchs;
use App\Models\Certificate_icbd;
use App\Models\Certificates;
use App\Models\Courses;
use App\Models\Session;
use App\Models\Students;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Class CertificateApiTest
 *
 * Automated regression and functional test suite for Phase 2.8:
 * 1. Institutional (ICBD) Certificate Issuance, Details, Update, Delete
 * 2. Student Eligibility Assessment & Duplicate Prevention
 * 3. Safe, Unique Certificate Number Generation (ICBDxxxx)
 * 4. QR Code Generation, Print HTML & DomPDF Download Integration
 * 5. Public Certificate Verification with Strict Data Minimization (Privacy Protection)
 * 6. Revoked and Non-existent Certificate Handling
 * 7. BTEB Board Certificate Management & Public Verification
 * 8. Aggregated Certificate Summary Reporting
 *
 * Runs exclusively in isolated in-memory SQLite (:memory:).
 *
 * @package Tests\Feature\Api\V1
 */
class CertificateApiTest extends TestCase
{
    protected Admin $admin;
    protected User $studentUser;

    protected string $adminToken;
    protected string $studentToken;

    protected Courses $course1;
    protected Courses $course2;
    protected Batchs $batch1;
    protected Session $session1;
    protected Students $student1;
    protected Students $student2;

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
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('certificates_icbd');
        Schema::dropIfExists('students');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('batchs');
        Schema::dropIfExists('courses');
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

        Schema::create('courses', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('courseduration')->nullable();
            $table->string('totalmoduls')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('batchs', function ($table) {
            $table->id();
            $table->string('batchnum');
            $table->integer('IsActive')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sessions', function ($table) {
            $table->id();
            $table->string('sessionName');
            $table->integer('IsActive')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('students', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->string('sname');
            $table->string('phone')->nullable();
            $table->string('nid')->nullable();
            $table->decimal('balance', 10, 2)->default(0.00);
            $table->integer('IsActive')->default(1);
            $table->integer('IsRegister')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('certificates_icbd', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('batch_id');
            $table->unsignedBigInteger('course_id');
            $table->string('certificate_no', 100)->nullable()->unique();
            $table->integer('theory_hours')->nullable()->default(0);
            $table->integer('practical_hours')->nullable()->default(0);
            $table->integer('intern_hours')->nullable()->default(0);
            $table->string('result', 50)->nullable();
            $table->date('issue_date')->nullable();
            $table->string('certificate_receiver', 150)->nullable();
            $table->date('delivery_date')->nullable();
            $table->tinyInteger('is_active')->nullable()->default(1);
            $table->string('cimage')->nullable();
            $table->string('dimage')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('certificates', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('session_id');
            $table->string('regNo')->nullable();
            $table->string('cno')->nullable();
            $table->date('pDate')->nullable();
            $table->string('recevier')->nullable();
            $table->string('cimage')->nullable();
            $table->string('dimage')->nullable();
            $table->string('Result')->nullable();
            $table->date('dDate')->nullable();
            $table->string('IsActive')->nullable()->default('1');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    protected function seedTestData(): void
    {
        $this->admin = Admin::create([
            'name'     => 'System Admin',
            'email'    => 'admin@innovacomputersbd.com',
            'password' => Hash::make('password123'),
        ]);

        $this->studentUser = User::create([
            'name'     => 'Student User',
            'email'    => 'student@innovacomputersbd.com',
            'password' => Hash::make('password123'),
        ]);

        $this->adminToken = $this->admin->createToken('admin-token')->plainTextToken;
        $this->studentToken = $this->studentUser->createToken('student-token')->plainTextToken;

        $this->course1 = Courses::create([
            'name'           => 'Web & Software Development',
            'courseduration' => '6',
            'totalmoduls'    => '12',
        ]);

        $this->course2 = Courses::create([
            'name'           => 'Graphic & UI/UX Design',
            'courseduration' => '3',
            'totalmoduls'    => '8',
        ]);

        $this->batch1 = Batchs::create([
            'batchnum' => 'WD-B01',
            'IsActive' => 1,
        ]);

        $this->session1 = Session::create([
            'sessionName' => 'January - June 2026',
            'IsActive'    => 1,
        ]);

        $this->student1 = Students::create([
            'sname'      => 'Md. Tanvir Hossain',
            'phone'      => '01711223344',
            'nid'        => '19951234567890123',
            'course_id'  => $this->course1->id,
            'batch_id'   => $this->batch1->id,
            'balance'    => 0.00,
            'IsActive'   => 1,
            'IsRegister' => 1,
        ]);

        $this->student2 = Students::create([
            'sname'      => 'Fatima Akhter',
            'phone'      => '01811223344',
            'nid'        => '19981234567890124',
            'course_id'  => $this->course2->id,
            'batch_id'   => $this->batch1->id,
            'balance'    => 500.00,
            'IsActive'   => 1,
            'IsRegister' => 1,
        ]);
    }

    public function test_unauthenticated_requests_are_denied_for_protected_certificate_endpoints(): void
    {
        $this->resetAuthGuards();

        $routes = [
            ['GET', '/api/v1/certificates'],
            ['POST', '/api/v1/certificates'],
            ['GET', '/api/v1/certificates/1'],
            ['PUT', '/api/v1/certificates/1'],
            ['DELETE', '/api/v1/certificates/1'],
            ['POST', '/api/v1/certificates/1/status'],
            ['GET', '/api/v1/certificates/1/qr'],
            ['GET', '/api/v1/certificates/1/print'],
            ['GET', '/api/v1/certificates/1/pdf'],
            ['GET', '/api/v1/certificates/eligibility/1'],
            ['GET', '/api/v1/certificates/reports/summary'],
            ['GET', '/api/v1/certificates/bteb'],
            ['POST', '/api/v1/certificates/bteb'],
            ['GET', '/api/v1/certificates/bteb/1'],
            ['PUT', '/api/v1/certificates/bteb/1'],
            ['DELETE', '/api/v1/certificates/bteb/1'],
        ];

        foreach ($routes as [$method, $uri]) {
            $response = $this->json($method, $uri);
            $response->assertStatus(401)
                     ->assertJson(['message' => 'Unauthenticated.']);
        }
    }

    public function test_non_admin_identity_is_forbidden_for_protected_certificate_endpoints(): void
    {
        $this->resetAuthGuards();

        $routes = [
            ['GET', '/api/v1/certificates'],
            ['POST', '/api/v1/certificates'],
            ['GET', '/api/v1/certificates/eligibility/1'],
            ['GET', '/api/v1/certificates/reports/summary'],
            ['GET', '/api/v1/certificates/bteb'],
        ];

        foreach ($routes as [$method, $uri]) {
            $response = $this->withHeaders([
                'Authorization' => "Bearer {$this->studentToken}",
            ])->json($method, $uri);

            $response->assertStatus(403)
                     ->assertJson([
                         'success' => false,
                         'message' => 'Forbidden: Admin access required',
                     ]);
        }
    }

    public function test_student_eligibility_assessment_for_eligible_student(): void
    {
        $this->resetAuthGuards();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->json('GET', "/api/v1/certificates/eligibility/{$this->student1->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data'    => [
                         'eligible'             => true,
                         'can_issue_icbd'       => true,
                         'can_issue_bteb'       => true,
                         'has_outstanding_dues' => false,
                         'student'              => [
                             'id'   => $this->student1->id,
                             'name' => 'Md. Tanvir Hossain',
                         ],
                     ],
                 ]);
    }

    public function test_student_eligibility_assessment_detects_outstanding_dues_and_inactive_status(): void
    {
        $this->resetAuthGuards();

        // student2 has balance of 500.00
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->json('GET', "/api/v1/certificates/eligibility/{$this->student2->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data'    => [
                         'eligible'             => true,
                         'has_outstanding_dues' => true,
                     ],
                 ]);

        // Now deactivate student2
        $this->student2->update(['IsActive' => 0]);

        $response2 = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->json('GET', "/api/v1/certificates/eligibility/{$this->student2->id}");

        $response2->assertStatus(200)
                  ->assertJson([
                      'success' => true,
                      'data'    => [
                          'eligible'       => false,
                          'can_issue_icbd' => false,
                          'can_issue_bteb' => false,
                      ],
                  ]);
    }

    public function test_can_issue_icbd_certificate_and_auto_generates_unique_certificate_no(): void
    {
        $this->resetAuthGuards();

        $payload = [
            'student_id'      => $this->student1->id,
            'course_id'       => $this->course1->id,
            'batch_id'        => $this->batch1->id,
            'theory_hours'    => 80,
            'practical_hours' => 120,
            'intern_hours'    => 40,
            'result'          => 'A+',
            'issue_date'      => '2026-06-30',
            'is_active'       => Certificate_icbd::STATUS_DELIVERED,
        ];

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->json('POST', '/api/v1/certificates', $payload);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Certificate issued successfully.',
                     'data'    => [
                         'student_id'      => $this->student1->id,
                         'course_id'       => $this->course1->id,
                         'batch_id'        => $this->batch1->id,
                         'certificate_no'  => 'ICBD0001',
                         'result'          => 'A+',
                         'total_hours'     => 240,
                         'status_label'    => 'Delivered',
                     ],
                 ]);

        $this->assertDatabaseHas('certificates_icbd', [
            'student_id'     => $this->student1->id,
            'certificate_no' => 'ICBD0001',
            'result'         => 'A+',
        ]);

        // Student's IsRegister should now be 2
        $this->assertEquals(2, $this->student1->fresh()->IsRegister);
    }

    public function test_duplicate_issuance_prevention_guards_against_same_student_and_course(): void
    {
        $this->resetAuthGuards();

        // Create first certificate
        Certificate_icbd::create([
            'student_id'     => $this->student1->id,
            'course_id'      => $this->course1->id,
            'batch_id'       => $this->batch1->id,
            'certificate_no' => 'ICBD0001',
            'is_active'      => Certificate_icbd::STATUS_DELIVERED,
        ]);

        // Attempting to create duplicate certificate for same student and course
        $payload = [
            'student_id' => $this->student1->id,
            'course_id'  => $this->course1->id,
            'batch_id'   => $this->batch1->id,
            'is_active'  => Certificate_icbd::STATUS_DELIVERED,
        ];

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->json('POST', '/api/v1/certificates', $payload);

        $response->assertStatus(409)
                 ->assertJson([
                     'success' => false,
                     'errors'  => 'DUPLICATE_CERTIFICATE_ISSUANCE',
                 ]);
    }

    public function test_can_list_certificates_with_filters_search_and_pagination(): void
    {
        $this->resetAuthGuards();

        Certificate_icbd::create([
            'student_id'     => $this->student1->id,
            'course_id'      => $this->course1->id,
            'batch_id'       => $this->batch1->id,
            'certificate_no' => 'ICBD0001',
            'result'         => 'A+',
            'is_active'      => Certificate_icbd::STATUS_DELIVERED,
        ]);

        Certificate_icbd::create([
            'student_id'     => $this->student2->id,
            'course_id'      => $this->course2->id,
            'batch_id'       => $this->batch1->id,
            'certificate_no' => 'ICBD0002',
            'result'         => 'A',
            'is_active'      => Certificate_icbd::STATUS_PROCESSING,
        ]);

        // Filter by course_id
        $respCourse = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->json('GET', '/api/v1/certificates', ['course_id' => $this->course1->id]);

        $respCourse->assertStatus(200);
        $this->assertCount(1, $respCourse->json('data.data'));
        $this->assertEquals('ICBD0001', $respCourse->json('data.data.0.certificate_no'));

        // Filter by status
        $respStatus = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->json('GET', '/api/v1/certificates', ['is_active' => Certificate_icbd::STATUS_PROCESSING]);

        $respStatus->assertStatus(200);
        $this->assertCount(1, $respStatus->json('data.data'));
        $this->assertEquals('ICBD0002', $respStatus->json('data.data.0.certificate_no'));

        // Search by student name
        $respSearch = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->json('GET', '/api/v1/certificates', ['search' => 'Tanvir']);

        $respSearch->assertStatus(200);
        $this->assertCount(1, $respSearch->json('data.data'));
        $this->assertEquals('ICBD0001', $respSearch->json('data.data.0.certificate_no'));
    }

    public function test_can_view_certificate_details_with_appends(): void
    {
        $this->resetAuthGuards();

        $cert = Certificate_icbd::create([
            'student_id'      => $this->student1->id,
            'course_id'       => $this->course1->id,
            'batch_id'        => $this->batch1->id,
            'certificate_no'  => 'ICBD0001',
            'theory_hours'    => 50,
            'practical_hours' => 70,
            'intern_hours'    => 20,
            'result'          => 'A',
            'is_active'       => Certificate_icbd::STATUS_DELIVERED,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->json('GET', "/api/v1/certificates/{$cert->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data'    => [
                         'id'               => $cert->id,
                         'certificate_no'   => 'ICBD0001',
                         'total_hours'      => 140,
                         'status_label'     => 'Delivered',
                         'qr_code_url'      => url("/api/v1/certificates/{$cert->id}/qr"),
                         'pdf_download_url' => url("/api/v1/certificates/{$cert->id}/pdf"),
                         'print_url'        => url("/api/v1/certificates/{$cert->id}/print"),
                     ],
                 ]);
    }

    public function test_can_update_certificate_metadata(): void
    {
        $this->resetAuthGuards();

        $cert = Certificate_icbd::create([
            'student_id'      => $this->student1->id,
            'course_id'       => $this->course1->id,
            'batch_id'        => $this->batch1->id,
            'certificate_no'  => 'ICBD0001',
            'theory_hours'    => 50,
            'practical_hours' => 50,
            'result'          => 'B+',
            'is_active'       => Certificate_icbd::STATUS_PROCESSING,
        ]);

        $updatePayload = [
            'theory_hours'         => 60,
            'practical_hours'      => 90,
            'result'               => 'A',
            'certificate_receiver' => 'Md. Tanvir Hossain',
            'is_active'            => Certificate_icbd::STATUS_DELIVERED,
        ];

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->json('PUT', "/api/v1/certificates/{$cert->id}", $updatePayload);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data'    => [
                         'theory_hours'         => 60,
                         'practical_hours'      => 90,
                         'result'               => 'A',
                         'certificate_receiver' => 'Md. Tanvir Hossain',
                         'is_active'            => Certificate_icbd::STATUS_DELIVERED,
                     ],
                 ]);
    }

    public function test_can_update_certificate_status(): void
    {
        $this->resetAuthGuards();

        $cert = Certificate_icbd::create([
            'student_id'     => $this->student1->id,
            'course_id'      => $this->course1->id,
            'batch_id'       => $this->batch1->id,
            'certificate_no' => 'ICBD0001',
            'is_active'      => Certificate_icbd::STATUS_PROCESSING,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->json('POST', "/api/v1/certificates/{$cert->id}/status", [
            'is_active' => Certificate_icbd::STATUS_REVOKED,
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data'    => [
                         'is_active'    => Certificate_icbd::STATUS_REVOKED,
                         'status_label' => 'Revoked',
                     ],
                 ]);

        $this->assertEquals(Certificate_icbd::STATUS_REVOKED, $cert->fresh()->is_active);
    }

    public function test_can_delete_certificate_and_resets_student_registration_flag(): void
    {
        $this->resetAuthGuards();

        $this->student1->update(['IsRegister' => 2]);

        $cert = Certificate_icbd::create([
            'student_id'     => $this->student1->id,
            'course_id'      => $this->course1->id,
            'batch_id'       => $this->batch1->id,
            'certificate_no' => 'ICBD0001',
            'is_active'      => Certificate_icbd::STATUS_DELIVERED,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->json('DELETE', "/api/v1/certificates/{$cert->id}");

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);

        $this->assertSoftDeleted('certificates_icbd', ['id' => $cert->id]);
        $this->assertEquals(1, $this->student1->fresh()->IsRegister);
    }

    public function test_can_generate_qr_code_for_verification_url(): void
    {
        $this->resetAuthGuards();

        $cert = Certificate_icbd::create([
            'student_id'     => $this->student1->id,
            'course_id'      => $this->course1->id,
            'batch_id'       => $this->batch1->id,
            'certificate_no' => 'ICBD0001',
            'is_active'      => Certificate_icbd::STATUS_DELIVERED,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->json('GET', "/api/v1/certificates/{$cert->id}/qr");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data'    => [
                         'certificate_no'   => 'ICBD0001',
                         'verification_url' => url('/certifiocate-verify/ICBD0001'),
                         'format'           => 'svg',
                     ],
                 ]);

        $this->assertNotNull($response->json('data.qr_data_uri'));
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $response->json('data.qr_data_uri'));
    }

    public function test_can_render_print_html_view(): void
    {
        $this->resetAuthGuards();

        $cert = Certificate_icbd::create([
            'student_id'      => $this->student1->id,
            'course_id'       => $this->course1->id,
            'batch_id'        => $this->batch1->id,
            'certificate_no'  => 'ICBD0001',
            'theory_hours'    => 80,
            'practical_hours' => 120,
            'intern_hours'    => 40,
            'result'          => 'A+',
            'issue_date'      => '2026-06-30',
            'is_active'       => Certificate_icbd::STATUS_DELIVERED,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->get("/api/v1/certificates/{$cert->id}/print");

        $response->assertStatus(200);
        $this->assertStringContainsString('text/html', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('ICBD0001', $response->getContent());
        $this->assertStringContainsString('Md. Tanvir Hossain', $response->getContent());
    }

    public function test_can_generate_and_download_pdf(): void
    {
        $this->resetAuthGuards();

        $cert = Certificate_icbd::create([
            'student_id'      => $this->student1->id,
            'course_id'       => $this->course1->id,
            'batch_id'        => $this->batch1->id,
            'certificate_no'  => 'ICBD0001',
            'theory_hours'    => 80,
            'practical_hours' => 120,
            'intern_hours'    => 40,
            'result'          => 'A+',
            'issue_date'      => '2026-06-30',
            'is_active'       => Certificate_icbd::STATUS_DELIVERED,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->get("/api/v1/certificates/{$cert->id}/pdf");

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('certificate_ICBD0001.pdf', $response->headers->get('Content-Disposition'));
        $this->assertGreaterThan(1000, strlen($response->getContent()));
    }

    public function test_public_verification_for_valid_certificate_with_strict_data_minimization(): void
    {
        $this->resetAuthGuards();

        Certificate_icbd::create([
            'student_id'      => $this->student1->id,
            'course_id'       => $this->course1->id,
            'batch_id'        => $this->batch1->id,
            'certificate_no'  => 'ICBD0001',
            'theory_hours'    => 80,
            'practical_hours' => 120,
            'intern_hours'    => 40,
            'result'          => 'A+',
            'issue_date'      => '2026-06-30',
            'is_active'       => Certificate_icbd::STATUS_DELIVERED,
        ]);

        // Public verification requires NO authentication!
        $response = $this->json('GET', '/api/v1/certificates/verify/ICBD0001');

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data'    => [
                         'is_valid'         => true,
                         'status'           => 'Delivered',
                         'certificate_type' => 'Institutional (ICBD)',
                         'certificate_no'   => 'ICBD0001',
                         'student_name'     => 'Md. Tanvir Hossain',
                         'course_name'      => 'Web & Software Development',
                         'result'           => 'A+',
                         'curriculum_hours' => [
                             'classroom_theory'   => 80,
                             'practical_lab'      => 120,
                             'internship'         => 40,
                             'total_course_hours' => 240,
                         ],
                     ],
                 ]);

        // Strict data minimization: Ensure private student PII is NEVER exposed publicly!
        $responseData = $response->json('data');
        $this->assertArrayNotHasKey('phone', $responseData);
        $this->assertArrayNotHasKey('nid', $responseData);
        $this->assertArrayNotHasKey('address', $responseData);
        $this->assertArrayNotHasKey('f_name', $responseData);
        $this->assertArrayNotHasKey('f_phone', $responseData);
        $this->assertArrayNotHasKey('balance', $responseData);
    }

    public function test_public_verification_for_revoked_certificate_returns_revoked_status(): void
    {
        $this->resetAuthGuards();

        Certificate_icbd::create([
            'student_id'     => $this->student1->id,
            'course_id'      => $this->course1->id,
            'batch_id'       => $this->batch1->id,
            'certificate_no' => 'ICBD0001',
            'is_active'      => Certificate_icbd::STATUS_REVOKED,
        ]);

        $response = $this->json('GET', '/api/v1/certificates/verify/ICBD0001');

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data'    => [
                         'is_valid'       => false,
                         'status'         => 'Revoked',
                         'certificate_no' => 'ICBD0001',
                     ],
                 ]);
    }

    public function test_public_verification_for_nonexistent_certificate_returns_404(): void
    {
        $this->resetAuthGuards();

        $response = $this->json('GET', '/api/v1/certificates/verify/ICBD9999');

        $response->assertStatus(404)
                 ->assertJson([
                     'success' => false,
                     'errors'  => 'CERTIFICATE_NOT_FOUND',
                 ]);
    }

    public function test_can_record_list_update_and_delete_bteb_certificate(): void
    {
        $this->resetAuthGuards();

        $storePayload = [
            'student_id' => $this->student1->id,
            'session_id' => $this->session1->id,
            'regNo'      => 'REG-2026-001',
            'cno'        => 'BTEB-778899',
            'pDate'      => '2026-06-15',
            'Result'     => 'A+',
            'recevier'   => 'Md. Tanvir Hossain',
            'IsActive'   => 1,
        ];

        // Store
        $storeResp = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->json('POST', '/api/v1/certificates/bteb', $storePayload);

        $storeResp->assertStatus(201)
                  ->assertJson([
                      'success' => true,
                      'data'    => [
                          'regNo'    => 'REG-2026-001',
                          'cno'      => 'BTEB-778899',
                          'Result'   => 'A+',
                          'IsActive' => 1,
                      ],
                  ]);

        $certId = $storeResp->json('data.id');

        // List
        $listResp = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->json('GET', '/api/v1/certificates/bteb');

        $listResp->assertStatus(200);
        $this->assertCount(1, $listResp->json('data.data'));

        // Update
        $updateResp = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->json('PUT', "/api/v1/certificates/bteb/{$certId}", [
            'Result' => 'A',
        ]);

        $updateResp->assertStatus(200)
                   ->assertJson([
                       'success' => true,
                       'data'    => ['Result' => 'A'],
                   ]);

        // Delete
        $deleteResp = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->json('DELETE', "/api/v1/certificates/bteb/{$certId}");

        $deleteResp->assertStatus(200);
        $this->assertSoftDeleted('certificates', ['id' => $certId]);
    }

    public function test_duplicate_bteb_certificate_prevention_guards_against_same_student_and_session(): void
    {
        $this->resetAuthGuards();

        Certificates::create([
            'student_id' => $this->student1->id,
            'session_id' => $this->session1->id,
            'regNo'      => 'REG-2026-001',
            'cno'        => 'BTEB-778899',
            'IsActive'   => 1,
        ]);

        $payload = [
            'student_id' => $this->student1->id,
            'session_id' => $this->session1->id,
            'regNo'      => 'REG-2026-002',
            'cno'        => 'BTEB-778900',
            'IsActive'   => 1,
        ];

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->json('POST', '/api/v1/certificates/bteb', $payload);

        $response->assertStatus(409)
                 ->assertJson([
                     'success' => false,
                     'errors'  => 'DUPLICATE_BTEB_CERTIFICATE',
                 ]);
    }

    public function test_public_verification_resolves_bteb_certificate_by_cno_or_regNo(): void
    {
        $this->resetAuthGuards();

        Certificates::create([
            'student_id' => $this->student1->id,
            'session_id' => $this->session1->id,
            'regNo'      => 'REG-2026-001',
            'cno'        => 'BTEB-778899',
            'Result'     => 'A+',
            'pDate'      => '2026-06-15',
            'IsActive'   => 1,
        ]);

        // Verify via cno
        $respCno = $this->json('GET', '/api/v1/certificates/verify/BTEB-778899');
        $respCno->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data'    => [
                        'is_valid'          => true,
                        'status'            => 'Valid',
                        'certificate_type'  => 'BTEB Board Certificate',
                        'certificate_no'    => 'BTEB-778899',
                        'registration_no'   => 'REG-2026-001',
                        'student_name'      => 'Md. Tanvir Hossain',
                    ],
                ]);

        // Verify via regNo
        $respReg = $this->json('GET', '/api/v1/certificates/verify/REG-2026-001');
        $respReg->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data'    => [
                        'certificate_no'  => 'BTEB-778899',
                        'registration_no' => 'REG-2026-001',
                    ],
                ]);
    }

    public function test_summary_report_computes_aggregated_statistics(): void
    {
        $this->resetAuthGuards();

        Certificate_icbd::create([
            'student_id'     => $this->student1->id,
            'course_id'      => $this->course1->id,
            'batch_id'       => $this->batch1->id,
            'certificate_no' => 'ICBD0001',
            'is_active'      => Certificate_icbd::STATUS_DELIVERED,
        ]);

        Certificate_icbd::create([
            'student_id'     => $this->student2->id,
            'course_id'      => $this->course2->id,
            'batch_id'       => $this->batch1->id,
            'certificate_no' => 'ICBD0002',
            'is_active'      => Certificate_icbd::STATUS_PROCESSING,
        ]);

        Certificates::create([
            'student_id' => $this->student1->id,
            'session_id' => $this->session1->id,
            'regNo'      => 'REG-2026-001',
            'cno'        => 'BTEB-778899',
            'IsActive'   => 1,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->json('GET', '/api/v1/certificates/reports/summary');

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data'    => [
                         'icbd_certificates' => [
                             'total'          => 2,
                             'delivered'      => 1,
                             'processing'     => 1,
                             'printing_queue' => 0,
                             'revoked'        => 0,
                         ],
                         'bteb_certificates' => [
                             'total'  => 1,
                             'active' => 1,
                         ],
                     ],
                 ]);
    }
}
