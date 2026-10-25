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
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Class ReactFeesMigrationTest
 *
 * Comprehensive Automated Verification Suite for Phase 3.7:
 * Fees Collection & Receipts React Module Migration:
 * 1. React SPA route /react-admin/fees renders HTML shell and mount container
 * 2. Compiled React production bundle contains Fees & Payment components
 * 3. Unauthenticated requests to fee endpoints are rejected (401 Unauthorized)
 * 4. Multi-guard auth security: student user token rejected on administrative fee endpoints (403 Forbidden)
 * 5. Admin without fees permissions is rejected (403 Forbidden)
 * 6. Admin with fees-list/fees-log can list fee transactions with filtering
 * 7. Admin can retrieve single fee transaction with student profile details
 * 8. Admin can query student fee balance and account ledger
 * 9. Admin can record manual counter fee payment and verifies immediate balance calculation
 * 10. Fee recording validation rejects negative amounts, invalid students, and missing fields (422)
 * 11. Admin can update fee transaction details
 * 12. Modifying amount on verified bKash transaction is strictly rejected (422 Unprocessable Content)
 * 13. Hard/soft deleting a fee transaction is categorically rejected to preserve audit trails (422)
 * 14. Admin can update transaction approval status and recalculates student dues
 * 15. Printable receipt endpoint returns formatted voucher data with amount in words
 * 16. Student user can access their own balance and ledger via /fees/my-balance
 * 17. bKash gateway reconciliation query endpoints require admin authorization
 * 18. Coexistence with legacy Blade fee routes (/admin/fees and /admin/fees/feelog)
 *
 * Runs strictly on isolated in-memory SQLite (:memory:) ensuring zero host MySQL mutations.
 *
 * @package Tests\Feature
 */
class ReactFeesMigrationTest extends TestCase
{
    protected Admin $superAdmin;
    protected Admin $limitedAdmin;
    protected User $studentUser;

    protected string $superAdminToken;
    protected string $limitedAdminToken;
    protected string $studentToken;

    protected Courses $testCourse;
    protected Batchs $testBatch;
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
        Schema::dropIfExists('fees_transections');
        Schema::dropIfExists('students');
        Schema::dropIfExists('batchs');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('course_categories');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
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
            $table->string('icon')->nullable();
            $table->unsignedInteger('IsActive')->default(1)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('courses', function ($table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('shortTitle')->nullable();
            $table->string('name');
            $table->string('coursefee');
            $table->unsignedInteger('IsActive')->default(1)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('batchs', function ($table) {
            $table->id();
            $table->string('batchnum');
            $table->string('totalseat');
            $table->unsignedInteger('IsActive')->default(1)->nullable();
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
            $table->string('gender')->nullable();
            $table->string('religion')->nullable();
            $table->string('phone')->nullable();
            $table->string('course_fee')->default('0');
            $table->string('discount')->default('0');
            $table->string('balance')->default('0');
            $table->date('admission_date')->nullable();
            $table->unsignedInteger('IsActive')->default(1)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('fees_transections', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('student_id');
            $table->string('paidamount');
            $table->string('paymentType')->default('1');
            $table->date('paiddate');
            $table->text('paidremark')->nullable();
            $table->unsignedInteger('channel')->default(1)->nullable();
            $table->string('trxID')->nullable();
            $table->string('paymentID')->nullable();
            $table->unsignedInteger('status')->default(0)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    protected function seedTestData(): void
    {
        // Permissions
        $pList = \Spatie\Permission\Models\Permission::create(['name' => 'fees-list', 'guard_name' => 'admin']);
        $pLog = \Spatie\Permission\Models\Permission::create(['name' => 'fees-log', 'guard_name' => 'admin']);
        $pCreate = \Spatie\Permission\Models\Permission::create(['name' => 'fees-create', 'guard_name' => 'admin']);
        $pEdit = \Spatie\Permission\Models\Permission::create(['name' => 'fees-edit', 'guard_name' => 'admin']);
        $pDelete = \Spatie\Permission\Models\Permission::create(['name' => 'fees-delete', 'guard_name' => 'admin']);
        $pStudentList = \Spatie\Permission\Models\Permission::create(['name' => 'student-list', 'guard_name' => 'admin']);

        // Super Admin with all fee permissions
        $this->superAdmin = Admin::create([
            'name'     => 'Accounts Manager',
            'email'    => 'accounts@innova.com',
            'password' => Hash::make('password123'),
        ]);
        $this->superAdmin->givePermissionTo([$pList, $pLog, $pCreate, $pEdit, $pDelete, $pStudentList]);
        $this->superAdminToken = $this->superAdmin->createToken('admin-token')->plainTextToken;

        // Limited Admin without fees-create
        $this->limitedAdmin = Admin::create([
            'name'     => 'Viewer Admin',
            'email'    => 'viewer@innova.com',
            'password' => Hash::make('password123'),
        ]);
        $this->limitedAdmin->givePermissionTo([$pList]);
        $this->limitedAdminToken = $this->limitedAdmin->createToken('limited-token')->plainTextToken;

        // Student User
        $this->studentUser = User::create([
            'name'     => 'Tariqul Islam',
            'email'    => 'tariqul@innova.com',
            'password' => Hash::make('secret123'),
        ]);
        $this->studentToken = $this->studentUser->createToken('student-token')->plainTextToken;

        // Course & Batch
        $cat = CourseCategory::create(['CourseCategoris' => 'Professional Software']);
        $this->testCourse = Courses::create([
            'category_id' => $cat->id,
            'name'        => 'Full Stack Web Development',
            'shortTitle'  => 'FSWD',
            'coursefee'   => '15000',
            'IsActive'    => 1,
        ]);
        $this->testBatch = Batchs::create([
            'batchnum'  => 'FSWD-B01',
            'totalseat' => '25',
            'IsActive'  => 1,
        ]);

        // Student with Course Fee 15000, Discount 3000, Balance (Net Payable) 12000
        $this->testStudent = Students::create([
            'user_id'        => $this->studentUser->id,
            'course_id'      => $this->testCourse->id,
            'batch_id'       => $this->testBatch->id,
            'sname'          => 'Tariqul Islam',
            'phone'          => '01711223344',
            'course_fee'     => '15000',
            'discount'       => '3000',
            'balance'        => '12000', // net course payable
            'admission_date' => '2026-02-01',
            'IsActive'       => 1,
        ]);
    }

    public function test_admin_spa_route_renders_react_shell_for_fees(): void
    {
        $response = $this->actingAs($this->superAdmin, 'admin')
            ->get('/react-admin/fees');

        $response->assertStatus(200);
        $response->assertSee('id="react-root"', false);
        $response->assertSee('data-app-mode="admin"', false);
    }

    public function test_compiled_bundle_contains_fees_module_components(): void
    {
        $bundlePath = public_path('react/main.js');
        $this->assertTrue(File::exists($bundlePath), 'Compiled React production bundle does not exist at public/react/main.js');

        $bundleContent = File::get($bundlePath);
        $this->assertNotEmpty($bundleContent, 'React bundle is empty.');

        // Verify fees keywords in bundle
        $hasFees = strpos($bundleContent, 'fees') !== false ||
                   strpos($bundleContent, 'paidamount') !== false ||
                   strpos($bundleContent, 'FeeCollectionModal') !== false ||
                   strpos($bundleContent, 'Fees & Payment Management') !== false;

        $this->assertTrue($hasFees, 'Compiled React bundle does not contain Fees & Payments module components.');
    }

    public function test_unauthenticated_requests_to_fees_endpoints_are_rejected(): void
    {
        $this->getJson('/api/v1/fees/transactions')->assertStatus(401);
        $this->getJson('/api/v1/fees/my-balance')->assertStatus(401);
        $this->getJson('/api/v1/fees/students/' . $this->testStudent->id . '/balance')->assertStatus(401);
        $this->postJson('/api/v1/fees/transactions', [])->assertStatus(401);
    }

    public function test_student_user_token_is_rejected_on_admin_fee_routes(): void
    {
        $this->resetAuthGuards();

        // Listing all fee transactions as student -> 403 Forbidden
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->studentToken)
            ->getJson('/api/v1/fees/transactions');
        $response->assertStatus(403);

        // Counter fee payment collection as student -> 403 Forbidden
        $storeResponse = $this->withHeader('Authorization', 'Bearer ' . $this->studentToken)
            ->postJson('/api/v1/fees/transactions', [
                'student_id'  => $this->testStudent->id,
                'paidamount'  => 5000,
                'paymentType' => 1,
                'paiddate'    => '2026-02-10',
                'paidremark'  => 'Self-collection attempt',
            ]);
        $storeResponse->assertStatus(403);
    }

    public function test_admin_without_fees_create_permission_is_rejected(): void
    {
        $this->resetAuthGuards();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->limitedAdminToken)
            ->postJson('/api/v1/fees/transactions', [
                'student_id'  => $this->testStudent->id,
                'paidamount'  => 4000,
                'paymentType' => 1,
                'paiddate'    => '2026-02-10',
                'paidremark'  => 'Counter installment',
            ]);

        $response->assertStatus(403);
        $response->assertJson(['success' => false]);
    }

    public function test_admin_can_retrieve_paginated_fee_transactions_with_filtering(): void
    {
        $this->resetAuthGuards();

        FeesTransections::create([
            'student_id'  => $this->testStudent->id,
            'paidamount'  => '4000.00',
            'paymentType' => 1,
            'paiddate'    => '2026-02-05',
            'paidremark'  => 'Counter Cash 1',
            'channel'     => 1,
            'status'      => 0,
        ]);

        FeesTransections::create([
            'student_id'  => $this->testStudent->id,
            'paidamount'  => '3000.00',
            'paymentType' => 1,
            'paiddate'    => '2026-02-12',
            'paidremark'  => 'bKash Deposit',
            'channel'     => 2,
            'trxID'       => 'BKASH_TRX_FEES_TEST',
            'status'      => 1,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->getJson('/api/v1/fees/transactions?channel=2');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.data.0.trxID', 'BKASH_TRX_FEES_TEST');
    }

    public function test_admin_can_retrieve_single_fee_transaction(): void
    {
        $this->resetAuthGuards();

        $trx = FeesTransections::create([
            'student_id'  => $this->testStudent->id,
            'paidamount'  => '5000.00',
            'paymentType' => 1,
            'paiddate'    => '2026-02-08',
            'paidremark'  => 'Full first installment',
            'channel'     => 1,
            'status'      => 0,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->getJson('/api/v1/fees/transactions/' . $trx->id);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.id', $trx->id);
        $response->assertJsonPath('data.paidamount', '5000.00');
        $response->assertJsonPath('data.student.id', $this->testStudent->id);
    }

    public function test_admin_can_query_student_balance_and_account_summary(): void
    {
        $this->resetAuthGuards();

        FeesTransections::create([
            'student_id'  => $this->testStudent->id,
            'paidamount'  => '5000.00',
            'paymentType' => 1,
            'paiddate'    => '2026-02-05',
            'paidremark'  => 'Approved Installment',
            'channel'     => 1,
            'status'      => 0,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->getJson('/api/v1/fees/students/' . $this->testStudent->id . '/balance');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.student.id', $this->testStudent->id);
        $response->assertJsonPath('data.account_summary.total_course_fee', 15000);
        $response->assertJsonPath('data.account_summary.discount', 3000);
        $response->assertJsonPath('data.account_summary.net_payable', 12000);
        $response->assertJsonPath('data.account_summary.total_paid_course', 5000);
        $response->assertJsonPath('data.account_summary.due_course_fee', 7000);
    }

    public function test_admin_can_record_manual_counter_payment_and_verifies_balance_reduction(): void
    {
        $this->resetAuthGuards();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->postJson('/api/v1/fees/transactions', [
                'student_id'  => $this->testStudent->id,
                'paidamount'  => 4000,
                'paymentType' => 1,
                'paiddate'    => '2026-02-15',
                'paidremark'  => 'Counter Cash Installment 1',
                'channel'     => 1,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.transaction.student_id', $this->testStudent->id);
        $response->assertJsonPath('data.transaction.status', 0); // Counter collection is approved immediately
        $response->assertJsonPath('data.financial_summary.total_paid', 4000);
        $response->assertJsonPath('data.financial_summary.due_amount', 8000); // 12000 - 4000 = 8000
    }

    public function test_fee_recording_validation_rejects_invalid_inputs(): void
    {
        $this->resetAuthGuards();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->postJson('/api/v1/fees/transactions', [
                'student_id'  => 999999, // non-existent
                'paidamount'  => -50,    // negative amount
                'paymentType' => 99,     // invalid type
                'paiddate'    => 'invalid-date',
            ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors' => ['student_id', 'paidamount', 'paymentType', 'paiddate', 'paidremark']]);
    }

    public function test_admin_can_update_fee_transaction_details(): void
    {
        $this->resetAuthGuards();

        $trx = FeesTransections::create([
            'student_id'  => $this->testStudent->id,
            'paidamount'  => '3000.00',
            'paymentType' => 1,
            'paiddate'    => '2026-02-01',
            'paidremark'  => 'Initial Cash Receipt',
            'channel'     => 1,
            'status'      => 0,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->putJson('/api/v1/fees/transactions/' . $trx->id, [
                'paidamount'  => '3500.00',
                'paymentType' => 1,
                'paiddate'    => '2026-02-02',
                'paidremark'  => 'Corrected Cash Receipt with late fee adjustment',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.paidamount', '3500.00');
        $response->assertJsonPath('data.paidremark', 'Corrected Cash Receipt with late fee adjustment');
    }

    public function test_modifying_amount_on_verified_bkash_transaction_is_strictly_rejected(): void
    {
        $this->resetAuthGuards();

        $gatewayTrx = FeesTransections::create([
            'student_id'  => $this->testStudent->id,
            'paidamount'  => '5000.00',
            'paymentType' => 1,
            'paiddate'    => '2026-02-05',
            'paidremark'  => 'Verified bKash Payment',
            'channel'     => 2,
            'trxID'       => 'GATEWAY_TRX_LOCKED_123',
            'status'      => 0,
        ]);

        // Attempting to modify the paidamount on a captured bKash gateway record
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->putJson('/api/v1/fees/transactions/' . $gatewayTrx->id, [
                'paidamount'  => '4000.00', // altered amount
                'paymentType' => 1,
                'paiddate'    => '2026-02-05',
                'paidremark'  => 'Attempted modification',
            ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    public function test_admin_cannot_delete_fee_transaction_to_preserve_audit_trails(): void
    {
        $this->resetAuthGuards();

        $trx = FeesTransections::create([
            'student_id'  => $this->testStudent->id,
            'paidamount'  => '2500.00',
            'paymentType' => 1,
            'paiddate'    => '2026-02-03',
            'paidremark'  => 'Installment',
            'channel'     => 1,
            'status'      => 0,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->deleteJson('/api/v1/fees/transactions/' . $trx->id);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);

        $this->assertDatabaseHas('fees_transections', ['id' => $trx->id]);
    }

    public function test_admin_can_update_transaction_approval_status_and_recalculates_student_dues(): void
    {
        $this->resetAuthGuards();

        // Pending transaction (status = 1) of 4000
        $trx = FeesTransections::create([
            'student_id'  => $this->testStudent->id,
            'paidamount'  => '4000.00',
            'paymentType' => 1,
            'paiddate'    => '2026-02-01',
            'paidremark'  => 'Pending bKash payment',
            'channel'     => 2,
            'status'      => 1,
        ]);

        // Transition status to 0 (Approved)
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->putJson('/api/v1/fees/transactions/' . $trx->id . '/status', [
                'status' => 0,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.transaction.status', 0);
        $response->assertJsonPath('data.financial_summary.total_paid', 4000);
        $response->assertJsonPath('data.financial_summary.due_amount', 8000); // 12000 - 4000
    }

    public function test_receipt_endpoint_returns_formatted_voucher_with_amount_in_words(): void
    {
        $this->resetAuthGuards();

        $trx = FeesTransections::create([
            'student_id'  => $this->testStudent->id,
            'paidamount'  => '6000.00',
            'paymentType' => 1,
            'paiddate'    => '2026-02-14',
            'paidremark'  => 'Midterm Tuition Installment',
            'channel'     => 1,
            'status'      => 0,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->getJson('/api/v1/fees/transactions/' . $trx->id . '/receipt');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.transaction_id', $trx->id);
        $response->assertJsonPath('data.paid_amount', 6000);
        $response->assertJsonPath('data.amount_in_words', '6,000.00 Taka only');
        $response->assertJsonPath('data.student.id', $this->testStudent->id);
        $response->assertJsonPath('data.account_summary.total_paid', 6000);
        $response->assertJsonPath('data.account_summary.net_due', 6000); // 12000 - 6000
    }

    public function test_student_user_can_access_own_balance_via_my_balance(): void
    {
        $this->resetAuthGuards();

        FeesTransections::create([
            'user_id'     => $this->studentUser->id,
            'student_id'  => $this->testStudent->id,
            'paidamount'  => '3000.00',
            'paymentType' => 1,
            'paiddate'    => '2026-02-10',
            'paidremark'  => 'Self Payment',
            'channel'     => 1,
            'status'      => 0,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->studentToken)
            ->getJson('/api/v1/fees/my-balance');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.student.id', $this->testStudent->id);
        $response->assertJsonPath('data.account_summary.total_paid_course', 3000);
    }

    public function test_bkash_reconciliation_query_requires_admin_authorization(): void
    {
        $this->resetAuthGuards();

        // Unauthenticated -> 401
        $this->getJson('/api/v1/fees/bkash/query/BKASH_TEST_PAY_123')->assertStatus(401);

        // Student token -> 403 Forbidden
        $this->withHeader('Authorization', 'Bearer ' . $this->studentToken)
            ->getJson('/api/v1/fees/bkash/query/BKASH_TEST_PAY_123')
            ->assertStatus(403);
    }

    public function test_legacy_blade_fees_routes_coexist_without_disruption(): void
    {
        // Legacy students payments list
        $response1 = $this->actingAs($this->superAdmin, 'admin')
            ->get('/admin/fees');
        $response1->assertStatus(200);

        // Legacy feelog
        $response2 = $this->actingAs($this->superAdmin, 'admin')
            ->get('/admin/fees/feelog');
        $response2->assertStatus(200);
    }
}
