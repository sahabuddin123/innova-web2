<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Class ReactAuthFoundationTest
 *
 * Automated verification suite for Phase 3.1 React Authentication Foundation:
 * - Isolated non-production test mount view (/react-health)
 * - Multi-guard Sanctum API authentication integration (Admin & Student)
 * - Session profile hydration (/me) and token revocation (/logout)
 * - Cross-boundary identity verification (Admin vs Student 403 enforcement)
 * - Unauthenticated request handling (401 enforcement)
 * - Legacy Blade authentication views non-regression (/admin/login, /login)
 *
 * Uses isolated in-memory SQLite database to ensure zero mutation of MySQL.
 *
 * @package Tests\Feature
 */
class ReactAuthFoundationTest extends TestCase
{
    /**
     * Set up test environment with normalized URL and isolated SQLite in-memory database.
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
            $this->fail('CRITICAL SAFETY CHECK FAILED: Test is not running on an isolated in-memory SQLite database!');
        }

        $this->createTestTables();
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

        Schema::create('sessions', function ($table) {
            $table->id();
            $table->string('session_name')->nullable();
            $table->boolean('IsActive')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('role_has_permissions', function ($table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
        });
    }

    /**
     * Tear down test schema in SQLite in-memory database.
     */
    protected function tearDown(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            Schema::dropIfExists('sessions');
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

    /**
     * 1. Test isolated React health check view mounts and loads compiled bundles.
     */
    public function test_react_health_route_serves_auth_mount_container()
    {
        $response = $this->get('/react-health');

        $response->assertStatus(200);
        $response->assertSee('id="react-root"', false);
        $response->assertSee('data-api-base-url=', false);
        $response->assertSee('name="csrf-token"', false);
        $response->assertSee('react/main.js', false);
    }

    /**
     * 2. Test compiled React bundle exists and incorporates Phase 3.1 auth code.
     */
    public function test_react_compiled_assets_contain_phase_3_1_auth_bundle()
    {
        $mainJsPath = public_path('react/main.js');
        $mainCssPath = public_path('react/main.css');

        $this->assertFileExists($mainJsPath);
        $this->assertFileExists($mainCssPath);

        // Phase 3.1 bundle incorporates auth context & state management (> 160 kB)
        $this->assertGreaterThan(160000, filesize($mainJsPath));
    }

    /**
     * 3. Test Admin authentication flow: Login -> Profile (/me) -> Logout.
     */
    public function test_admin_authentication_api_lifecycle()
    {
        $admin = Admin::create([
            'name'     => 'System Admin',
            'email'    => 'admin@innovacomputersbd.com',
            'password' => Hash::make('securePassword123'),
        ]);

        $role = Role::create(['name' => 'super-admin', 'guard_name' => 'admin']);
        $permission = Permission::create(['name' => 'manage-courses', 'guard_name' => 'admin']);
        $admin->assignRole($role);
        $admin->givePermissionTo($permission);

        // Step 3a: Authenticate admin via POST /api/v1/admin/auth/login
        $loginResponse = $this->postJson('/api/v1/admin/auth/login', [
            'email'    => 'admin@innovacomputersbd.com',
            'password' => 'securePassword123',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'token_type' => 'Bearer',
                    'user'       => [
                        'id'          => $admin->id,
                        'name'        => 'System Admin',
                        'email'       => 'admin@innovacomputersbd.com',
                        'is_admin'    => true,
                        'roles'       => ['super-admin'],
                        'permissions' => ['manage-courses'],
                    ],
                ],
            ]);

        $token = $loginResponse->json('data.access_token');
        $this->assertNotEmpty($token);

        // Step 3b: Retrieve authenticated profile via GET /api/v1/admin/auth/me
        $meResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/admin/auth/me');

        $meResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'id'          => $admin->id,
                    'name'        => 'System Admin',
                    'email'       => 'admin@innovacomputersbd.com',
                    'is_admin'    => true,
                    'roles'       => ['super-admin'],
                    'permissions' => ['manage-courses'],
                ],
            ]);

        // Step 3c: Revoke token via POST /api/v1/admin/auth/logout
        $logoutResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/admin/auth/logout');

        $logoutResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Successfully logged out',
            ]);

        // Step 3d: Confirm token is revoked; subsequent request returns 401
        app('auth')->forgetGuards();
        $revokedResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/admin/auth/me');

        $revokedResponse->assertStatus(401);
    }

    /**
     * 4. Test Student authentication flow: Login -> Profile (/me) -> Logout.
     */
    public function test_student_authentication_api_lifecycle()
    {
        $user = User::create([
            'name'     => 'Student Learner',
            'email'    => 'student@innovacomputersbd.com',
            'password' => Hash::make('studentPass123'),
        ]);

        // Step 4a: Authenticate user via POST /api/v1/auth/login
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email'    => 'student@innovacomputersbd.com',
            'password' => 'studentPass123',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'token_type' => 'Bearer',
                    'user'       => [
                        'id'       => $user->id,
                        'name'     => 'Student Learner',
                        'email'    => 'student@innovacomputersbd.com',
                        'is_admin' => false,
                    ],
                ],
            ]);

        $token = $loginResponse->json('data.access_token');
        $this->assertNotEmpty($token);

        // Step 4b: Retrieve authenticated student profile via GET /api/v1/auth/me
        $meResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/me');

        $meResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'id'       => $user->id,
                    'name'     => 'Student Learner',
                    'email'    => 'student@innovacomputersbd.com',
                    'is_admin' => false,
                ],
            ]);

        // Step 4c: Revoke token via POST /api/v1/auth/logout
        $logoutResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/logout');

        $logoutResponse->assertStatus(200);

        // Step 4d: Confirm token is revoked
        app('auth')->forgetGuards();
        $revokedResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/me');

        $revokedResponse->assertStatus(401);
    }

    /**
     * 5. Test cross-boundary access protection:
     * - Student token rejected on Admin endpoints (403 Forbidden)
     * - Admin token rejected on Student endpoints (403 Forbidden)
     */
    public function test_cross_boundary_identity_protection()
    {
        $admin = Admin::create([
            'name'     => 'Boundary Admin',
            'email'    => 'boundary.admin@test.com',
            'password' => Hash::make('password'),
        ]);

        $user = User::create([
            'name'     => 'Boundary User',
            'email'    => 'boundary.user@test.com',
            'password' => Hash::make('password'),
        ]);

        $adminToken = $admin->createToken('admin-tok')->plainTextToken;
        $userToken = $user->createToken('user-tok')->plainTextToken;

        // Student token accessing admin endpoint
        $studentOnAdmin = $this->withHeader('Authorization', 'Bearer ' . $userToken)
            ->getJson('/api/v1/admin/auth/me');

        $studentOnAdmin->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Forbidden: Admin access required',
            ]);

        // Reset auth guards between simulated HTTP requests
        app('auth')->forgetGuards();

        // Admin token accessing student endpoint
        $adminOnStudent = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->getJson('/api/v1/auth/me');

        $adminOnStudent->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Forbidden: User access required',
            ]);
    }

    /**
     * 6. Test that unauthenticated requests to protected endpoints return HTTP 401.
     */
    public function test_unauthenticated_requests_return_401()
    {
        $adminMe = $this->getJson('/api/v1/admin/auth/me');
        $adminMe->assertStatus(401);

        $userMe = $this->getJson('/api/v1/auth/me');
        $userMe->assertStatus(401);
    }

    /**
     * 7. Test that legacy Blade authentication pages and routes remain intact.
     */
    public function test_legacy_blade_authentication_routes_remain_intact()
    {
        // Admin Blade login page
        $adminLoginView = $this->get('/admin/login');
        $adminLoginView->assertStatus(200);

        // Student Blade login page
        $userLoginView = $this->get('/login');
        $userLoginView->assertStatus(200);
    }
}
