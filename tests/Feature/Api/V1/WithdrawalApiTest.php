<?php

namespace Tests\Feature\Api\V1;

use App\Models\Admin;
use App\Models\DividendWithdraw;
use App\Models\Invesments;
use App\Models\InvestmentWithdraw;
use App\Models\Shareholder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Class WithdrawalApiTest
 *
 * Automated regression suite for Phase 2.7.2:
 * 1. Capital Investment Withdrawal APIs (7 endpoints)
 * 2. Shareholder Dividend Withdrawal APIs (7 endpoints)
 *
 * Validates authentication barriers, state machine lifecycle (pending -> approved | rejected),
 * financial capital balance checks via BCMath (scale 2), overdraft prevention, zero-balance allowance,
 * comma sanitization, idempotency/concurrency safeguards, and strict isolation of dividend payouts.
 *
 * Runs exclusively in isolated in-memory SQLite (:memory:).
 *
 * @package Tests\Feature\Api\V1
 */
class WithdrawalApiTest extends TestCase
{
    protected Admin $admin;
    protected User $studentUser;

    protected string $adminToken;
    protected string $studentToken;

    protected Shareholder $shareholder;

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

        Schema::create('shareholders', function ($table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->string('designation')->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->unsignedInteger('shareType')->default(0)->nullable();
            $table->string('image')->nullable();
            $table->string('nid')->nullable();
            $table->unsignedInteger('isActive')->default(0)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('invesments', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('shareholder_id');
            $table->string('amount')->nullable();
            $table->string('recevedBy')->nullable();
            $table->date('recevedDate')->nullable();
            $table->text('paidremark')->nullable();
            $table->string('image')->nullable();
            $table->unsignedInteger('isActive')->default(0)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('investment_withdraws', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('shareholder_id')->nullable();
            $table->decimal('amount', 15, 2)->default(0.00);
            $table->date('withdraw_date')->nullable();
            $table->string('method')->nullable();
            $table->string('branch_name')->nullable();
            $table->string('receiver_name')->nullable();
            $table->string('pay_by')->nullable();
            $table->string('account_number')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('processed_remarks')->nullable();
            $table->text('remarks')->nullable();
            $table->string('attachment')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('dividend_withdraws', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('shareholder_id')->nullable();
            $table->decimal('amount', 15, 2)->default(0.00);
            $table->date('withdraw_date')->nullable();
            $table->string('method')->nullable();
            $table->string('branch_name')->nullable();
            $table->string('receiver_name')->nullable();
            $table->string('pay_by')->nullable();
            $table->string('account_number')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('processed_remarks')->nullable();
            $table->text('remarks')->nullable();
            $table->string('attachment')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    protected function seedTestData(): void
    {
        $this->admin = Admin::create([
            'name'     => 'Finance Director Admin',
            'email'    => 'finance.director@example.com',
            'password' => Hash::make('Secret123!'),
        ]);
        $this->adminToken = $this->admin->createToken('admin-token')->plainTextToken;

        $this->studentUser = User::create([
            'name'     => 'Student User',
            'email'    => 'student@example.com',
            'password' => Hash::make('Secret123!'),
        ]);
        $this->studentToken = $this->studentUser->createToken('user-token')->plainTextToken;

        $this->shareholder = Shareholder::create([
            'name'        => 'Engr. Tareq Rahman',
            'designation' => 'Founding Director',
            'email'       => 'tareq@example.com',
            'phone'       => '01711000001',
            'shareType'   => 1,
            'isActive'    => 1,
        ]);
    }

    // =========================================================================
    // SECTION 1: AUTHENTICATION & ACCESS CONTROL
    // =========================================================================

    public function test_unauthenticated_requests_are_rejected_with_401(): void
    {
        $routes = [
            ['GET', '/api/v1/withdrawals/investment'],
            ['POST', '/api/v1/withdrawals/investment'],
            ['GET', '/api/v1/withdrawals/investment/1'],
            ['PUT', '/api/v1/withdrawals/investment/1'],
            ['DELETE', '/api/v1/withdrawals/investment/1'],
            ['POST', '/api/v1/withdrawals/investment/1/approve'],
            ['POST', '/api/v1/withdrawals/investment/1/reject'],
            ['GET', '/api/v1/withdrawals/dividend'],
            ['POST', '/api/v1/withdrawals/dividend'],
            ['GET', '/api/v1/withdrawals/dividend/1'],
            ['PUT', '/api/v1/withdrawals/dividend/1'],
            ['DELETE', '/api/v1/withdrawals/dividend/1'],
            ['POST', '/api/v1/withdrawals/dividend/1/approve'],
            ['POST', '/api/v1/withdrawals/dividend/1/reject'],
        ];

        foreach ($routes as [$method, $uri]) {
            $response = $this->json($method, $uri);
            $response->assertStatus(401);
        }
    }

    public function test_non_admin_users_are_forbidden_with_403(): void
    {
        $routes = [
            ['GET', '/api/v1/withdrawals/investment'],
            ['POST', '/api/v1/withdrawals/investment'],
            ['GET', '/api/v1/withdrawals/dividend'],
            ['POST', '/api/v1/withdrawals/dividend'],
        ];

        foreach ($routes as [$method, $uri]) {
            $response = $this->withHeader('Authorization', 'Bearer ' . $this->studentToken)
                ->json($method, $uri, []);
            $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'message' => 'Forbidden: Admin access required',
                ]);
        }
    }

    // =========================================================================
    // SECTION 2: CAPITAL INVESTMENT WITHDRAWAL CRUD LIFECYCLE
    // =========================================================================

    public function test_create_investment_withdrawal_request_sets_server_managed_defaults(): void
    {
        $payload = [
            'shareholder_id' => $this->shareholder->id,
            'amount'         => '50,000.00', // comma-formatted
            'withdraw_date'  => '2026-10-24',
            'method'         => 'Bank Transfer',
            'branch_name'    => 'Agrabad Branch',
            'receiver_name'  => 'Tareq Rahman',
            'pay_by'         => 'Cheque #12345',
            'account_number' => '123-456-7890',
            'remarks'        => 'Capital draw for personal expenditure',
            // Attempt to spoof status and audit fields
            'status'         => 'approved',
            'requested_by'   => 9999,
            'processed_by'   => 9999,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('POST', '/api/v1/withdrawals/investment', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Investment withdrawal request created successfully',
            ]);

        $data = $response->json('data');
        $this->assertEquals('pending', $data['status'], 'Status must be forced to pending');
        $this->assertEquals($this->admin->id, $data['requested_by'], 'Requested by must be authenticated admin');
        $this->assertNotNull($data['requested_at'], 'Requested at must be set by server');
        $this->assertNull($data['processed_by'], 'Processed by must remain null on creation');
        $this->assertNull($data['processed_at'], 'Processed at must remain null on creation');
        $this->assertEquals('50000.00', $data['amount'], 'Comma in amount must be sanitized');

        $this->assertDatabaseHas('investment_withdraws', [
            'id'             => $data['id'],
            'shareholder_id' => $this->shareholder->id,
            'status'         => 'pending',
            'amount'         => 50000.00,
            'requested_by'   => $this->admin->id,
        ]);
    }

    public function test_create_investment_withdrawal_validates_required_fields(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('POST', '/api/v1/withdrawals/investment', [
                'shareholder_id' => 99999, // non-existent
                'amount'         => -50,   // invalid
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['success', 'message', 'errors' => ['shareholder_id', 'amount']]);
    }

    public function test_list_investment_withdrawals_returns_paginated_records_and_filters(): void
    {
        // Create 2 records
        InvestmentWithdraw::create([
            'shareholder_id' => $this->shareholder->id,
            'amount'         => 10000.00,
            'status'         => 'pending',
            'withdraw_date'  => '2026-10-20',
            'method'         => 'Cash',
        ]);
        InvestmentWithdraw::create([
            'shareholder_id' => $this->shareholder->id,
            'amount'         => 20000.00,
            'status'         => 'approved',
            'withdraw_date'  => '2026-10-22',
            'method'         => 'Cheque',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('GET', '/api/v1/withdrawals/investment');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Investment withdrawals retrieved successfully',
            ]);

        $this->assertCount(2, $response->json('data.data'));

        // Filter by status=approved
        $filtered = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('GET', '/api/v1/withdrawals/investment?status=approved');

        $filtered->assertStatus(200);
        $this->assertCount(1, $filtered->json('data.data'));
        $this->assertEquals('approved', $filtered->json('data.data.0.status'));
    }

    public function test_show_investment_withdrawal_returns_detail_with_relations(): void
    {
        $withdraw = InvestmentWithdraw::create([
            'shareholder_id' => $this->shareholder->id,
            'amount'         => 15000.00,
            'status'         => 'pending',
            'withdraw_date'  => '2026-10-21',
            'method'         => 'Cash',
            'requested_by'   => $this->admin->id,
            'requested_at'   => Carbon::now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('GET', "/api/v1/withdrawals/investment/{$withdraw->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'id'             => $withdraw->id,
                    'shareholder_id' => $this->shareholder->id,
                    'numeric_amount' => 15000.0,
                    'shareholder'    => [
                        'id'   => $this->shareholder->id,
                        'name' => $this->shareholder->name,
                    ],
                    'requester'      => [
                        'id'   => $this->admin->id,
                        'name' => $this->admin->name,
                    ],
                ],
            ]);
    }

    public function test_show_non_existent_investment_withdrawal_returns_404(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('GET', '/api/v1/withdrawals/investment/99999');

        $response->assertStatus(404)
            ->assertJson(['success' => false, 'message' => 'Investment withdrawal request not found']);
    }

    public function test_update_pending_investment_withdrawal_succeeds(): void
    {
        $withdraw = InvestmentWithdraw::create([
            'shareholder_id' => $this->shareholder->id,
            'amount'         => 10000.00,
            'status'         => 'pending',
            'withdraw_date'  => '2026-10-20',
            'method'         => 'Cash',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('PUT', "/api/v1/withdrawals/investment/{$withdraw->id}", [
                'amount' => '12,500.00',
                'method' => 'Bank Transfer',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Investment withdrawal request updated successfully',
                'data'    => [
                    'id'     => $withdraw->id,
                    'amount' => '12500.00',
                    'method' => 'Bank Transfer',
                ],
            ]);

        $this->assertDatabaseHas('investment_withdraws', [
            'id'     => $withdraw->id,
            'amount' => 12500.00,
            'method' => 'Bank Transfer',
        ]);
    }

    public function test_update_non_pending_investment_withdrawal_returns_409(): void
    {
        $approvedWithdraw = InvestmentWithdraw::create([
            'shareholder_id' => $this->shareholder->id,
            'amount'         => 10000.00,
            'status'         => 'approved',
            'withdraw_date'  => '2026-10-20',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('PUT', "/api/v1/withdrawals/investment/{$approvedWithdraw->id}", [
                'amount' => 15000.00,
            ]);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_destroy_pending_investment_withdrawal_succeeds(): void
    {
        $withdraw = InvestmentWithdraw::create([
            'shareholder_id' => $this->shareholder->id,
            'amount'         => 10000.00,
            'status'         => 'pending',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('DELETE', "/api/v1/withdrawals/investment/{$withdraw->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Investment withdrawal request deleted successfully',
            ]);

        $this->assertSoftDeleted('investment_withdraws', ['id' => $withdraw->id]);
    }

    public function test_destroy_approved_investment_withdrawal_returns_409(): void
    {
        $withdraw = InvestmentWithdraw::create([
            'shareholder_id' => $this->shareholder->id,
            'amount'         => 10000.00,
            'status'         => 'approved',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('DELETE', "/api/v1/withdrawals/investment/{$withdraw->id}");

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot delete withdrawal request: Approved withdrawals are locked for financial audit integrity.',
            ]);

        $this->assertDatabaseHas('investment_withdraws', [
            'id'         => $withdraw->id,
            'deleted_at' => null,
        ]);
    }

    // =========================================================================
    // SECTION 3: FINANCIAL BALANCE CHECKS & APPROVAL / REJECTION WORKFLOW
    // =========================================================================

    public function test_approve_withdrawal_fails_with_422_when_capital_is_insufficient(): void
    {
        // Shareholder has 50,000 active investment
        Invesments::create([
            'shareholder_id' => $this->shareholder->id,
            'amount'         => '50000.00',
            'recevedBy'      => 'Admin',
            'recevedDate'    => '2026-10-01',
            'isActive'       => 1,
        ]);

        // Withdrawal request is 60,000 (exceeds balance)
        $withdraw = InvestmentWithdraw::create([
            'shareholder_id' => $this->shareholder->id,
            'amount'         => '60000.00',
            'status'         => 'pending',
            'withdraw_date'  => '2026-10-24',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('POST', "/api/v1/withdrawals/investment/{$withdraw->id}/approve", [
                'remarks' => 'Approving draw',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        // Verify status remains pending
        $this->assertDatabaseHas('investment_withdraws', [
            'id'     => $withdraw->id,
            'status' => 'pending',
        ]);
    }

    public function test_approve_withdrawal_blocks_one_cent_overdraft_with_422(): void
    {
        // Active capital: 10,000.00
        Invesments::create([
            'shareholder_id' => $this->shareholder->id,
            'amount'         => '10000.00',
            'recevedBy'      => 'Admin',
            'recevedDate'    => '2026-10-01',
            'isActive'       => 1,
        ]);

        // Attempting to withdraw 10,000.01 (overdraft of -0.01)
        $withdraw = InvestmentWithdraw::create([
            'shareholder_id' => $this->shareholder->id,
            'amount'         => '10000.01',
            'status'         => 'pending',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('POST', "/api/v1/withdrawals/investment/{$withdraw->id}/approve");

        $response->assertStatus(422);

        $this->assertDatabaseHas('investment_withdraws', [
            'id'     => $withdraw->id,
            'status' => 'pending',
        ]);
    }

    public function test_approve_withdrawal_allows_exact_zero_ending_balance(): void
    {
        // Active capital: 25,000.00
        Invesments::create([
            'shareholder_id' => $this->shareholder->id,
            'amount'         => '25000.00',
            'recevedBy'      => 'Admin',
            'recevedDate'    => '2026-10-01',
            'isActive'       => 1,
        ]);

        // Attempting to withdraw exactly 25,000.00 (projected balance 0.00)
        $withdraw = InvestmentWithdraw::create([
            'shareholder_id' => $this->shareholder->id,
            'amount'         => '25000.00',
            'status'         => 'pending',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('POST', "/api/v1/withdrawals/investment/{$withdraw->id}/approve", [
                'remarks' => 'Full capital liquidation authorized',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Investment withdrawal request approved successfully',
            ]);

        $data = $response->json('data');
        $this->assertEquals('approved', $data['status']);
        $this->assertEquals($this->admin->id, $data['processed_by']);
        $this->assertEquals('Full capital liquidation authorized', $data['processed_remarks']);
        $this->assertNotNull($data['processed_at']);

        // Check statement reflects ending balance of exactly 0.00
        $stmtResponse = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('GET', "/api/v1/shareholders/{$this->shareholder->id}/statement");

        $stmtResponse->assertStatus(200);
        $this->assertEquals(0.0, $stmtResponse->json('data.summary.current_balance'));
        $this->assertEquals(25000.0, $stmtResponse->json('data.summary.total_investment'));
        $this->assertEquals(25000.0, $stmtResponse->json('data.summary.total_withdrawn'));
    }

    public function test_approved_withdrawal_is_reflected_in_statement_and_summary(): void
    {
        // 100,000 deposit
        Invesments::create([
            'shareholder_id' => $this->shareholder->id,
            'amount'         => '100000.00',
            'recevedBy'      => 'Accounts',
            'recevedDate'    => '2026-10-01',
            'isActive'       => 1,
        ]);

        // 30,000 pending withdrawal (should NOT affect statement yet)
        $withdraw = InvestmentWithdraw::create([
            'shareholder_id' => $this->shareholder->id,
            'amount'         => '30000.00',
            'status'         => 'pending',
            'withdraw_date'  => '2026-10-15',
        ]);

        // Statement before approval
        $stmtBefore = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('GET', "/api/v1/shareholders/{$this->shareholder->id}/statement");
        $this->assertEquals(100000.0, $stmtBefore->json('data.summary.current_balance'));
        $this->assertEquals(0.0, $stmtBefore->json('data.summary.total_withdrawn'));

        // Approve withdrawal
        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('POST', "/api/v1/withdrawals/investment/{$withdraw->id}/approve", [
                'remarks' => 'Approved by board',
            ])->assertStatus(200);

        // Statement after approval
        $stmtAfter = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('GET', "/api/v1/shareholders/{$this->shareholder->id}/statement");
        $this->assertEquals(70000.0, $stmtAfter->json('data.summary.current_balance'));
        $this->assertEquals(30000.0, $stmtAfter->json('data.summary.total_withdrawn'));
        $this->assertCount(2, $stmtAfter->json('data.transactions'));
    }

    public function test_rejected_withdrawal_does_not_affect_capital_balance(): void
    {
        Invesments::create([
            'shareholder_id' => $this->shareholder->id,
            'amount'         => '100000.00',
            'recevedBy'      => 'Accounts',
            'recevedDate'    => '2026-10-01',
            'isActive'       => 1,
        ]);

        $withdraw = InvestmentWithdraw::create([
            'shareholder_id' => $this->shareholder->id,
            'amount'         => '30000.00',
            'status'         => 'pending',
            'withdraw_date'  => '2026-10-15',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('POST', "/api/v1/withdrawals/investment/{$withdraw->id}/reject", [
                'remarks' => 'Insufficient documentation provided',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Investment withdrawal request rejected successfully',
                'data'    => [
                    'id'                => $withdraw->id,
                    'status'            => 'rejected',
                    'processed_by'      => $this->admin->id,
                    'processed_remarks' => 'Insufficient documentation provided',
                ],
            ]);

        // Balance remains 100,000.00
        $stmt = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('GET', "/api/v1/shareholders/{$this->shareholder->id}/statement");
        $this->assertEquals(100000.0, $stmt->json('data.summary.current_balance'));
        $this->assertEquals(0.0, $stmt->json('data.summary.total_withdrawn'));
    }

    public function test_cannot_re_approve_or_re_reject_already_processed_withdrawal(): void
    {
        Invesments::create([
            'shareholder_id' => $this->shareholder->id,
            'amount'         => '100000.00',
            'recevedBy'      => 'Accounts',
            'recevedDate'    => '2026-10-01',
            'isActive'       => 1,
        ]);

        $withdraw = InvestmentWithdraw::create([
            'shareholder_id' => $this->shareholder->id,
            'amount'         => '20000.00',
            'status'         => 'pending',
        ]);

        // First approval
        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('POST', "/api/v1/withdrawals/investment/{$withdraw->id}/approve")
            ->assertStatus(200);

        // Second approval attempt returns 409 Conflict
        $reApprove = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('POST', "/api/v1/withdrawals/investment/{$withdraw->id}/approve");
        $reApprove->assertStatus(409);

        // Reject attempt on approved record returns 409 Conflict
        $rejectAttempt = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('POST', "/api/v1/withdrawals/investment/{$withdraw->id}/reject");
        $rejectAttempt->assertStatus(409);
    }

    // =========================================================================
    // SECTION 4: SHAREHOLDER DIVIDEND WITHDRAWALS & CAPITAL ISOLATION
    // =========================================================================

    public function test_create_dividend_withdrawal_request(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('POST', '/api/v1/withdrawals/dividend', [
                'shareholder_id' => $this->shareholder->id,
                'amount'         => '35,000.00',
                'withdraw_date'  => '2026-10-24',
                'method'         => 'Cheque',
                'branch_name'    => 'Principal Branch',
                'receiver_name'  => 'Tareq Rahman',
                'remarks'        => 'Q3 Dividend Distribution',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Dividend withdrawal request created successfully',
            ]);

        $data = $response->json('data');
        $this->assertEquals('pending', $data['status']);
        $this->assertEquals('35000.00', $data['amount']);
        $this->assertEquals($this->admin->id, $data['requested_by']);

        $this->assertDatabaseHas('dividend_withdraws', [
            'id'             => $data['id'],
            'shareholder_id' => $this->shareholder->id,
            'amount'         => 35000.00,
            'status'         => 'pending',
        ]);
    }

    public function test_list_and_show_dividend_withdrawals(): void
    {
        $dividend = DividendWithdraw::create([
            'shareholder_id' => $this->shareholder->id,
            'amount'         => 18000.00,
            'status'         => 'pending',
            'withdraw_date'  => '2026-10-23',
            'method'         => 'EFT',
        ]);

        $listResp = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('GET', '/api/v1/withdrawals/dividend');
        $listResp->assertStatus(200);
        $this->assertCount(1, $listResp->json('data.data'));

        $showResp = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('GET', "/api/v1/withdrawals/dividend/{$dividend->id}");
        $showResp->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'id'             => $dividend->id,
                    'numeric_amount' => 18000.0,
                    'method'         => 'EFT',
                ],
            ]);
    }

    public function test_update_and_destroy_dividend_withdrawals(): void
    {
        $dividend = DividendWithdraw::create([
            'shareholder_id' => $this->shareholder->id,
            'amount'         => 18000.00,
            'status'         => 'pending',
            'withdraw_date'  => '2026-10-23',
        ]);

        // Update pending
        $updateResp = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('PUT', "/api/v1/withdrawals/dividend/{$dividend->id}", [
                'amount' => '22,000.00',
            ]);
        $updateResp->assertStatus(200);
        $this->assertEquals('22000.00', $updateResp->json('data.amount'));

        // Delete pending
        $delResp = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('DELETE', "/api/v1/withdrawals/dividend/{$dividend->id}");
        $delResp->assertStatus(200);
        $this->assertSoftDeleted('dividend_withdraws', ['id' => $dividend->id]);
    }

    public function test_approve_dividend_withdrawal_does_not_affect_capital_balance(): void
    {
        // 100,000 capital investment
        Invesments::create([
            'shareholder_id' => $this->shareholder->id,
            'amount'         => '100000.00',
            'recevedBy'      => 'Accounts',
            'recevedDate'    => '2026-10-01',
            'isActive'       => 1,
        ]);

        // Dividend withdrawal of 45,000
        $dividend = DividendWithdraw::create([
            'shareholder_id' => $this->shareholder->id,
            'amount'         => '45000.00',
            'status'         => 'pending',
            'withdraw_date'  => '2026-10-24',
        ]);

        $approveResp = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('POST', "/api/v1/withdrawals/dividend/{$dividend->id}/approve", [
                'remarks' => 'Q3 dividend payout approved',
            ]);

        $approveResp->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Dividend withdrawal request approved successfully',
            ]);

        $this->assertEquals('approved', $approveResp->json('data.status'));
        $this->assertEquals($this->admin->id, $approveResp->json('data.processed_by'));

        // Verify capital statement: Running balance and active capital are 100% UNCHANGED
        $stmtResp = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('GET', "/api/v1/shareholders/{$this->shareholder->id}/statement");

        $stmtResp->assertStatus(200);
        $this->assertEquals(100000.0, $stmtResp->json('data.summary.current_balance'), 'Capital balance must not be affected by dividend payouts');
        $this->assertEquals(0.0, $stmtResp->json('data.summary.total_withdrawn'), 'Dividend withdrawal must not appear in capital withdrawals');
        $this->assertCount(1, $stmtResp->json('data.transactions'), 'Dividend withdrawal must not appear in capital statement ledger');
    }

    public function test_reject_dividend_withdrawal_workflow(): void
    {
        $dividend = DividendWithdraw::create([
            'shareholder_id' => $this->shareholder->id,
            'amount'         => '15000.00',
            'status'         => 'pending',
        ]);

        $rejectResp = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('POST', "/api/v1/withdrawals/dividend/{$dividend->id}/reject", [
                'remarks' => 'Disallowed by board',
            ]);

        $rejectResp->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Dividend withdrawal request rejected successfully',
            ]);

        $this->assertEquals('rejected', $rejectResp->json('data.status'));
        $this->assertEquals($this->admin->id, $rejectResp->json('data.processed_by'));
        $this->assertEquals('Disallowed by board', $rejectResp->json('data.processed_remarks'));

        // Cannot re-process
        $reReject = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('POST', "/api/v1/withdrawals/dividend/{$dividend->id}/reject");
        $reReject->assertStatus(409);
    }

    public function test_cannot_update_rejected_withdrawal_returns_409(): void
    {
        $withdraw = InvestmentWithdraw::create([
            'shareholder_id' => $this->shareholder->id,
            'amount'         => 10000.00,
            'status'         => 'rejected',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('PUT', "/api/v1/withdrawals/investment/{$withdraw->id}", [
                'amount' => 15000.00,
            ]);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_can_delete_rejected_withdrawal(): void
    {
        $withdraw = InvestmentWithdraw::create([
            'shareholder_id' => $this->shareholder->id,
            'amount'         => 10000.00,
            'status'         => 'rejected',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('DELETE', "/api/v1/withdrawals/investment/{$withdraw->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Investment withdrawal request deleted successfully',
            ]);

        $this->assertSoftDeleted('investment_withdraws', ['id' => $withdraw->id]);
    }

    public function test_cannot_delete_approved_dividend_withdrawal_returns_409(): void
    {
        $dividend = DividendWithdraw::create([
            'shareholder_id' => $this->shareholder->id,
            'amount'         => 10000.00,
            'status'         => 'approved',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('DELETE', "/api/v1/withdrawals/dividend/{$dividend->id}");

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot delete withdrawal request: Approved withdrawals are locked for financial audit integrity.',
            ]);

        $this->assertDatabaseHas('dividend_withdraws', [
            'id'         => $dividend->id,
            'deleted_at' => null,
        ]);
    }

    public function test_approve_or_reject_non_existent_withdrawal_returns_404(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('POST', '/api/v1/withdrawals/investment/99999/approve')
            ->assertStatus(404);

        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('POST', '/api/v1/withdrawals/investment/99999/reject')
            ->assertStatus(404);

        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('POST', '/api/v1/withdrawals/dividend/99999/approve')
            ->assertStatus(404);

        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('POST', '/api/v1/withdrawals/dividend/99999/reject')
            ->assertStatus(404);
    }

    public function test_date_range_and_search_filters(): void
    {
        InvestmentWithdraw::create([
            'shareholder_id' => $this->shareholder->id,
            'amount'         => 5000.00,
            'status'         => 'pending',
            'withdraw_date'  => '2026-05-10',
            'method'         => 'Bkash Mobile',
            'receiver_name'  => 'Tareq Personal',
        ]);

        InvestmentWithdraw::create([
            'shareholder_id' => $this->shareholder->id,
            'amount'         => 15000.00,
            'status'         => 'pending',
            'withdraw_date'  => '2026-08-15',
            'method'         => 'Bank Transfer',
            'receiver_name'  => 'Tareq Business',
        ]);

        // Search by method keyword
        $searchResp = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('GET', '/api/v1/withdrawals/investment?search=Bkash');
        $searchResp->assertStatus(200);
        $this->assertCount(1, $searchResp->json('data.data'));

        // Filter by date range
        $dateResp = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('GET', '/api/v1/withdrawals/investment?start_date=2026-08-01&end_date=2026-08-31');
        $dateResp->assertStatus(200);
        $this->assertCount(1, $dateResp->json('data.data'));
        $this->assertEquals('2026-08-15', $dateResp->json('data.data.0.withdraw_date'));
    }

    public function test_soft_deleted_withdrawal_is_excluded_from_queries_and_cannot_be_approved(): void
    {
        Invesments::create([
            'shareholder_id' => $this->shareholder->id,
            'amount'         => '50000.00',
            'recevedBy'      => 'Admin',
            'recevedDate'    => '2026-10-01',
            'isActive'       => 1,
        ]);

        $withdraw = InvestmentWithdraw::create([
            'shareholder_id' => $this->shareholder->id,
            'amount'         => 10000.00,
            'status'         => 'pending',
        ]);

        // Soft delete
        $withdraw->delete();

        // Show returns 404
        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('GET', "/api/v1/withdrawals/investment/{$withdraw->id}")
            ->assertStatus(404);

        // Approve returns 404
        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('POST', "/api/v1/withdrawals/investment/{$withdraw->id}/approve")
            ->assertStatus(404);
    }

    public function test_cannot_create_withdrawal_for_soft_deleted_shareholder_returns_422(): void
    {
        $deletedShareholder = Shareholder::create([
            'name'     => 'Departed Shareholder',
            'email'    => 'departed@example.com',
            'phone'    => '01711999999',
            'isActive' => 0,
        ]);
        $deletedShareholder->delete();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('POST', '/api/v1/withdrawals/investment', [
                'shareholder_id' => $deletedShareholder->id,
                'amount'         => 10000.00,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['shareholder_id']);
    }

    public function test_cannot_approve_withdrawal_if_shareholder_was_deleted_returns_422(): void
    {
        $tempShareholder = Shareholder::create([
            'name'     => 'Temporary Shareholder',
            'email'    => 'temp@example.com',
            'phone'    => '01711888888',
            'isActive' => 1,
        ]);

        $withdraw = InvestmentWithdraw::create([
            'shareholder_id' => $tempShareholder->id,
            'amount'         => 10000.00,
            'status'         => 'pending',
        ]);

        // Delete shareholder prior to approval
        $tempShareholder->delete();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('POST', "/api/v1/withdrawals/investment/{$withdraw->id}/approve");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Associated shareholder record not found or has been deleted.',
            ]);
    }

    public function test_cannot_approve_withdrawal_with_zero_or_negative_amount_returns_422(): void
    {
        $withdraw = InvestmentWithdraw::create([
            'shareholder_id' => $this->shareholder->id,
            'amount'         => 0.00,
            'status'         => 'pending',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('POST', "/api/v1/withdrawals/investment/{$withdraw->id}/approve");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_cannot_delete_legacy_approved_status_1_withdrawal_returns_409(): void
    {
        $legacyWithdraw = InvestmentWithdraw::create([
            'shareholder_id' => $this->shareholder->id,
            'amount'         => 15000.00,
            'status'         => '1', // legacy approved representation
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('DELETE', "/api/v1/withdrawals/investment/{$legacyWithdraw->id}");

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot delete withdrawal request: Approved withdrawals are locked for financial audit integrity.',
            ]);
    }

    public function test_filter_by_status_approved_matches_legacy_status_1_records(): void
    {
        InvestmentWithdraw::create([
            'shareholder_id' => $this->shareholder->id,
            'amount'         => 5000.00,
            'status'         => '1', // legacy approved
        ]);
        InvestmentWithdraw::create([
            'shareholder_id' => $this->shareholder->id,
            'amount'         => 7000.00,
            'status'         => 'approved', // canonical approved
        ]);
        InvestmentWithdraw::create([
            'shareholder_id' => $this->shareholder->id,
            'amount'         => 3000.00,
            'status'         => 'pending',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('GET', '/api/v1/withdrawals/investment?status=approved');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.data'));
    }

    public function test_search_filter_matches_by_amount(): void
    {
        InvestmentWithdraw::create([
            'shareholder_id' => $this->shareholder->id,
            'amount'         => 77777.00,
            'status'         => 'pending',
            'method'         => 'Cash',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->json('GET', '/api/v1/withdrawals/investment?search=77777');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('77777.00', $response->json('data.data.0.amount'));
    }
}
