<?php

namespace Tests\Feature\Api\V1;

use App\Http\Controllers\Admin\SMSController;
use App\Models\Admin;
use App\Models\AssetCourse;
use App\Models\AssetRegistration;
use App\Models\Batchs;
use App\Models\Courses;
use App\Models\FeesTransections;
use App\Models\Setting;
use App\Models\SmsLog;
use App\Models\Students;
use App\Models\User;
use App\Services\SmsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Class SmsAndSettingsApiTest
 *
 * Automated regression and functional test suite for Phase 2.9:
 * 1. Public & Admin System Settings endpoints
 * 2. Strict credential masking & protection against arbitrary .env/config tampering
 * 3. SMS Provider Configuration retrieval & update
 * 4. SMS Approved Template Catalog & customization
 * 5. SMS Direct, Template, and Batch dispatch with mocked provider calls
 * 6. Phone number normalization (01..., +8801..., 8801...)
 * 7. Duplicate send prevention (60-second idempotency window)
 * 8. Provider error, network failure, and timeout resilience
 * 9. SMS delivery audit logs retrieval and filtering
 * 10. Gateway provider connectivity check
 * 11. Legacy SMSController backward compatibility regression verification
 *
 * Runs exclusively in isolated in-memory SQLite (:memory:).
 *
 * @package Tests\Feature\Api\V1
 */
class SmsAndSettingsApiTest extends TestCase
{
    protected Admin $admin;
    protected User $studentUser;

    protected string $adminToken;
    protected string $studentToken;

    protected Courses $course;
    protected Batchs $batch;
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

        // Default mock for SMS service
        SmsService::fake();
    }

    protected function tearDown(): void
    {
        SmsService::resetFake();

        Schema::dropIfExists('sms_logs');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('fees_transections');
        Schema::dropIfExists('students');
        Schema::dropIfExists('batchs');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('asset_registrations');
        Schema::dropIfExists('asset_courses');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('admins');

        parent::tearDown();
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

        Schema::create('settings', function ($table) {
            $table->bigIncrements('id');
            $table->string('key', 150)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('sms_logs', function ($table) {
            $table->bigIncrements('id');
            $table->string('recipient', 30)->index();
            $table->text('message');
            $table->string('template_key', 60)->nullable()->index();
            $table->string('provider', 50)->default('elitbuzz');
            $table->string('status', 20)->default('queued')->index();
            $table->text('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('sent_by')->nullable()->index();
            $table->unsignedBigInteger('batch_id')->nullable()->index();
            $table->unsignedBigInteger('student_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('courses', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('courseduration')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('batchs', function ($table) {
            $table->id();
            $table->string('batchnum');
            $table->unsignedBigInteger('course_id')->nullable();
            $table->string('schedule1')->nullable();
            $table->string('schedule2')->nullable();
            $table->string('schedule3')->nullable();
            $table->string('schedule4')->nullable();
            $table->string('schedule5')->nullable();
            $table->string('schedule6')->nullable();
            $table->string('schedule7')->nullable();
            $table->integer('day1')->default(0);
            $table->integer('day2')->default(0);
            $table->integer('day3')->default(0);
            $table->integer('day4')->default(0);
            $table->integer('day5')->default(0);
            $table->integer('day6')->default(0);
            $table->integer('day7')->default(0);
            $table->integer('IsActive')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('students', function ($table) {
            $table->id();
            $table->string('sname');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->decimal('balance', 10, 2)->default(10000.00);
            $table->integer('IsActive')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('fees_transections', function ($table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->decimal('paidamount', 10, 2);
            $table->integer('paymentType')->default(1); // 1 = regular
            $table->timestamps();
        });

        Schema::create('asset_courses', function ($table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('asset_registrations', function ($table) {
            $table->id();
            $table->string('full_name')->nullable();
            $table->string('name')->nullable();
            $table->string('mobile');
            $table->string('whatsapp')->nullable();
            $table->string('course')->nullable();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->string('registration_status')->default('Registered');
            $table->timestamps();
        });
    }

    protected function seedTestData(): void
    {
        $this->admin = Admin::create([
            'name'     => 'Super Administrator',
            'email'    => 'admin@innovabd.com',
            'password' => Hash::make('password123'),
        ]);
        $this->adminToken = $this->admin->createToken('admin-token')->plainTextToken;

        $this->studentUser = User::create([
            'name'     => 'Student User',
            'email'    => 'student@innovabd.com',
            'password' => Hash::make('password123'),
        ]);
        $this->studentToken = $this->studentUser->createToken('student-token')->plainTextToken;

        // Seed Course & Batch
        $this->course = Courses::create([
            'name'           => 'Professional Web Development',
            'courseduration' => '6 Months',
        ]);

        $this->batch = Batchs::create([
            'batchnum'  => 'PWD-101',
            'course_id' => $this->course->id,
            'schedule1' => '09:00 AM - 11:00 AM',
            'schedule2' => '11:00 AM - 01:00 PM',
            'schedule3' => '03:00 PM - 05:00 PM',
            'schedule4' => '05:00 PM - 07:00 PM',
            'schedule5' => '09:00 AM - 11:00 AM',
            'schedule6' => '11:00 AM - 01:00 PM',
            'schedule7' => '03:00 PM - 05:00 PM',
            'day1'      => 1,
            'day2'      => 1,
            'day3'      => 0,
            'day4'      => 0,
            'day5'      => 0,
            'day6'      => 0,
            'day7'      => 0,
            'IsActive'  => 1,
        ]);

        // Seed Students
        $this->student1 = Students::create([
            'sname'     => 'Rahim Uddin',
            'phone'     => '01844463954',
            'email'     => 'rahim@test.com',
            'course_id' => $this->course->id,
            'batch_id'  => $this->batch->id,
            'balance'   => 12000.00,
            'IsActive'  => 1,
        ]);

        $this->student2 = Students::create([
            'sname'     => 'Karim Mia',
            'phone'     => '01711223344',
            'email'     => 'karim@test.com',
            'course_id' => $this->course->id,
            'batch_id'  => $this->batch->id,
            'balance'   => 10000.00,
            'IsActive'  => 1,
        ]);

        FeesTransections::create([
            'student_id'  => $this->student1->id,
            'paidamount'  => 5000.00,
            'paymentType' => 1,
        ]);

        // Seed Initial Settings
        Setting::set('site_name', 'INNOVA Computers BD');
        Setting::set('site_title', 'Leading IT Training Institute');
        Setting::set('default_email_address', 'info@innovabd.com');
        Setting::set('currency_code', 'BDT');
        Setting::set('currency_symbol', '৳');
        Setting::set('contact_phone', '01844463954');
        Setting::set('sms_enabled', '1');
        Setting::set('sms_provider', 'elitbuzz');
        Setting::set('sms_url', 'https://api.elitbuzz-bd.com/smsapi');
        Setting::set('sms_sender_id', 'INNOVA BD');
        Setting::set('sms_api_key', 'test_secret_api_key_1234567890');
        Setting::set('stripe_secret_key', 'sk_live_99998888777766665555');
    }

    // =========================================================================
    // SECTION 1: AUTHENTICATION & ACCESS CONTROL TESTS
    // =========================================================================

    public function test_unauthenticated_request_to_protected_settings_and_sms_is_rejected(): void
    {
        $this->getJson('/api/v1/settings')
            ->assertStatus(401);

        $this->putJson('/api/v1/settings', ['settings' => ['site_name' => 'New Name']])
            ->assertStatus(401);

        $this->getJson('/api/v1/settings/sms')
            ->assertStatus(401);

        $this->getJson('/api/v1/sms/logs')
            ->assertStatus(401);

        $this->postJson('/api/v1/sms/send', [
            'recipient' => '01844463954',
            'message'   => 'Test',
        ])->assertStatus(401);
    }

    public function test_student_user_is_forbidden_from_administrative_settings_and_sms(): void
    {
        $headers = ['Authorization' => 'Bearer ' . $this->studentToken];

        $this->getJson('/api/v1/settings', $headers)
            ->assertStatus(403);

        $this->putJson('/api/v1/settings', ['settings' => ['site_name' => 'New Name']], $headers)
            ->assertStatus(403);

        $this->getJson('/api/v1/settings/sms', $headers)
            ->assertStatus(403);

        $this->getJson('/api/v1/sms/logs', $headers)
            ->assertStatus(403);

        $this->postJson('/api/v1/sms/send', [
            'recipient' => '01844463954',
            'message'   => 'Test',
        ], $headers)->assertStatus(403);
    }

    // =========================================================================
    // SECTION 2: PUBLIC SETTINGS ENDPOINT TESTS
    // =========================================================================

    public function test_public_settings_are_accessible_without_authentication_and_leak_no_secrets(): void
    {
        $response = $this->getJson('/api/v1/settings/public');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'site_name'             => 'INNOVA Computers BD',
                    'site_title'            => 'Leading IT Training Institute',
                    'default_email_address' => 'info@innovabd.com',
                    'currency_code'         => 'BDT',
                    'currency_symbol'       => '৳',
                    'contact_phone'         => '01844463954',
                ],
            ]);

        // CRITICAL PRIVACY ASSERTION: No secrets or provider credentials in public response
        $data = $response->json('data');
        $this->assertArrayNotHasKey('sms_api_key', $data);
        $this->assertArrayNotHasKey('stripe_secret_key', $data);
        $this->assertArrayNotHasKey('paypal_secret_id', $data);
        $this->assertArrayNotHasKey('bkash_app_secret', $data);
        $this->assertArrayNotHasKey('bkash_password', $data);
    }

    // =========================================================================
    // SECTION 3: ADMIN SETTINGS RETRIEVAL & SECRET MASKING TESTS
    // =========================================================================

    public function test_admin_settings_index_masks_all_sensitive_credentials(): void
    {
        $response = $this->getJson('/api/v1/settings', [
            'Authorization' => 'Bearer ' . $this->adminToken,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'categories' => [
                        'general' => [
                            'site_name' => 'INNOVA Computers BD',
                        ],
                    ],
                ],
            ]);

        $settings = $response->json('data.settings');

        // Verify sensitive keys are masked, never raw plaintext
        $this->assertNotEquals('test_secret_api_key_1234567890', $settings['sms_api_key']);
        $this->assertStringContainsString('•', $settings['sms_api_key']);
        $this->assertStringStartsWith('test', $settings['sms_api_key']);
        $this->assertStringEndsWith('7890', $settings['sms_api_key']);

        $this->assertNotEquals('sk_live_99998888777766665555', $settings['stripe_secret_key']);
        $this->assertStringContainsString('•', $settings['stripe_secret_key']);
    }

    // =========================================================================
    // SECTION 4: SETTINGS UPDATE & TAMPERING PREVENTION TESTS
    // =========================================================================

    public function test_updating_permitted_settings_succeeds(): void
    {
        $payload = [
            'settings' => [
                'site_name'       => 'INNOVA IT Academy',
                'contact_phone'   => '01999888777',
                'contact_address' => 'Mirpur-10, Dhaka',
            ],
        ];

        $response = $this->putJson('/api/v1/settings', $payload, [
            'Authorization' => 'Bearer ' . $this->adminToken,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'updated' => [
                        'site_name'       => 'INNOVA IT Academy',
                        'contact_phone'   => '01999888777',
                        'contact_address' => 'Mirpur-10, Dhaka',
                    ],
                ],
            ]);

        $this->assertEquals('INNOVA IT Academy', Setting::get('site_name'));
        $this->assertEquals('01999888777', Setting::get('contact_phone'));
    }

    public function test_updating_unpermitted_or_arbitrary_config_keys_is_rejected(): void
    {
        $payload = [
            'settings' => [
                'site_name'             => 'Valid Name',
                'app_key'               => 'malicious_overwrite',
                'database_password'     => 'hacked',
                'arbitrary_env_payload' => 'exploit',
            ],
        ];

        $response = $this->putJson('/api/v1/settings', $payload, [
            'Authorization' => 'Bearer ' . $this->adminToken,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'settings.app_key',
                'settings.database_password',
                'settings.arbitrary_env_payload',
            ]);

        // Verify no change occurred to site_name because transaction/validation aborted
        $this->assertNotEquals('Valid Name', Setting::get('site_name'));
    }

    public function test_masked_secret_sent_back_is_preserved_and_not_overwritten(): void
    {
        $originalSecret = Setting::get('stripe_secret_key');
        $maskedString = Setting::maskSecret($originalSecret);

        $payload = [
            'settings' => [
                'site_title'        => 'Updated Title',
                'stripe_secret_key' => $maskedString, // Sent back by UI without changes
            ],
        ];

        $response = $this->putJson('/api/v1/settings', $payload, [
            'Authorization' => 'Bearer ' . $this->adminToken,
        ]);

        $response->assertStatus(200);

        // Verify the original secret is intact in database
        $this->assertEquals($originalSecret, Setting::get('stripe_secret_key'));
        $this->assertEquals('Updated Title', Setting::get('site_title'));
    }

    public function test_providing_a_new_unmasked_secret_updates_it_correctly(): void
    {
        $newSecret = 'sk_live_new_updated_production_secret_9999';

        $payload = [
            'settings' => [
                'stripe_secret_key' => $newSecret,
            ],
        ];

        $response = $this->putJson('/api/v1/settings', $payload, [
            'Authorization' => 'Bearer ' . $this->adminToken,
        ]);

        $response->assertStatus(200);
        $this->assertEquals($newSecret, Setting::get('stripe_secret_key'));
    }

    // =========================================================================
    // SECTION 5: SMS PROVIDER CONFIGURATION TESTS
    // =========================================================================

    public function test_get_sms_settings_returns_masked_provider_details(): void
    {
        $response = $this->getJson('/api/v1/settings/sms', [
            'Authorization' => 'Bearer ' . $this->adminToken,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'enabled'   => true,
                    'provider'  => 'elitbuzz',
                    'url'       => 'https://api.elitbuzz-bd.com/smsapi',
                    'sender_id' => 'INNOVA BD',
                ],
            ]);

        $apiKey = $response->json('data.api_key');
        $this->assertStringContainsString('•', $apiKey);
    }

    public function test_update_sms_settings_updates_configuration(): void
    {
        $payload = [
            'sms_enabled'   => '1',
            'sms_sender_id' => 'INNOVA TECH',
            'sms_url'       => 'https://api.elitbuzz-bd.com/v2/smsapi',
        ];

        $response = $this->putJson('/api/v1/settings/sms', $payload, [
            'Authorization' => 'Bearer ' . $this->adminToken,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'sender_id' => 'INNOVA TECH',
                    'url'       => 'https://api.elitbuzz-bd.com/v2/smsapi',
                ],
            ]);

        $this->assertEquals('INNOVA TECH', Setting::get('sms_sender_id'));
        $this->assertEquals('https://api.elitbuzz-bd.com/v2/smsapi', Setting::get('sms_url'));
    }

    // =========================================================================
    // SECTION 6: SMS TEMPLATES CATALOG & CUSTOMIZATION TESTS
    // =========================================================================

    public function test_get_templates_returns_all_approved_templates(): void
    {
        $response = $this->getJson('/api/v1/sms/templates', [
            'Authorization' => 'Bearer ' . $this->adminToken,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $templates = collect($response->json('data'));
        $this->assertCount(9, $templates);

        $dueTemplate = $templates->firstWhere('key', 'due_reminder');
        $this->assertNotNull($dueTemplate);
        $this->assertEquals('Course Fee Due Reminder', $dueTemplate['name']);
        $this->assertContains('{due}', $dueTemplate['placeholders']);
    }

    public function test_update_template_persists_custom_template_override(): void
    {
        $customText = 'Dear {name}, Please settle your due amount of {due} BDT immediately. Thanks!';

        $response = $this->putJson('/api/v1/sms/templates/due_reminder', [
            'template_text' => $customText,
        ], [
            'Authorization' => 'Bearer ' . $this->adminToken,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'key'          => 'due_reminder',
                    'current_text' => $customText,
                    'is_custom'    => true,
                ],
            ]);

        $this->assertEquals($customText, Setting::get('sms_template_due_reminder'));
    }

    public function test_update_template_with_invalid_key_returns_404(): void
    {
        $this->putJson('/api/v1/sms/templates/non_existent_key', [
            'template_text' => 'Custom text',
        ], [
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->assertStatus(404);
    }

    // =========================================================================
    // SECTION 7: DIRECT SMS SENDING TESTS
    // =========================================================================

    public function test_send_direct_sms_normalizes_bangladeshi_numbers_and_logs_delivery(): void
    {
        $testNumbers = [
            '01844463954'     => '8801844463954',
            '+8801844463954'  => '8801844463954',
            '8801844463954'   => '8801844463954',
        ];

        foreach ($testNumbers as $inputPhone => $expectedNormalized) {
            $msg = 'Notice: Special exam batch registration is open. Ref #' . mt_rand(100, 999);

            $response = $this->postJson('/api/v1/sms/send', [
                'recipient' => $inputPhone,
                'message'   => $msg,
            ], [
                'Authorization' => 'Bearer ' . $this->adminToken,
            ]);

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data'    => [
                        'status'    => 'sent',
                        'recipient' => $expectedNormalized,
                    ],
                ]);

            // Verify logged in sms_logs table
            $log = SmsLog::where('recipient', $expectedNormalized)
                ->where('message', $msg)
                ->first();

            $this->assertNotNull($log);
            $this->assertEquals('sent', $log->status);
            $this->assertEquals($this->admin->id, $log->sent_by);
        }
    }

    public function test_send_direct_sms_rejects_invalid_phone_numbers(): void
    {
        $invalidNumbers = ['12345', '01234567890', '+12025550199', 'abcd'];

        foreach ($invalidNumbers as $badNumber) {
            $this->postJson('/api/v1/sms/send', [
                'recipient' => $badNumber,
                'message'   => 'Test Message',
            ], [
                'Authorization' => 'Bearer ' . $this->adminToken,
            ])->assertStatus(422)
                ->assertJsonValidationErrors(['recipient']);
        }
    }

    public function test_send_direct_sms_rejects_empty_or_excessive_message(): void
    {
        $this->postJson('/api/v1/sms/send', [
            'recipient' => '01844463954',
            'message'   => '',
        ], [
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['message']);

        $this->postJson('/api/v1/sms/send', [
            'recipient' => '01844463954',
            'message'   => str_repeat('A', 1001),
        ], [
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    public function test_duplicate_send_prevention_blocks_identical_sms_within_60_seconds(): void
    {
        $message = 'Important meeting today at 4 PM.';

        // First dispatch succeeds
        $first = $this->postJson('/api/v1/sms/send', [
            'recipient' => '01844463954',
            'message'   => $message,
        ], [
            'Authorization' => 'Bearer ' . $this->adminToken,
        ]);
        $first->assertStatus(200);

        // Immediate retry with identical recipient and content is caught by idempotency guard
        $retry = $this->postJson('/api/v1/sms/send', [
            'recipient' => '01844463954',
            'message'   => $message,
        ], [
            'Authorization' => 'Bearer ' . $this->adminToken,
        ]);

        $retry->assertStatus(422)
            ->assertJson([
                'success' => false,
                'errors'  => [
                    'code'   => 'DUPLICATE_SMS',
                    'status' => 'duplicate',
                ],
            ]);
    }

    // =========================================================================
    // SECTION 8: PROVIDER ERROR & TIMEOUT RESILIENCE TESTS
    // =========================================================================

    public function test_sms_gateway_provider_error_is_handled_gracefully(): void
    {
        SmsService::fakeFailure('Invalid API Key / Insufficient Balance');

        $response = $this->postJson('/api/v1/sms/send', [
            'recipient' => '01844463954',
            'message'   => 'Test dispatch during provider failure',
        ], [
            'Authorization' => 'Bearer ' . $this->adminToken,
        ]);

        $response->assertStatus(502)
            ->assertJson([
                'success' => false,
                'errors'  => [
                    'status' => 'failed',
                ],
            ]);

        // Check that failure was recorded in sms_logs
        $log = SmsLog::where('recipient', '8801844463954')->latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals('failed', $log->status);
    }

    public function test_sms_gateway_timeout_is_handled_without_stack_trace_leak(): void
    {
        SmsService::fakeTimeout();

        $response = $this->postJson('/api/v1/sms/send', [
            'recipient' => '01844463954',
            'message'   => 'Test dispatch during timeout',
        ], [
            'Authorization' => 'Bearer ' . $this->adminToken,
        ]);

        $response->assertStatus(502)
            ->assertJson([
                'success' => false,
            ]);

        // Response must not contain PHP stack trace
        $this->assertStringNotContainsString('Stack trace:', $response->getContent());
    }

    public function test_sms_dispatch_when_gateway_is_disabled_returns_clean_error(): void
    {
        Setting::set('sms_enabled', '0');

        $response = $this->postJson('/api/v1/sms/send', [
            'recipient' => '01844463954',
            'message'   => 'Dispatch while disabled',
        ], [
            'Authorization' => 'Bearer ' . $this->adminToken,
        ]);

        $response->assertStatus(502)
            ->assertJson([
                'success' => false,
                'errors'  => [
                    'code' => 'GATEWAY_DISABLED',
                ],
            ]);
    }

    // =========================================================================
    // SECTION 9: TEMPLATE SMS SENDING TESTS
    // =========================================================================

    public function test_send_template_sms_for_student_interpolates_placeholders(): void
    {
        $response = $this->postJson('/api/v1/sms/send-template', [
            'template_key' => 'due_reminder',
            'student_id'   => $this->student1->id,
        ], [
            'Authorization' => 'Bearer ' . $this->adminToken,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'status'    => 'sent',
                    'recipient' => '8801844463954',
                ],
            ]);

        $log = SmsLog::where('student_id', $this->student1->id)->latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals('due_reminder', $log->template_key);
        // Student balance: 12000 - 5000 = 7000.00
        $this->assertStringContainsString('7,000.00', $log->message);
        $this->assertStringContainsString('Rahim Uddin', $log->message);
    }

    public function test_send_template_sms_for_asset_trainee(): void
    {
        $trainee = AssetRegistration::create([
            'full_name'           => 'Fatema Khatun',
            'mobile'              => '01755667788',
            'whatsapp'            => '01755667788',
            'course'              => 'Graphics Design',
            'registration_status' => 'Shortlisted',
        ]);

        $response = $this->postJson('/api/v1/sms/send-template', [
            'template_key' => 'trainee_qualified',
            'trainee_id'   => $trainee->id,
        ], [
            'Authorization' => 'Bearer ' . $this->adminToken,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'status'    => 'sent',
                    'recipient' => '8801755667788',
                ],
            ]);

        $log = SmsLog::where('recipient', '8801755667788')->latest()->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Fatema Khatun', $log->message);
        $this->assertStringContainsString('Graphics Design', $log->message);
    }

    // =========================================================================
    // SECTION 10: BATCH SMS SENDING TESTS
    // =========================================================================

    public function test_send_batch_sms_dispatches_notifications_to_all_active_students(): void
    {
        $response = $this->postJson('/api/v1/sms/send-batch', [
            'batch_id'     => $this->batch->id,
            'template_key' => 'today_postpone',
        ], [
            'Authorization' => 'Bearer ' . $this->adminToken,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'batch_id'       => $this->batch->id,
                    'total_students' => 2,
                    'sent_count'     => 2,
                    'failed_count'   => 0,
                ],
            ]);

        // Verify logs created for both students
        $logs = SmsLog::where('batch_id', $this->batch->id)->get();
        $this->assertCount(2, $logs);
    }

    public function test_send_batch_sms_to_empty_batch_returns_422(): void
    {
        $emptyBatch = Batchs::create([
            'batchnum'  => 'EMPTY-001',
            'course_id' => $this->course->id,
            'IsActive'  => 1,
        ]);

        $response = $this->postJson('/api/v1/sms/send-batch', [
            'batch_id'     => $emptyBatch->id,
            'template_key' => 'today_postpone',
        ], [
            'Authorization' => 'Bearer ' . $this->adminToken,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'No active students found in the specified batch.',
            ]);
    }

    // =========================================================================
    // SECTION 11: SMS DELIVERY LOGS TESTS
    // =========================================================================

    public function test_sms_delivery_logs_retrieval_and_filtering(): void
    {
        // Seed logs
        SmsLog::create([
            'recipient'    => '8801844463954',
            'message'      => 'Message 1',
            'template_key' => 'due_reminder',
            'status'       => 'sent',
            'sent_by'      => $this->admin->id,
        ]);

        SmsLog::create([
            'recipient'    => '8801711223344',
            'message'      => 'Message 2 failed',
            'template_key' => 'direct',
            'status'       => 'failed',
            'sent_by'      => $this->admin->id,
        ]);

        // Unfiltered list
        $all = $this->getJson('/api/v1/sms/logs', [
            'Authorization' => 'Bearer ' . $this->adminToken,
        ]);
        $all->assertStatus(200);
        $this->assertGreaterThanOrEqual(2, $all->json('data.total'));

        // Filter by status=failed
        $failed = $this->getJson('/api/v1/sms/logs?status=failed', [
            'Authorization' => 'Bearer ' . $this->adminToken,
        ]);
        $failed->assertStatus(200);
        foreach ($failed->json('data.data') as $item) {
            $this->assertEquals('failed', $item['status']);
        }

        // Filter by recipient
        $recipientFilter = $this->getJson('/api/v1/sms/logs?recipient=01844463954', [
            'Authorization' => 'Bearer ' . $this->adminToken,
        ]);
        $recipientFilter->assertStatus(200);
        foreach ($recipientFilter->json('data.data') as $item) {
            $this->assertStringContainsString('01844463954', $item['recipient']);
        }
    }

    public function test_show_single_sms_log_detail(): void
    {
        $log = SmsLog::create([
            'recipient'        => '8801844463954',
            'message'          => 'Detailed log test message',
            'template_key'     => 'direct',
            'status'           => 'sent',
            'response_payload' => 'SMS SUBMITTED: 998877',
            'sent_by'          => $this->admin->id,
            'student_id'       => $this->student1->id,
        ]);

        $response = $this->getJson("/api/v1/sms/logs/{$log->id}", [
            'Authorization' => 'Bearer ' . $this->adminToken,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'id'        => $log->id,
                    'recipient' => '8801844463954',
                    'status'    => 'sent',
                    'student'   => [
                        'sname' => 'Rahim Uddin',
                    ],
                ],
            ]);
    }

    // =========================================================================
    // SECTION 12: PROVIDER CONNECTIVITY TEST
    // =========================================================================

    public function test_sms_gateway_test_connection_verifies_provider(): void
    {
        $response = $this->postJson('/api/v1/sms/test-connection', [], [
            'Authorization' => 'Bearer ' . $this->adminToken,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'success' => true,
                ],
            ]);
    }

    public function test_sms_gateway_test_connection_fails_when_unconfigured(): void
    {
        SmsService::resetFake(); // Temporarily reset fake to test config validation
        Setting::set('sms_api_key', '');

        $response = $this->postJson('/api/v1/sms/test-connection', [], [
            'Authorization' => 'Bearer ' . $this->adminToken,
        ]);

        $response->assertStatus(502)
            ->assertJson([
                'success' => false,
            ]);

        SmsService::fake(); // Restore fake
    }

    // =========================================================================
    // SECTION 13: LEGACY REGRESSION TEST
    // =========================================================================

    public function test_legacy_sms_controller_send_sms_delegates_to_sms_service_and_logs(): void
    {
        $legacyController = new SMSController();
        $rawResult = $legacyController->send_sms('01844463954', 'Legacy controller regression test message');

        $this->assertNotFalse($rawResult);

        // Verify logged in sms_logs table
        $log = SmsLog::where('recipient', '8801844463954')
            ->where('message', 'Legacy controller regression test message')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('sent', $log->status);
    }
}
