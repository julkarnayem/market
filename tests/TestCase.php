<?php
namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // app.blade.php calls @vite, which throws when public/build/manifest.json is
        // absent — and that directory is gitignored build output. Without this, a fresh
        // clone or a CI checkout fails 151 tests that have nothing to do with assets,
        // purely because nobody had run `npm run build` first. Nothing in the suite
        // asserts an asset URL; the real build is covered by the frontend CI job.
        $this->withoutVite();

        // Seed roles and permissions for every test
        $this->seed(\Database\Seeders\PermissionRoleSeeder::class);
    }
}
