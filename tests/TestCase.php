<?php

namespace TableKit\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;
use TableKit\TableKitServiceProvider;
use TableKit\Tests\Fixtures\AdminPanelProvider;
use TableKit\Tests\Fixtures\Order;
use TableKit\Tests\Fixtures\Pages\ListOrdersWithPresets;
use TableKit\Tests\Fixtures\TeamPanelProvider;
use TableKit\Tests\Fixtures\User;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        ListOrdersWithPresets::$presets = null;

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
    }

    protected function getPackageProviders($app): array
    {
        return [
            BladeCaptureDirectiveServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            SupportServiceProvider::class,
            LivewireServiceProvider::class,
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentServiceProvider::class,
            TableKitServiceProvider::class,
            AdminPanelProvider::class,
            TeamPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('session.driver', 'array');
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('app.locale', 'en');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    protected function signIn(?User $user = null): User
    {
        $user ??= User::create(['name' => 'Tester', 'email' => 'tester'.uniqid().'@example.test', 'password' => 'secret']);

        $this->actingAs($user);

        return $user;
    }

    protected function seedOrders(int $count = 50): void
    {
        $customers = ['Alice Alpha', 'Bob Beta', 'Carol Gamma', 'Dan Delta', 'Eve Epsilon'];
        $statuses = ['pending', 'paid', 'shipped', 'cancelled'];

        for ($i = 1; $i <= $count; $i++) {
            Order::create([
                'reference' => sprintf('ORD-%03d', $i),
                'customer_name' => $customers[$i % 5],
                'status' => $statuses[$i % 4],
                'amount' => ($i * 37) % 1000 + 10,
                'notes' => "note {$i}",
                'ordered_at' => now()->startOfYear()->addDays($i)->toDateString(),
                'customer_id' => null,
            ]);
        }
    }
}
