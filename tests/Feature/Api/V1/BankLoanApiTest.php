<?php

namespace Tests\Feature\Api\V1;

use App\Models\Admin;
use App\Models\BankLoan;
use App\Models\EmiInstallment;
use App\Models\Shareholder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Class BankLoanApiTest
 *
 * Automated regression and functional test suite for Phase 2.7.3:
 * 1. Bank Loans API (CRUD, Search, Pagination, Deletion Guards, Statement, Schedule Generation)
 * 2. EMI Installments API (CRUD, Filtering, Deletion Guards, Payment Lifecycle, Concurrency & Idempotency)
 *
 * Runs exclusively in isolated in-memory SQLite (:memory:).
 *
 * @package Tests\Feature\Api\V1
 */
class BankLoanApiTest extends TestCase
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

    protected function tearDown(): void
    {
        Schema::dropIfExists('emi_installments');
        Schema::dropIfExists('bank_loans');
        Schema::dropIfExists('shareholders');
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

        Schema::create('shareholders', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->date('joining_date')->nullable();
            $table->integer('share_count')->default(0);
            $table->decimal('share_value', 15, 2)->default(0.00);
            $table->decimal('total_investment', 15, 2)->default(0.00);
            $table->string('status')->default('active');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('bank_loans', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('shareholder_id')->nullable();
            $table->string('bank_name', 191)->nullable();
            $table->decimal('loan_amount', 15, 2)->default(0.00);
            $table->decimal('interest_rate', 5, 2)->default(0.00);
            $table->integer('loan_term_months')->default(0);
            $table->date('loan_date')->nullable();
            $table->date('maturity_date')->nullable();
            $table->integer('installment_count')->default(0);
            $table->decimal('savings_amount', 15, 2)->default(0.00);
            $table->string('status', 191)->default('active');
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('emi_installments', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('bank_loan_id')->nullable();
            $table->unsignedBigInteger('shareholder_id')->nullable();
            $table->decimal('emi_amount', 15, 2)->default(0.00);
            $table->decimal('savings_amount', 15, 2)->default(0.00);
            $table->date('payment_date')->nullable();
            $table->string('payment_method', 191)->nullable();
            $table->unsignedBigInteger('paid_by')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('status', 191)->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function seedTestData(): void
    {
        $this->admin = Admin::create([
            'name'     => 'Finance Admin',
            'email'    => 'admin.finance@example.com',
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
            'name'             => 'Haji Mohammad Ali',
            'phone'            => '01711122233',
            'email'            => 'haji.ali@example.com',
            'address'          => 'Dhaka, Bangladesh',
            'joining_date'     => '2024-01-01',
            'share_count'      => 10,
            'share_value'      => 100000.00,
            'total_investment' => 1000000.00,
            'status'           => 'active',
        ]);
    }

    // =========================================================================
    // 1. AUTHENTICATION & AUTHORIZATION BARRIER TESTS
    // =========================================================================

    public function test_unauthenticated_requests_are_denied_for_all_endpoints(): void
    {
        $this->resetAuthGuards();

        $routes = [
            ['GET', '/api/v1/loans/bank-loans'],
            ['POST', '/api/v1/loans/bank-loans'],
            ['GET', '/api/v1/loans/bank-loans/1'],
            ['PUT', '/api/v1/loans/bank-loans/1'],
            ['DELETE', '/api/v1/loans/bank-loans/1'],
            ['GET', '/api/v1/loans/bank-loans/1/statement'],
            ['POST', '/api/v1/loans/bank-loans/1/generate-schedule'],
            ['GET', '/api/v1/loans/installments'],
            ['POST', '/api/v1/loans/installments'],
            ['GET', '/api/v1/loans/installments/1'],
            ['PUT', '/api/v1/loans/installments/1'],
            ['DELETE', '/api/v1/loans/installments/1'],
            ['POST', '/api/v1/loans/installments/1/pay'],
        ];

        foreach ($routes as [$method, $uri]) {
            $response = $this->json($method, $uri);
            $this->assertEquals(
                401,
                $response->status(),
                "Route [{$method} {$uri}] should be 401 Unauthorized for unauthenticated caller."
            );
        }
    }

    public function test_non_admin_identity_is_forbidden_for_all_endpoints(): void
    {
        $this->resetAuthGuards();

        $routes = [
            ['GET', '/api/v1/loans/bank-loans'],
            ['POST', '/api/v1/loans/bank-loans'],
            ['GET', '/api/v1/loans/bank-loans/1'],
            ['PUT', '/api/v1/loans/bank-loans/1'],
            ['DELETE', '/api/v1/loans/bank-loans/1'],
            ['GET', '/api/v1/loans/bank-loans/1/statement'],
            ['POST', '/api/v1/loans/bank-loans/1/generate-schedule'],
            ['GET', '/api/v1/loans/installments'],
            ['POST', '/api/v1/loans/installments'],
            ['GET', '/api/v1/loans/installments/1'],
            ['PUT', '/api/v1/loans/installments/1'],
            ['DELETE', '/api/v1/loans/installments/1'],
            ['POST', '/api/v1/loans/installments/1/pay'],
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
    // 2. BANK LOANS CRUD & VALIDATION TESTS
    // =========================================================================

    public function test_can_create_bank_loan_with_sanitized_amounts(): void
    {
        $this->resetAuthGuards();

        $payload = [
            'shareholder_id'    => $this->shareholder->id,
            'bank_name'         => 'Sonali Bank Ltd.',
            'loan_amount'       => '1,200,000.00',
            'interest_rate'     => '9.50',
            'loan_term_months'  => 12,
            'loan_date'         => '2026-01-01',
            'maturity_date'     => '2027-01-01',
            'installment_count' => 12,
            'savings_amount'    => '10,000.00',
            'remarks'           => 'Commercial equipment financing',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/loans/bank-loans', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.bank_name', 'Sonali Bank Ltd.')
            ->assertJsonPath('data.status', 'active');

        $loanId = $response->json('data.id');
        $loan = BankLoan::find($loanId);
        $this->assertNotNull($loan);
        $this->assertEquals('1200000.00', $loan->loan_amount);
        $this->assertEquals('10000.00', $loan->savings_amount);
        $this->assertEquals($this->admin->id, $loan->created_by);
    }

    public function test_bank_loan_store_validates_required_and_numerical_fields(): void
    {
        $this->resetAuthGuards();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/loans/bank-loans', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'shareholder_id',
                'bank_name',
                'loan_amount',
                'interest_rate',
                'loan_term_months',
                'loan_date',
                'maturity_date',
                'installment_count',
            ]);
    }

    public function test_cannot_create_bank_loan_for_non_existent_shareholder(): void
    {
        $this->resetAuthGuards();

        $payload = [
            'shareholder_id'    => 99999,
            'bank_name'         => 'Islami Bank',
            'loan_amount'       => 500000,
            'interest_rate'     => 8,
            'loan_term_months'  => 6,
            'loan_date'         => '2026-02-01',
            'maturity_date'     => '2026-08-01',
            'installment_count' => 6,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/loans/bank-loans', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['shareholder_id']);
    }

    public function test_can_list_bank_loans_with_search_and_pagination(): void
    {
        $this->resetAuthGuards();

        BankLoan::create([
            'shareholder_id'    => $this->shareholder->id,
            'bank_name'         => 'Dutch Bangla Bank',
            'loan_amount'       => 300000.00,
            'interest_rate'     => 10.00,
            'loan_term_months'  => 10,
            'loan_date'         => '2026-01-01',
            'maturity_date'     => '2026-11-01',
            'installment_count' => 10,
            'status'            => 'active',
        ]);

        BankLoan::create([
            'shareholder_id'    => $this->shareholder->id,
            'bank_name'         => 'Eastern Bank Ltd',
            'loan_amount'       => 500000.00,
            'interest_rate'     => 9.00,
            'loan_term_months'  => 12,
            'loan_date'         => '2026-02-01',
            'maturity_date'     => '2027-02-01',
            'installment_count' => 12,
            'status'            => 'closed',
        ]);

        // General listing
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/loans/bank-loans');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.data');

        // Filter by bank name search
        $responseSearch = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/loans/bank-loans?search=Eastern');

        $responseSearch->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.bank_name', 'Eastern Bank Ltd');

        // Filter by status
        $responseStatus = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/loans/bank-loans?status=closed');

        $responseStatus->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.status', 'closed');
    }

    public function test_can_show_bank_loan_with_computed_financial_metrics(): void
    {
        $this->resetAuthGuards();

        $loan = BankLoan::create([
            'shareholder_id'    => $this->shareholder->id,
            'bank_name'         => 'Brac Bank',
            'loan_amount'       => 120000.00,
            'interest_rate'     => 10.00,
            'loan_term_months'  => 12,
            'loan_date'         => '2026-01-01',
            'maturity_date'     => '2027-01-01',
            'installment_count' => 12,
            'savings_amount'    => 5000.00,
            'status'            => 'active',
        ]);

        // Create 2 paid installments
        EmiInstallment::create([
            'bank_loan_id'   => $loan->id,
            'shareholder_id' => $this->shareholder->id,
            'emi_amount'     => 11000.00,
            'savings_amount' => 500.00,
            'status'         => 'paid',
            'payment_date'   => '2026-02-01',
        ]);
        EmiInstallment::create([
            'bank_loan_id'   => $loan->id,
            'shareholder_id' => $this->shareholder->id,
            'emi_amount'     => 11000.00,
            'savings_amount' => 500.00,
            'status'         => 'paid',
            'payment_date'   => '2026-03-01',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson("/api/v1/loans/bank-loans/{$loan->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $loan->id)
            ->assertJsonPath('data.total_interest', '12000.00')
            ->assertJsonPath('data.total_payable', '132000.00')
            ->assertJsonPath('data.total_paid', '22000.00')
            ->assertJsonPath('data.remaining_balance', '110000.00')
            ->assertJsonPath('data.total_savings', '6000.00');
    }

    public function test_can_update_bank_loan(): void
    {
        $this->resetAuthGuards();

        $loan = BankLoan::create([
            'shareholder_id'    => $this->shareholder->id,
            'bank_name'         => 'City Bank',
            'loan_amount'       => 200000.00,
            'interest_rate'     => 12.00,
            'loan_term_months'  => 6,
            'loan_date'         => '2026-01-01',
            'maturity_date'     => '2026-07-01',
            'installment_count' => 6,
            'status'            => 'active',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->putJson("/api/v1/loans/bank-loans/{$loan->id}", [
                'bank_name' => 'City Bank PLC Updated',
                'remarks'   => 'Updated loan agreement',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.bank_name', 'City Bank PLC Updated')
            ->assertJsonPath('data.remarks', 'Updated loan agreement');

        $this->assertEquals('City Bank PLC Updated', $loan->fresh()->bank_name);
    }

    public function test_cannot_delete_bank_loan_with_paid_installments(): void
    {
        $this->resetAuthGuards();

        $loan = BankLoan::create([
            'shareholder_id'    => $this->shareholder->id,
            'bank_name'         => 'Prime Bank',
            'loan_amount'       => 100000.00,
            'interest_rate'     => 10.00,
            'loan_term_months'  => 5,
            'loan_date'         => '2026-01-01',
            'maturity_date'     => '2026-06-01',
            'installment_count' => 5,
            'status'            => 'active',
        ]);

        EmiInstallment::create([
            'bank_loan_id'   => $loan->id,
            'shareholder_id' => $this->shareholder->id,
            'emi_amount'     => 20000.00,
            'status'         => 'paid',
            'payment_date'   => '2026-02-01',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->deleteJson("/api/v1/loans/bank-loans/{$loan->id}");

        $response->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors', 'LOAN_HAS_PAID_INSTALLMENTS');

        $this->assertNull($loan->fresh()->deleted_at);
    }

    public function test_can_delete_bank_loan_without_paid_installments_and_cascades_pending(): void
    {
        $this->resetAuthGuards();

        $loan = BankLoan::create([
            'shareholder_id'    => $this->shareholder->id,
            'bank_name'         => 'Standard Chartered',
            'loan_amount'       => 100000.00,
            'interest_rate'     => 10.00,
            'loan_term_months'  => 5,
            'loan_date'         => '2026-01-01',
            'maturity_date'     => '2026-06-01',
            'installment_count' => 5,
            'status'            => 'active',
        ]);

        $pendingInstallment = EmiInstallment::create([
            'bank_loan_id'   => $loan->id,
            'shareholder_id' => $this->shareholder->id,
            'emi_amount'     => 20000.00,
            'status'         => 'pending',
            'payment_date'   => '2026-02-01',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->deleteJson("/api/v1/loans/bank-loans/{$loan->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertNotNull($loan->fresh()->deleted_at);
        $this->assertNotNull($pendingInstallment->fresh()->deleted_at);
    }

    // =========================================================================
    // 3. STATEMENT & EMI SCHEDULE GENERATION TESTS
    // =========================================================================

    public function test_statement_endpoint_computes_exact_financial_summary_and_schedule(): void
    {
        $this->resetAuthGuards();

        $loan = BankLoan::create([
            'shareholder_id'    => $this->shareholder->id,
            'bank_name'         => 'National Bank Ltd',
            'loan_amount'       => 120000.00,
            'interest_rate'     => 10.00,
            'loan_term_months'  => 12,
            'loan_date'         => '2026-01-01',
            'maturity_date'     => '2027-01-01',
            'installment_count' => 12,
            'savings_amount'    => 6000.00,
            'status'            => 'active',
        ]);

        // 1 installment paid, 1 pending
        EmiInstallment::create([
            'bank_loan_id'   => $loan->id,
            'shareholder_id' => $this->shareholder->id,
            'emi_amount'     => 11000.00,
            'savings_amount' => 500.00,
            'status'         => 'paid',
            'payment_date'   => '2026-02-01',
        ]);
        EmiInstallment::create([
            'bank_loan_id'   => $loan->id,
            'shareholder_id' => $this->shareholder->id,
            'emi_amount'     => 11000.00,
            'savings_amount' => 500.00,
            'status'         => 'pending',
            'payment_date'   => '2026-03-01',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson("/api/v1/loans/bank-loans/{$loan->id}/statement");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.loan.id', $loan->id)
            ->assertJsonPath('data.financial_summary.total_interest', '12000.00')
            ->assertJsonPath('data.financial_summary.total_payable', '132000.00')
            ->assertJsonPath('data.financial_summary.total_emi_paid', '11000.00')
            ->assertJsonPath('data.financial_summary.remaining_balance', '121000.00')
            ->assertJsonPath('data.financial_summary.total_savings', '6500.00')
            ->assertJsonCount(2, 'data.installments');
    }

    public function test_generate_schedule_handles_uneven_division_and_cent_remainder(): void
    {
        $this->resetAuthGuards();

        // 100,000 / 3 at 0% interest -> total payable = 100,000.00
        // Base installment: 33,333.33 x 3 = 99,999.99 -> remainder 0.01 added to 3rd installment
        $loan = BankLoan::create([
            'shareholder_id'    => $this->shareholder->id,
            'bank_name'         => 'Dutch Bangla Bank',
            'loan_amount'       => 100000.00,
            'interest_rate'     => 0.00,
            'loan_term_months'  => 3,
            'loan_date'         => '2026-01-01',
            'maturity_date'     => '2026-04-01',
            'installment_count' => 3,
            'status'            => 'active',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/v1/loans/bank-loans/{$loan->id}/generate-schedule", [
                'start_date'               => '2026-02-01',
                'savings_per_installment'  => '250.00',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.installments_created', 3)
            ->assertJsonPath('data.total_payable', '100000.00');

        $installments = EmiInstallment::where('bank_loan_id', $loan->id)
            ->orderBy('id', 'asc')
            ->get();

        $this->assertCount(3, $installments);
        $this->assertEquals('33333.33', $installments[0]->emi_amount);
        $this->assertEquals('33333.33', $installments[1]->emi_amount);
        $this->assertEquals('33333.34', $installments[2]->emi_amount); // Remainder 0.01 added here
        $this->assertEquals('250.00', $installments[0]->savings_amount);

        // Check dates
        $this->assertEquals('2026-02-01', $installments[0]->payment_date->format('Y-m-d'));
        $this->assertEquals('2026-03-01', $installments[1]->payment_date->format('Y-m-d'));
        $this->assertEquals('2026-04-01', $installments[2]->payment_date->format('Y-m-d'));

        // Exact sum check
        $totalSum = bcadd(bcadd($installments[0]->emi_amount, $installments[1]->emi_amount, 2), $installments[2]->emi_amount, 2);
        $this->assertEquals('100000.00', $totalSum);
    }

    public function test_cannot_regenerate_schedule_if_paid_installments_exist(): void
    {
        $this->resetAuthGuards();

        $loan = BankLoan::create([
            'shareholder_id'    => $this->shareholder->id,
            'bank_name'         => 'Pubali Bank',
            'loan_amount'       => 50000.00,
            'interest_rate'     => 10.00,
            'loan_term_months'  => 5,
            'loan_date'         => '2026-01-01',
            'maturity_date'     => '2026-06-01',
            'installment_count' => 5,
            'status'            => 'active',
        ]);

        EmiInstallment::create([
            'bank_loan_id'   => $loan->id,
            'shareholder_id' => $this->shareholder->id,
            'emi_amount'     => 10000.00,
            'status'         => 'paid',
            'payment_date'   => '2026-02-01',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/v1/loans/bank-loans/{$loan->id}/generate-schedule", [
                'overwrite_existing' => true,
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors', 'SCHEDULE_HAS_PAID_INSTALLMENTS');
    }

    public function test_generate_schedule_overwrites_pending_installments_when_flag_is_true(): void
    {
        $this->resetAuthGuards();

        $loan = BankLoan::create([
            'shareholder_id'    => $this->shareholder->id,
            'bank_name'         => 'UCB Bank',
            'loan_amount'       => 60000.00,
            'interest_rate'     => 10.00,
            'loan_term_months'  => 6,
            'loan_date'         => '2026-01-01',
            'maturity_date'     => '2026-07-01',
            'installment_count' => 2,
            'status'            => 'active',
        ]);

        EmiInstallment::create([
            'bank_loan_id'   => $loan->id,
            'shareholder_id' => $this->shareholder->id,
            'emi_amount'     => 30000.00,
            'status'         => 'pending',
            'payment_date'   => '2026-02-01',
        ]);

        // Attempting to generate without overwrite_existing should fail
        $responseFail = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/v1/loans/bank-loans/{$loan->id}/generate-schedule", []);

        $responseFail->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors', 'SCHEDULE_ALREADY_EXISTS');

        // With overwrite_existing = true
        $responseSuccess = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/v1/loans/bank-loans/{$loan->id}/generate-schedule", [
                'overwrite_existing' => true,
            ]);

        $responseSuccess->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.installments_created', 2);

        $this->assertEquals(2, EmiInstallment::where('bank_loan_id', $loan->id)->count());
    }

    // =========================================================================
    // 4. EMI INSTALLMENTS CRUD & VALIDATION TESTS
    // =========================================================================

    public function test_can_list_installments_with_filters(): void
    {
        $this->resetAuthGuards();

        $loan1 = BankLoan::create([
            'shareholder_id'    => $this->shareholder->id,
            'bank_name'         => 'Bank 1',
            'loan_amount'       => 100000.00,
            'interest_rate'     => 10.00,
            'loan_term_months'  => 10,
            'loan_date'         => '2026-01-01',
            'maturity_date'     => '2026-11-01',
            'installment_count' => 10,
            'status'            => 'active',
        ]);

        $loan2 = BankLoan::create([
            'shareholder_id'    => $this->shareholder->id,
            'bank_name'         => 'Bank 2',
            'loan_amount'       => 50000.00,
            'interest_rate'     => 8.00,
            'loan_term_months'  => 5,
            'loan_date'         => '2026-01-01',
            'maturity_date'     => '2026-06-01',
            'installment_count' => 5,
            'status'            => 'active',
        ]);

        EmiInstallment::create([
            'bank_loan_id'   => $loan1->id,
            'shareholder_id' => $this->shareholder->id,
            'emi_amount'     => 10000.00,
            'status'         => 'paid',
            'payment_date'   => '2026-02-01',
        ]);

        EmiInstallment::create([
            'bank_loan_id'   => $loan2->id,
            'shareholder_id' => $this->shareholder->id,
            'emi_amount'     => 10000.00,
            'status'         => 'pending',
            'payment_date'   => '2026-02-01',
        ]);

        // Filter by bank_loan_id
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson("/api/v1/loans/installments?bank_loan_id={$loan1->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.bank_loan_id', $loan1->id);

        // Filter by status
        $responsePaid = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/loans/installments?status=paid');

        $responsePaid->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.status', 'paid');
    }

    public function test_can_store_manual_installment(): void
    {
        $this->resetAuthGuards();

        $loan = BankLoan::create([
            'shareholder_id'    => $this->shareholder->id,
            'bank_name'         => 'Janata Bank',
            'loan_amount'       => 100000.00,
            'interest_rate'     => 10.00,
            'loan_term_months'  => 10,
            'loan_date'         => '2026-01-01',
            'maturity_date'     => '2026-11-01',
            'installment_count' => 10,
            'status'            => 'active',
        ]);

        $payload = [
            'bank_loan_id'   => $loan->id,
            'shareholder_id' => $this->shareholder->id,
            'emi_amount'     => '11,000.00',
            'savings_amount' => '500.00',
            'payment_date'   => '2026-03-01',
            'status'         => 'pending',
            'remarks'        => 'Manual installment entry',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/loans/installments', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.emi_amount', '11000.00')
            ->assertJsonPath('data.savings_amount', '500.00')
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_cannot_update_monetary_amount_of_paid_installment(): void
    {
        $this->resetAuthGuards();

        $loan = BankLoan::create([
            'shareholder_id'    => $this->shareholder->id,
            'bank_name'         => 'Agrani Bank',
            'loan_amount'       => 100000.00,
            'interest_rate'     => 10.00,
            'loan_term_months'  => 10,
            'loan_date'         => '2026-01-01',
            'maturity_date'     => '2026-11-01',
            'installment_count' => 10,
            'status'            => 'active',
        ]);

        $installment = EmiInstallment::create([
            'bank_loan_id'   => $loan->id,
            'shareholder_id' => $this->shareholder->id,
            'emi_amount'     => 10000.00,
            'status'         => 'paid',
            'payment_date'   => '2026-02-01',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->putJson("/api/v1/loans/installments/{$installment->id}", [
                'emi_amount' => '15000.00',
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors', 'PAID_INSTALLMENT_AMOUNT_IMMUTABLE');
    }

    public function test_cannot_delete_paid_installment(): void
    {
        $this->resetAuthGuards();

        $loan = BankLoan::create([
            'shareholder_id'    => $this->shareholder->id,
            'bank_name'         => 'Rupali Bank',
            'loan_amount'       => 100000.00,
            'interest_rate'     => 10.00,
            'loan_term_months'  => 10,
            'loan_date'         => '2026-01-01',
            'maturity_date'     => '2026-11-01',
            'installment_count' => 10,
            'status'            => 'active',
        ]);

        $installment = EmiInstallment::create([
            'bank_loan_id'   => $loan->id,
            'shareholder_id' => $this->shareholder->id,
            'emi_amount'     => 10000.00,
            'status'         => 'paid',
            'payment_date'   => '2026-02-01',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->deleteJson("/api/v1/loans/installments/{$installment->id}");

        $response->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors', 'CANNOT_DELETE_PAID_INSTALLMENT');

        $this->assertNull($installment->fresh()->deleted_at);
    }

    public function test_can_delete_pending_installment(): void
    {
        $this->resetAuthGuards();

        $loan = BankLoan::create([
            'shareholder_id'    => $this->shareholder->id,
            'bank_name'         => 'Dhaka Bank',
            'loan_amount'       => 100000.00,
            'interest_rate'     => 10.00,
            'loan_term_months'  => 10,
            'loan_date'         => '2026-01-01',
            'maturity_date'     => '2026-11-01',
            'installment_count' => 10,
            'status'            => 'active',
        ]);

        $installment = EmiInstallment::create([
            'bank_loan_id'   => $loan->id,
            'shareholder_id' => $this->shareholder->id,
            'emi_amount'     => 10000.00,
            'status'         => 'pending',
            'payment_date'   => '2026-02-01',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->deleteJson("/api/v1/loans/installments/{$installment->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertNotNull($installment->fresh()->deleted_at);
    }

    // =========================================================================
    // 5. PAYMENT LIFECYCLE, CONCURRENCY, & AUTO-CLOSE TESTS
    // =========================================================================

    public function test_pay_installment_transitions_status_to_paid_and_records_audit(): void
    {
        $this->resetAuthGuards();

        $loan = BankLoan::create([
            'shareholder_id'    => $this->shareholder->id,
            'bank_name'         => 'Trust Bank',
            'loan_amount'       => 50000.00,
            'interest_rate'     => 10.00,
            'loan_term_months'  => 5,
            'loan_date'         => '2026-01-01',
            'maturity_date'     => '2026-06-01',
            'installment_count' => 5,
            'status'            => 'active',
        ]);

        $installment = EmiInstallment::create([
            'bank_loan_id'   => $loan->id,
            'shareholder_id' => $this->shareholder->id,
            'emi_amount'     => 10000.00,
            'savings_amount' => 500.00,
            'status'         => 'pending',
            'payment_date'   => '2026-02-01',
        ]);

        $payload = [
            'payment_date'   => '2026-02-05',
            'payment_method' => 'bank_transfer',
            'remarks'        => 'Cleared via online banking',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/v1/loans/installments/{$installment->id}/pay", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.payment_method', 'bank_transfer')
            ->assertJsonPath('data.received_by', $this->admin->id)
            ->assertJsonPath('meta.loan_remaining_balance', 42083.33);

        $fresh = $installment->fresh();
        $this->assertEquals('paid', $fresh->status);
        $this->assertEquals('bank_transfer', $fresh->payment_method);
        $this->assertEquals('2026-02-05', $fresh->payment_date->format('Y-m-d'));
        $this->assertNotNull($fresh->paid_at);
        $this->assertEquals($this->admin->id, $fresh->received_by);
    }

    public function test_pay_installment_idempotency_prevents_duplicate_payments(): void
    {
        $this->resetAuthGuards();

        $loan = BankLoan::create([
            'shareholder_id'    => $this->shareholder->id,
            'bank_name'         => 'One Bank',
            'loan_amount'       => 50000.00,
            'interest_rate'     => 10.00,
            'loan_term_months'  => 5,
            'loan_date'         => '2026-01-01',
            'maturity_date'     => '2026-06-01',
            'installment_count' => 5,
            'status'            => 'active',
        ]);

        $installment = EmiInstallment::create([
            'bank_loan_id'   => $loan->id,
            'shareholder_id' => $this->shareholder->id,
            'emi_amount'     => 10000.00,
            'status'         => 'pending',
            'payment_date'   => '2026-02-01',
        ]);

        // First payment succeeds
        $res1 = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/v1/loans/installments/{$installment->id}/pay", [
                'payment_method' => 'cash',
            ]);
        $res1->assertStatus(200);

        // Immediate second attempt fails with 409 Conflict
        $res2 = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/v1/loans/installments/{$installment->id}/pay", [
                'payment_method' => 'cash',
            ]);

        $res2->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors', 'INSTALLMENT_ALREADY_PAID');
    }

    public function test_pay_installment_auto_closes_bank_loan_when_all_installments_are_paid(): void
    {
        $this->resetAuthGuards();

        $loan = BankLoan::create([
            'shareholder_id'    => $this->shareholder->id,
            'bank_name'         => 'Mutual Trust Bank',
            'loan_amount'       => 20000.00,
            'interest_rate'     => 0.00,
            'loan_term_months'  => 2,
            'loan_date'         => '2026-01-01',
            'maturity_date'     => '2026-03-01',
            'installment_count' => 2,
            'status'            => 'active',
        ]);

        // Installment 1 already paid
        EmiInstallment::create([
            'bank_loan_id'   => $loan->id,
            'shareholder_id' => $this->shareholder->id,
            'emi_amount'     => 10000.00,
            'status'         => 'paid',
            'payment_date'   => '2026-02-01',
        ]);

        // Installment 2 is pending (final installment)
        $installment2 = EmiInstallment::create([
            'bank_loan_id'   => $loan->id,
            'shareholder_id' => $this->shareholder->id,
            'emi_amount'     => 10000.00,
            'status'         => 'pending',
            'payment_date'   => '2026-03-01',
        ]);

        $this->assertEquals('active', $loan->status);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/v1/loans/installments/{$installment2->id}/pay", [
                'payment_method' => 'cash',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.loan_remaining_balance', 0)
            ->assertJsonPath('meta.loan_status', 'closed');

        // Parent bank loan should now be auto-closed
        $this->assertEquals('closed', $loan->fresh()->status);
    }

    public function test_cannot_pay_soft_deleted_installment(): void
    {
        $this->resetAuthGuards();

        $loan = BankLoan::create([
            'shareholder_id'    => $this->shareholder->id,
            'bank_name'         => 'AB Bank',
            'loan_amount'       => 10000.00,
            'interest_rate'     => 0.00,
            'loan_term_months'  => 1,
            'loan_date'         => '2026-01-01',
            'maturity_date'     => '2026-02-01',
            'installment_count' => 1,
            'status'            => 'active',
        ]);

        $installment = EmiInstallment::create([
            'bank_loan_id'   => $loan->id,
            'shareholder_id' => $this->shareholder->id,
            'emi_amount'     => 10000.00,
            'status'         => 'pending',
            'payment_date'   => '2026-02-01',
        ]);

        $installment->delete();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/v1/loans/installments/{$installment->id}/pay", [
                'payment_method' => 'cash',
            ]);

        $response->assertStatus(404);
    }
}
