<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Class ReactFoundationTest
 *
 * Verifies Phase 3.0 React Frontend Foundation setup:
 * - Isolated non-production health mount view (/react-health)
 * - Compiled React 18 / Vite bundle availability in public/react/
 * - Seamless integration with /api/v1/ping
 * - Non-regression of existing legacy Blade views and Laravel Mix assets
 *
 * @package Tests\Feature
 */
class ReactFoundationTest extends TestCase
{
    /**
     * Test that GET /react-health serves the isolated React mount view.
     *
     * @return void
     */
    public function test_react_health_route_serves_isolated_mount_view()
    {
        $response = $this->get('/react-health');

        $response->assertStatus(200);
        $response->assertSee('id="react-root"', false);
        $response->assertSee('data-api-base-url=', false);
        $response->assertSee('name="csrf-token"', false);
        $response->assertSee('react/main.js', false);
    }

    /**
     * Test that compiled React and Vite bundle assets exist in the public directory.
     *
     * @return void
     */
    public function test_react_compiled_assets_exist_in_public_directory()
    {
        $mainJsPath = public_path('react/main.js');
        $mainCssPath = public_path('react/main.css');
        $manifestPath = public_path('react/.vite/manifest.json');

        $this->assertFileExists($mainJsPath, 'Compiled React bundle public/react/main.js must exist.');
        $this->assertFileExists($mainCssPath, 'Compiled React styles public/react/main.css must exist.');
        $this->assertFileExists($manifestPath, 'Vite manifest public/react/.vite/manifest.json must exist.');

        $this->assertGreaterThan(1000, filesize($mainJsPath), 'public/react/main.js should contain compiled bundle code.');
        $this->assertGreaterThan(100, filesize($mainCssPath), 'public/react/main.css should contain compiled styles.');
    }

    /**
     * Test that the React foundation API client target (/api/v1/ping) responds successfully.
     *
     * @return void
     */
    public function test_react_api_target_endpoint_is_operational()
    {
        $response = $this->getJson('/api/v1/ping');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'API v1 foundation operational',
            ]);
    }

    /**
     * Test that legacy Laravel Mix compiled assets and Blade pages remain intact.
     *
     * @return void
     */
    public function test_legacy_mix_assets_and_blade_views_remain_intact()
    {
        $legacyJs = public_path('js/app.js');
        $legacyCss = public_path('css/app.css');

        $this->assertFileExists($legacyJs, 'Legacy Mix bundle public/js/app.js must remain intact.');
        $this->assertFileExists($legacyCss, 'Legacy Mix styles public/css/app.css must remain intact.');

        // Verify public Blade route renders without regression
        $response = $this->get('/about');
        $response->assertStatus(200);
    }
}
