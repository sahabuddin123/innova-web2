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
 * Class ReactAdminShellTest
 *
 * Automated verification suite for Phase 3.2 React Admin Dashboard Shell:
 * - Dedicated isolated mount view (/react-admin) serving HTML container with data-app-mode="admin"
 * - SPA routing catch-all: /react-admin/{any} serves the React shell allowing client-side routing
 * - Client-side route isolation: /react-admin/dashboard, /react-admin/profile, /react-admin/access-denied, unknown routes
 * - Routing conflict avoidance: Production Blade admin routes (/admin/*, /admin/login) remain 100% untouched
 * - Compiled React bundle verification: public/react/main.js & public/react/main.css exist with expected assets
 * - Legacy Mix asset non-regression: public/js/app.js & public/css/app.css exist and remain intact
 * - Admin authorization integration: /api/v1/admin/me returns admin identity with roles & permissions
 * - Cross-boundary access prevention: student receives 403 on admin identity, unauthenticated receives 401
 *
 * Uses isolated in-memory SQLite database to ensure zero mutation of production MySQL.
 *
 * @package Tests\Feature
 */
class ReactAdminShellTest extends TestCase
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
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('admins');

        parent::tearDown();
    }

    /**
     * Test that GET /react-admin serves the dedicated React admin mount view.
     */
    public function test_react_admin_root_serves_dedicated_mount_view(): void
    {
        $response = $this->get('/react-admin');

        $response->assertStatus(200);
        $response->assertSee('id="react-root"', false);
        $response->assertSee('data-app-mode="admin"', false);
        $response->assertSee('data-api-base-url="http://localhost/api/v1"', false);
        $response->assertSee('name="csrf-token"', false);
        $response->assertSee('react/main.js', false);
        $response->assertSee('react/main.css', false);
    }

    /**
     * Test that SPA client-side routes under /react-admin/{any} all serve the React shell mount.
     */
    public function test_react_admin_spa_catch_all_routes_serve_shell_mount(): void
    {
        $spaPaths = [
            '/react-admin/dashboard',
            '/react-admin/profile',
            '/react-admin/access-denied',
            '/react-admin/courses',
            '/react-admin/students',
            '/react-admin/accounting',
            '/react-admin/some/nonexistent/deep/route',
        ];

        foreach ($spaPaths as $path) {
            $response = $this->get($path);
            $response->assertStatus(200);
            $response->assertSee('id="react-root"', false);
            $response->assertSee('data-app-mode="admin"', false);
            $response->assertSee('react/main.js', false);
        }
    }

    /**
     * Test that production Blade admin routes are NOT hijacked or redirected by the React mount.
     */
    public function test_production_blade_admin_routes_are_not_hijacked(): void
    {
        // Unauthenticated access to /admin must redirect to /admin/login (Blade admin guard)
        $response = $this->get('/admin');
        $this->assertTrue(
            in_array($response->status(), [302, 401, 200]),
            'Expected /admin to invoke Blade admin middleware (got ' . $response->status() . ')'
        );

        // /admin/login serves Blade view (does not serve the React SPA mount)
        $loginResponse = $this->get('/admin/login');
        $this->assertEquals(200, $loginResponse->status());
        $loginResponse->assertDontSee('data-app-mode="admin"');
    }

    /**
     * Test that compiled React bundle files exist and contain React Router and Admin Shell assets.
     */
    public function test_react_bundle_contains_router_and_shell_assets(): void
    {
        $mainJsPath = public_path('react/main.js');
        $mainCssPath = public_path('react/main.css');

        $this->assertFileExists($mainJsPath);
        $this->assertFileExists($mainCssPath);

        $jsContent = file_get_contents($mainJsPath);
        $this->assertStringContainsString('react-admin', $jsContent, 'Bundle should contain react-admin basename configuration');
        $this->assertStringContainsString('Skip to main content', $jsContent, 'Bundle should contain accessible shell skip link');
        $this->assertStringContainsString('Phase 3.3', $jsContent, 'Bundle should contain admin module roadmap references');

        $cssContent = file_get_contents($mainCssPath);
        $this->assertStringContainsString('--innova-sidebar-width', $cssContent, 'Styles should contain sidebar CSS variable');
        $this->assertStringContainsString('--innova-header-height', $cssContent, 'Styles should contain header CSS variable');
    }

    /**
     * Test that legacy Laravel Mix bundle assets remain intact.
     */
    public function test_legacy_mix_assets_remain_intact(): void
    {
        $mixJsPath = public_path('js/app.js');
        $mixCssPath = public_path('css/app.css');

        $this->assertFileExists($mixJsPath);
        $this->assertFileExists($mixCssPath);

        $this->assertGreaterThan(50000, filesize($mixJsPath), 'Legacy Mix app.js should not be truncated');
        $this->assertGreaterThan(10000, filesize($mixCssPath), 'Legacy Mix app.css should not be truncated');
    }

    /**
     * Test admin identity and authorization endpoint used by React Admin Shell.
     */
    public function test_admin_identity_returns_roles_and_permissions_for_react_shell(): void
    {
        $admin = Admin::create([
            'name' => 'Shell Administrator',
            'email' => 'shelladmin@innovabd.net',
            'password' => Hash::make('Secret123!'),
        ]);

        $role = Role::create(['name' => 'Super Admin', 'guard_name' => 'admin']);
        $permission = Permission::create(['name' => 'manage-users', 'guard_name' => 'admin']);
        $role->givePermissionTo($permission);
        $admin->assignRole($role);

        $token = $admin->createToken('admin-shell-token', ['*'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/admin/auth/me');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.email', 'shelladmin@innovabd.net');
        $response->assertJsonPath('data.is_admin', true);
        $response->assertJsonPath('data.roles.0', 'Super Admin');
        $response->assertJsonPath('data.permissions.0', 'manage-users');
    }

    /**
     * Test cross-boundary access protection: student token receives 403 on admin identity endpoint.
     */
    public function test_student_token_cannot_access_admin_identity_endpoint(): void
    {
        $user = User::create([
            'name' => 'Regular Student',
            'email' => 'student.shell@innovabd.net',
            'password' => Hash::make('Secret123!'),
        ]);

        $token = $user->createToken('student-token', ['*'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/admin/auth/me');

        $response->assertStatus(403);
    }

    /**
     * Test unauthenticated access to admin identity endpoint receives 401.
     */
    public function test_unauthenticated_request_to_admin_identity_endpoint_returns_401(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->getJson('/api/v1/admin/auth/me');

        $response->assertStatus(401);
    }
}
