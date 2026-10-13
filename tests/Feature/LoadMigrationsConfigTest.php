<?php

namespace Elite\TableViews\Tests\Feature;

use Elite\TableViews\Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;

class LoadMigrationsConfigTest extends TestCase
{
    protected function skipPackageMigrations($app): void
    {
        $app['config']->set('table-views.load_migrations', false);
    }

    #[Test]
    public function it_creates_the_table_by_default(): void
    {
        $this->assertTrue(Schema::hasTable('table_views'));
        $this->assertTrue(Schema::hasColumns('table_views', [
            'id', 'user_id', 'name', 'icon', 'color', 'resource', 'state', 'is_favorite', 'sort', 'tenant_type', 'tenant_id', 'created_at', 'updated_at',
        ]));
    }

    #[Test]
    #[DefineEnvironment('skipPackageMigrations')]
    public function it_leaves_the_migration_to_the_host_app_when_load_migrations_is_false(): void
    {
        $this->assertFalse(Schema::hasTable('table_views'));
    }

    #[Test]
    public function it_creates_the_preferences_table_by_default(): void
    {
        $this->assertTrue(Schema::hasTable('table_view_preferences'));
        $this->assertTrue(Schema::hasColumns('table_view_preferences', [
            'id', 'user_id', 'tenant_type', 'tenant_id', 'resource', 'view_type', 'view_key', 'is_favorite', 'is_hidden', 'sort', 'is_default', 'created_at', 'updated_at',
        ]));
    }

    #[Test]
    #[DefineEnvironment('skipPackageMigrations')]
    public function it_leaves_the_preferences_migration_to_the_host_app_too(): void
    {
        $this->assertFalse(Schema::hasTable('table_view_preferences'));
    }
}
