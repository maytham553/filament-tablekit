<?php

use Filament\Facades\Filament;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use TableKit\Models\TablePreference;
use TableKit\Presets\PresetTableView;
use TableKit\TableKitPlugin;
use TableKit\Tests\Fixtures\Pages\ListOrdersWithPresets;
use TableKit\Tests\Fixtures\Team;
use TableKit\Tests\Fixtures\UlidUser;
use TableKit\Tests\Fixtures\User;

beforeEach(function () {
    $this->user = $this->signIn();
    $this->other = User::create(['name' => 'Other', 'email' => 'other@example.test', 'password' => 'x']);
});

function preference(array $attributes = []): array
{
    return [
        'user_id' => test()->user->id,
        'resource' => 'res',
        'view_type' => 'preset',
        'view_key' => 'a',
        ...$attributes,
    ];
}

it('stores the user id and keys in string columns', function () {
    expect(Schema::getColumnType('tablekit_preferences', 'user_id'))->toBe('varchar')
        ->and(Schema::getColumnType('tablekit_preferences', 'view_key'))->toBe('varchar');
});

it('defaults a new row to no favorite choice, not hidden, no sort, not default', function () {
    $row = TablePreference::create(preference())->fresh();

    expect($row->is_favorite)->toBeNull()
        ->and($row->is_hidden)->toBeFalse()
        ->and($row->sort)->toBeNull()
        ->and($row->is_default)->toBeFalse();
});

it('scopes every read to the user, tenant and table', function () {
    $x = Team::create(['name' => 'X']);

    Filament::setCurrentPanel(Filament::getPanel('team'));
    Filament::bootCurrentPanel();
    Filament::setTenant($x);

    TablePreference::create(preference(['tenant_type' => $x->getMorphClass(), 'tenant_id' => $x->id, 'view_key' => 'mine']));
    TablePreference::create(preference(['tenant_type' => $x->getMorphClass(), 'tenant_id' => $x->id, 'resource' => 'other-table', 'view_key' => 'elsewhere']));
    TablePreference::create(preference(['tenant_type' => $x->getMorphClass(), 'tenant_id' => $x->id, 'user_id' => $this->other->id, 'view_key' => 'theirs']));
    TablePreference::create(preference(['view_key' => 'no-tenant']));
    TablePreference::create(preference(['tenant_type' => $x->getMorphClass(), 'tenant_id' => 'another', 'view_key' => 'other-tenant']));

    expect(TablePreference::forCurrentContext('res')->pluck('view_key')->all())->toBe(['mine']);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant(null);

    expect(TablePreference::forCurrentContext('res')->pluck('view_key')->all())->toBe(['no-tenant']);
});

it('returns nothing without a signed in user', function () {
    TablePreference::create(preference());

    auth()->logout();

    expect(TablePreference::forCurrentContext('res')->count())->toBe(0)
        ->and(TablePreference::upsertFor('res', 'preset', 'a', ['sort' => 1]))->toBeNull();
});

describe('upsert', function () {
    it('creates once and updates after', function () {
        TablePreference::upsertFor('res', 'preset', 'a', ['sort' => 1]);
        TablePreference::upsertFor('res', 'preset', 'a', ['sort' => 5, 'is_favorite' => true]);

        $rows = TablePreference::all();

        expect($rows)->toHaveCount(1)
            ->and($rows[0]->sort)->toBe(5)
            ->and($rows[0]->is_favorite)->toBeTrue()
            ->and($rows[0]->user_id)->toBe((string) $this->user->id)
            ->and($rows[0]->tenant_type)->toBeNull();
    });

    it('keeps rows of the same key apart per type, user and table', function () {
        TablePreference::upsertFor('res', 'preset', 'a', ['sort' => 1]);
        TablePreference::upsertFor('res', 'user', 'a', ['sort' => 2]);
        TablePreference::upsertFor('other', 'preset', 'a', ['sort' => 3]);

        expect(TablePreference::count())->toBe(3);
    });

    it('retries against the winner when a concurrent request created the row first', function () {
        $raced = false;

        TablePreference::creating(function () use (&$raced) {
            if (! $raced) {
                $raced = true;

                DB::table('tablekit_preferences')->insert([
                    'user_id' => (string) $this->user->id, 'resource' => 'res', 'view_type' => 'preset', 'view_key' => 'a',
                    'is_hidden' => false, 'is_default' => false, 'sort' => 9,
                ]);
            }
        });

        $row = TablePreference::upsertFor('res', 'preset', 'a', ['sort' => 1]);

        expect(TablePreference::count())->toBe(1)
            ->and($row->fresh()->sort)->toBe(1);
    });
});

describe('unique index', function () {
    it('rejects a duplicate row, also when there is no tenant', function () {
        TablePreference::create(preference());

        expect(fn () => TablePreference::create(preference()))->toThrow(UniqueConstraintViolationException::class);
    });

    it('rejects a duplicate row inside a tenant', function () {
        TablePreference::create(preference(['tenant_type' => 'team', 'tenant_id' => '1']));

        expect(fn () => TablePreference::create(preference(['tenant_type' => 'team', 'tenant_id' => '1'])))
            ->toThrow(UniqueConstraintViolationException::class);
    });

    it('allows the same key for another user, tenant, table or type', function () {
        TablePreference::create(preference());
        TablePreference::create(preference(['user_id' => $this->other->id]));
        TablePreference::create(preference(['tenant_type' => 'team', 'tenant_id' => '1']));
        TablePreference::create(preference(['tenant_type' => 'team', 'tenant_id' => '2']));
        TablePreference::create(preference(['resource' => 'other']));
        TablePreference::create(preference(['view_type' => 'user']));

        expect(TablePreference::count())->toBe(6);
    });
});

describe('ulid users', function () {
    it('stores string user ids and keeps two ulid users apart', function () {
        presets(fn () => ['a' => PresetTableView::make()->label('Alpha')->favorite(), 'b' => PresetTableView::make()->label('Beta')->favorite()]);

        $first = UlidUser::create(['name' => 'One', 'email' => 'one@example.test', 'password' => 'x']);
        $second = UlidUser::create(['name' => 'Two', 'email' => 'two@example.test', 'password' => 'x']);

        $this->actingAs($first);
        Livewire::test(ListOrdersWithPresets::class)->call('openTableViewsManager')->call('moveTableView', 'preset', 'b', 'up');

        $this->actingAs($second);
        $catalog = callProtected(Livewire::test(ListOrdersWithPresets::class)->call('openTableViewsManager'), 'getTableViewsCatalog');

        expect($catalog->presets->pluck('key')->all())->toBe(['a', 'b'])
            ->and(TablePreference::pluck('user_id')->unique()->all())->toBe([$first->getKey()]);

        $this->actingAs($first);
        $catalog = callProtected(Livewire::test(ListOrdersWithPresets::class)->call('openTableViewsManager'), 'getTableViewsCatalog');

        expect($catalog->presets->pluck('key')->all())->toBe(['b', 'a']);
    });
});

describe('connection', function () {
    function preferencesOn(string ...$names): void
    {
        foreach ($names as $name) {
            config(["database.connections.{$name}" => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        }
    }

    it('uses the configured connection while the default is another one', function () {
        preferencesOn('views', 'elsewhere');
        config(['tablekit.connection' => 'views']);
        (require __DIR__.'/../../database/migrations/2026_01_01_000001_create_tablekit_preferences_table.php')->up();

        DB::setDefaultConnection('elsewhere');

        $row = TablePreference::create(preference());

        expect($row->getConnectionName())->toBe('views')
            ->and(DB::connection('views')->table('tablekit_preferences')->count())->toBe(1)
            ->and(DB::connection('testing')->table('tablekit_preferences')->count())->toBe(0);
    });

    it('lets the plugin connection win', function () {
        preferencesOn('plugin');
        config(['tablekit.connection' => 'plugin']);
        (require __DIR__.'/../../database/migrations/2026_01_01_000001_create_tablekit_preferences_table.php')->up();
        config(['tablekit.connection' => null]);

        TableKitPlugin::get()->connection('plugin');

        expect(TablePreference::create(preference())->getConnectionName())->toBe('plugin');
    });

    it('reads the table name from config only', function () {
        config(['tablekit.preferences_table' => 'custom_preferences']);

        expect((new TablePreference)->getTable())->toBe('custom_preferences');
    });
});
