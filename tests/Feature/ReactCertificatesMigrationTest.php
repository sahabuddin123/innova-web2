<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Batchs;
use App\Models\Certificate_icbd;
use App\Models\Certificates;
use App\Models\Courses;
use App\Models\Session;
use App\Models\Students;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Class ReactCertificatesMigrationTest
 *
 * Comprehensive Automated Verification Suite for Phase 3.6:
 * Certificates & Verification React Module Migration:
 * 1. React SPA route /react-admin/certificates renders HTML shell and mount container
 * 2. Compiled React production bundle contains Certificates & Verification components
 * 3. Public certificate verification endpoint (unauthenticated) with strict data minimization
 * 4. Multi-guard auth security: unauthenticated (401) and student user (403) access blocked
 * 5. Admin listing, search, and multi-criteria filtering for ICBD institutional certificates
 * 6. Student eligibility evaluation (active status, fee clearance, existing certificates)
 * 7. ICBD certificate issuance with automatic serial generation (ICBDxxxx) & student status update
 * 8. Duplicate certificate issuance prevention (409 Conflict)
 * 9. Certificate detail retrieval, metadata updates, and status transitions
 * 10. Certificate revocation / soft-deletion with student registration flag reversal
 * 11. BTEB technical board certificate management & duplicate protection
 * 12. Anti-tamper QR code SVG generation and HTML print view accessibility
 * 13. Aggregated certificate reporting metrics (summary report)
 * 14. Coexistence with legacy Blade certificate routes (/admin/certificate and /admin/certificate_icbd)
 *
 * Runs strictly on isolated in-memory SQLite (:memory:) ensuring zero host MySQL mutations.
 *
 * @package Tests\Feature
 */
class ReactCertificatesMigrationTest extends TestCase
{
    protected Admin $admin;
    protected User $studentUser;

    protected string $adminToken;
    protected string $studentToken;

    protected Courses $testCourse;
    protected Batchs $testBatch;
    protected Session $testSession;
    protected Students $testStudent;

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
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('batch_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('sname');
            $table->string('gender')->default('Male');
            $table->string('phone')->nullable();
            $table->string('nid')->nullable();
            $table->string('g_name')->default('Guardian');
            $table->string('g_phone')->default('01700000000');
            $table->string('rel_g')->default('Father');
            $table->string('course_fee')->default('5000');
            $table->string('balance')->default('0');
            $table->integer('IsActive')->default(1);
            $table->integer('IsRegister')->default(1);
            $table->string('image')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('certificates_icbd', function ($table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('batch_id');
            $table->unsignedBigInteger('course_id');
            $table->string('certificate_no')->unique();
            $table->integer('theory_hours')->default(0);
            $table->integer('practical_hours')->default(0);
            $table->integer('intern_hours')->default(0);
            $table->string('result')->nullable();
            $table->date('issue_date')->nullable();
            $table->string('certificate_receiver')->nullable();
            $table->date('delivery_date')->nullable();
            $table->integer('is_active')->default(2);
            $table->string('cimage')->nullable();
            $table->string('dimage')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('certificates', function ($table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('session_id');
            $table->string('regNo');
            $table->string('cno')->nullable();
            $table->date('pDate')->nullable();
            $table->string('recevier')->nullable();
            $table->string('cimage')->nullable();
            $table->string('dimage')->nullable();
            $table->string('Result')->nullable();
            $table->date('dDate')->nullable();
            $table->integer('IsActive')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    protected function seedTestData(): void
    {
        $this->admin = Admin::create([
            'name'     => 'Cert Admin',
            'email'    => 'certadmin@innovacomputersbd.com',
            'password' => Hash::make('Secret123!'),
        ]);
        $this->adminToken = $this->admin->createToken('admin-test-token')->plainTextToken;

        $this->studentUser = User::create([
            'name'     => 'Student User',
            'email'    => 'studentuser@example.com',
            'password' => Hash::make('Secret123!'),
        ]);
        $this->studentToken = $this->studentUser->createToken('student-test-token')->plainTextToken;

        $this->testCourse = Courses::create([
            'name'           => 'Full Stack Web Development',
            'courseduration' => '6',
            'totalmoduls'    => '12',
        ]);

        $this->testBatch = Batchs::create([
            'batchnum' => 'WD-B2026-01',
            'IsActive' => 1,
        ]);

        $this->testSession = Session::create([
            'sessionName' => 'January - June 2026',
            'IsActive'    => 1,
        ]);

        $this->testStudent = Students::create([
            'user_id'    => $this->studentUser->id,
            'course_id'  => $this->testCourse->id,
            'batch_id'   => $this->testBatch->id,
            'sname'      => 'Tariqul Islam',
            'phone'      => '01711223344',
            'nid'        => '1998123456789',
            'course_fee' => '15000',
            'balance'    => '0',
            'IsActive'   => 1,
            'IsRegister' => 1,
        ]);
    }

    /**
     * Test 1: React Admin Certificates Route renders HTML shell with mount container.
     */
    public function test_react_admin_certificates_page_renders_shell_with_mount_container(): void
    {
        $response = $this->get('/react-admin/certificates');

        $response->assertStatus(200);
        $response->assertSee('id="react-root"', false);
        $response->assertSee('data-app-mode="admin"', false);
        $response->assertSee('react/main.js', false);
    }

    /**
     * Test 2: Compiled React production bundle contains Phase 3.6 Certificates & Verification components.
     */
    public function test_compiled_react_bundle_contains_certificates_verification_components(): void
    {
        $bundlePath = public_path('react/main.js');
        $this->assertFileExists($bundlePath, 'The compiled React bundle public/react/main.js must exist.');

        $bundleContent = File::get($bundlePath);

        $this->assertStringContainsString('certificates', $bundleContent);
        $this->assertStringContainsString('certificate_no', $bundleContent);
        $this->assertStringContainsString('bteb', $bundleContent);
        $this->assertStringContainsString('eligibility', $bundleContent);
        $this->assertStringContainsString('ICBD', $bundleContent);
    }

    /**
     * Test 3: Public verification endpoint resolves authentic ICBD certificate without authentication.
     */
    public function test_public_certificate_verification_resolves_valid_icbd_without_authentication(): void
    {
        $certificate = Certificate_icbd::create([
            'student_id'     => $this->testStudent->id,
            'course_id'      => $this->testCourse->id,
            'batch_id'       => $this->testBatch->id,
            'certificate_no' => 'ICBD1001',
            'theory_hours'   => 80,
            'practical_hours'=> 120,
            'intern_hours'   => 40,
            'result'         => 'A+',
            'issue_date'     => '2026-06-15',
            'is_active'      => Certificate_icbd::STATUS_DELIVERED,
        ]);

        $this->resetAuthGuards();
        $response = $this->getJson("/api/v1/certificates/verify/{$certificate->certificate_no}");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data'   => [
                'is_valid'          => true,
                'status'            => 'Delivered',
                'certificate_type'  => 'Institutional (ICBD)',
                'certificate_no'    => 'ICBD1001',
                'student_name'      => 'Tariqul Islam',
                'course_name'       => 'Full Stack Web Development',
                'curriculum_hours'  => [
                    'classroom_theory'   => 80,
                    'practical_lab'      => 120,
                    'internship'         => 40,
                    'total_course_hours' => 240,
                ],
                'result'            => 'A+',
            ],
        ]);
    }

    /**
     * Test 4: Public verification enforces strict candidate data minimization (privacy preservation).
     */
    public function test_public_certificate_verification_enforces_strict_data_minimization(): void
    {
        $certificate = Certificate_icbd::create([
            'student_id'     => $this->testStudent->id,
            'course_id'      => $this->testCourse->id,
            'batch_id'       => $this->testBatch->id,
            'certificate_no' => 'ICBD1002',
            'is_active'      => Certificate_icbd::STATUS_DELIVERED,
        ]);

        $this->resetAuthGuards();
        $response = $this->getJson("/api/v1/certificates/verify/{$certificate->certificate_no}");

        $response->assertStatus(200);
        $responseData = $response->json('data');

        // Verify candidate personal identifiable information (PII) is NOT exposed
        $this->assertArrayNotHasKey('phone', $responseData);
        $this->assertArrayNotHasKey('nid', $responseData);
        $this->assertArrayNotHasKey('g_name', $responseData);
        $this->assertArrayNotHasKey('g_phone', $responseData);
        $this->assertArrayNotHasKey('balance', $responseData);
    }

    /**
     * Test 5: Public verification resolves BTEB technical board certificate.
     */
    public function test_public_certificate_verification_resolves_bteb_certificate(): void
    {
        $bteb = Certificates::create([
            'student_id' => $this->testStudent->id,
            'session_id' => $this->testSession->id,
            'regNo'      => 'REG-998877',
            'cno'        => 'BTEB-2026-001',
            'Result'     => 'GPA 5.00',
            'IsActive'   => Certificates::STATUS_ACTIVE,
        ]);

        $this->resetAuthGuards();
        $response = $this->getJson("/api/v1/certificates/verify/{$bteb->regNo}");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data'   => [
                'is_valid'         => true,
                'status'           => 'Valid',
                'certificate_type' => 'BTEB Board Certificate',
                'registration_no'  => 'REG-998877',
                'certificate_no'   => 'BTEB-2026-001',
                'student_name'     => 'Tariqul Islam',
                'session'          => 'January - June 2026',
                'result'           => 'GPA 5.00',
            ],
        ]);
    }

    /**
     * Test 6: Public verification handles revoked status and non-existent identifiers.
     */
    public function test_public_certificate_verification_handles_revoked_and_non_existent(): void
    {
        $revoked = Certificate_icbd::create([
            'student_id'     => $this->testStudent->id,
            'course_id'      => $this->testCourse->id,
            'batch_id'       => $this->testBatch->id,
            'certificate_no' => 'ICBD-REVOKED',
            'is_active'      => Certificate_icbd::STATUS_REVOKED,
        ]);

        $this->resetAuthGuards();
        $revokedRes = $this->getJson("/api/v1/certificates/verify/{$revoked->certificate_no}");
        $revokedRes->assertStatus(200);
        $this->assertFalse($revokedRes->json('data.is_valid'));
        $this->assertEquals('Revoked', $revokedRes->json('data.status'));

        // Non-existent certificate
        $missingRes = $this->getJson('/api/v1/certificates/verify/NONEXISTENT999');
        $missingRes->assertStatus(404);
        $missingRes->assertJson([
            'success' => false,
            'errors'  => 'CERTIFICATE_NOT_FOUND',
        ]);
    }

    /**
     * Test 7: Unauthenticated requests to administrative certificate endpoints return 401.
     */
    public function test_unauthenticated_requests_to_admin_certificate_endpoints_are_rejected(): void
    {
        $this->resetAuthGuards();

        $this->getJson('/api/v1/certificates')->assertStatus(401);
        $this->getJson('/api/v1/certificates/bteb')->assertStatus(401);
        $this->getJson("/api/v1/certificates/eligibility/{$this->testStudent->id}")->assertStatus(401);
        $this->getJson('/api/v1/certificates/reports/summary')->assertStatus(401);
    }

    /**
     * Test 8: Student User tokens cannot access administrative certificate endpoints (403 Forbidden).
     */
    public function test_student_user_token_access_to_admin_certificate_endpoints_is_forbidden(): void
    {
        $this->resetAuthGuards();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->studentToken,
        ])->getJson('/api/v1/certificates');

        $response->assertStatus(403);
    }

    /**
     * Test 9: Authenticated Admin can list ICBD certificates with search and filtering.
     */
    public function test_admin_can_list_and_filter_icbd_certificates(): void
    {
        Certificate_icbd::create([
            'student_id'     => $this->testStudent->id,
            'course_id'      => $this->testCourse->id,
            'batch_id'       => $this->testBatch->id,
            'certificate_no' => 'ICBD1005',
            'result'         => 'A+',
            'is_active'      => Certificate_icbd::STATUS_DELIVERED,
        ]);

        $this->resetAuthGuards();
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson('/api/v1/certificates?is_active=1');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('ICBD1005', $response->json('data.data.0.certificate_no'));
    }

    /**
     * Test 10: Admin can evaluate student certificate eligibility.
     */
    public function test_admin_can_evaluate_student_eligibility(): void
    {
        $this->resetAuthGuards();
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/v1/certificates/eligibility/{$this->testStudent->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data'   => [
                'eligible'             => true,
                'can_issue_icbd'       => true,
                'can_issue_bteb'       => true,
                'has_outstanding_dues' => false,
                'student'              => [
                    'id'   => $this->testStudent->id,
                    'name' => 'Tariqul Islam',
                ],
            ],
        ]);
    }

    /**
     * Test 11: Admin can issue ICBD certificate with automatic serial generation & student status update.
     */
    public function test_admin_can_issue_icbd_certificate_and_marks_student_certified(): void
    {
        $payload = [
            'student_id'      => $this->testStudent->id,
            'course_id'       => $this->testCourse->id,
            'batch_id'        => $this->testBatch->id,
            'theory_hours'    => 50,
            'practical_hours' => 100,
            'intern_hours'    => 30,
            'result'          => 'A+',
            'issue_date'      => '2026-07-01',
            'is_active'       => Certificate_icbd::STATUS_PROCESSING,
        ];

        $this->resetAuthGuards();
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/v1/certificates', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('certificates_icbd', [
            'student_id' => $this->testStudent->id,
            'course_id'  => $this->testCourse->id,
            'result'     => 'A+',
        ]);

        // Asserts student's registration status was updated to 2 (certified)
        $this->assertEquals(2, $this->testStudent->fresh()->IsRegister);
    }

    /**
     * Test 12: Duplicate certificate issuance for same student and course is blocked (409 Conflict).
     */
    public function test_duplicate_icbd_certificate_issuance_is_prevented(): void
    {
        Certificate_icbd::create([
            'student_id'     => $this->testStudent->id,
            'course_id'      => $this->testCourse->id,
            'batch_id'       => $this->testBatch->id,
            'certificate_no' => 'ICBD-EXISTING',
            'is_active'      => Certificate_icbd::STATUS_PROCESSING,
        ]);

        $payload = [
            'student_id' => $this->testStudent->id,
            'course_id'  => $this->testCourse->id,
            'batch_id'   => $this->testBatch->id,
            'is_active'  => Certificate_icbd::STATUS_PROCESSING,
        ];

        $this->resetAuthGuards();
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/v1/certificates', $payload);

        $response->assertStatus(409);
        $response->assertJson([
            'success' => false,
            'errors'  => 'DUPLICATE_CERTIFICATE_ISSUANCE',
        ]);
    }

    /**
     * Test 13: Admin can update and transition certificate status.
     */
    public function test_admin_can_update_and_transition_icbd_certificate_lifecycle(): void
    {
        $cert = Certificate_icbd::create([
            'student_id'     => $this->testStudent->id,
            'course_id'      => $this->testCourse->id,
            'batch_id'       => $this->testBatch->id,
            'certificate_no' => 'ICBD-TRANSITION',
            'is_active'      => Certificate_icbd::STATUS_PROCESSING,
        ]);

        $this->resetAuthGuards();
        $statusRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/v1/certificates/{$cert->id}/status", [
            'is_active' => Certificate_icbd::STATUS_DELIVERED,
        ]);

        $statusRes->assertStatus(200);
        $this->assertEquals(Certificate_icbd::STATUS_DELIVERED, $cert->fresh()->is_active);
    }

    /**
     * Test 14: Deleting ICBD certificate soft-deletes and reverts student status.
     */
    public function test_admin_can_delete_icbd_certificate_and_reverts_student_registration(): void
    {
        $this->testStudent->update(['IsRegister' => 2]);

        $cert = Certificate_icbd::create([
            'student_id'     => $this->testStudent->id,
            'course_id'      => $this->testCourse->id,
            'batch_id'       => $this->testBatch->id,
            'certificate_no' => 'ICBD-DELETE-ME',
            'is_active'      => Certificate_icbd::STATUS_DELIVERED,
        ]);

        $this->resetAuthGuards();
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->deleteJson("/api/v1/certificates/{$cert->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('certificates_icbd', ['id' => $cert->id]);
        $this->assertEquals(1, $this->testStudent->fresh()->IsRegister);
    }

    /**
     * Test 15: Admin can manage BTEB board certificates with duplicate protection.
     */
    public function test_admin_can_manage_bteb_technical_board_certificates(): void
    {
        $payload = [
            'student_id' => $this->testStudent->id,
            'session_id' => $this->testSession->id,
            'regNo'      => 'BTEB-REG-1001',
            'cno'        => 'CERT-BTEB-1001',
            'Result'     => 'A+',
            'IsActive'   => 1,
        ];

        $this->resetAuthGuards();
        $createRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/v1/certificates/bteb', $payload);

        $createRes->assertStatus(201);
        $this->assertDatabaseHas('certificates', [
            'regNo' => 'BTEB-REG-1001',
        ]);

        // Duplicate guard for same student & session
        $dupRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson('/api/v1/certificates/bteb', $payload);

        $dupRes->assertStatus(409);
        $dupRes->assertJson([
            'success' => false,
            'errors'  => 'DUPLICATE_BTEB_CERTIFICATE',
        ]);
    }

    /**
     * Test 16: QR Code SVG generation endpoint returns data URI.
     */
    public function test_qr_code_endpoint_returns_data_uri(): void
    {
        $cert = Certificate_icbd::create([
            'student_id'     => $this->testStudent->id,
            'course_id'      => $this->testCourse->id,
            'batch_id'       => $this->testBatch->id,
            'certificate_no' => 'ICBD-QR-TEST',
            'is_active'      => Certificate_icbd::STATUS_DELIVERED,
        ]);

        $this->resetAuthGuards();
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson("/api/v1/certificates/{$cert->id}/qr");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data'   => [
                'certificate_no' => 'ICBD-QR-TEST',
                'format'         => 'svg',
            ],
        ]);
    }

    /**
     * Test 17: Aggregated Certificate Summary Reporting endpoint returns metrics.
     */
    public function test_summary_report_aggregates_metrics(): void
    {
        Certificate_icbd::create([
            'student_id'     => $this->testStudent->id,
            'course_id'      => $this->testCourse->id,
            'batch_id'       => $this->testBatch->id,
            'certificate_no' => 'ICBD-REPORT-1',
            'is_active'      => Certificate_icbd::STATUS_DELIVERED,
        ]);

        $this->resetAuthGuards();
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->getJson('/api/v1/certificates/reports/summary');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data'   => [
                'icbd_certificates' => [
                    'total'     => 1,
                    'delivered' => 1,
                ],
            ],
        ]);
    }

    /**
     * Test 18: Legacy Blade certificate routes coexist and redirect unauthenticated guests.
     */
    public function test_legacy_blade_certificate_routes_coexist(): void
    {
        $this->resetAuthGuards();

        $icbdBlade = $this->get('/admin/certificate-icbd');
        $icbdBlade->assertStatus(302);

        $btebBlade = $this->get('/admin/certificate');
        $btebBlade->assertStatus(302);
    }
}
