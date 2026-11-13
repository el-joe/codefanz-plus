<?php

use App\Enums\KillSwitchKey;
use App\Enums\Role;
use App\Models\BrandProfile;
use App\Models\KillSwitch;
use App\Models\Language;
use App\Models\Menu;
use App\Models\PageSection;
use App\Models\PromptTemplate;
use App\Models\RadarPlatform;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * The deploy script re-runs DatabaseSeeder on every release. It must add what is missing and never undo what an
 * administrator changed in production.
 */
function systemRowCounts(): array
{
    return collect(['languages', 'roles', 'permissions', 'role_has_permissions', 'kill_switches', 'menus', 'page_sections', 'brand_profiles', 'prompt_templates', 'prompt_template_versions', 'radar_platforms'])
        ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])
        ->all();
}

test('re-running the production seeders creates no duplicates', function () {
    $this->seed(DatabaseSeeder::class);
    $first = systemRowCounts();

    $this->seed(DatabaseSeeder::class);

    expect(systemRowCounts())->toBe($first)
        ->and($first['languages'])->toBe(2)
        ->and($first['kill_switches'])->toBe(count(KillSwitchKey::cases()))
        ->and($first['roles'])->toBe(count(Role::cases()));
});

test('re-running the production seeders keeps administrator changes', function () {
    $this->seed(DatabaseSeeder::class);

    $switch = KillSwitch::query()->firstOrFail();
    $switch->update(['is_active' => true]);
    Language::query()->where('code', 'ar')->update(['is_active' => false, 'sort_order' => 9]);
    $section = PageSection::query()->firstOrFail();
    $section->update(['is_visible' => ! $section->is_visible, 'sort_order' => 42]);
    BrandProfile::query()->where('is_default', true)->update(['name' => 'Owner edited']);
    $template = PromptTemplate::query()->firstOrFail();
    $template->update(['is_active' => false]);
    $platform = RadarPlatform::query()->firstOrFail();
    $platform->update(['fetch_interval_minutes' => 120]);
    $menuIds = Menu::query()->pluck('id')->all();

    $this->seed(DatabaseSeeder::class);

    expect($switch->fresh()->is_active)->toBeTrue()
        ->and(Language::query()->where('code', 'ar')->first()->only(['is_active', 'sort_order']))->toBe(['is_active' => false, 'sort_order' => 9])
        ->and($section->fresh()->only(['is_visible', 'sort_order']))->toBe(['is_visible' => $section->is_visible, 'sort_order' => 42])
        ->and(BrandProfile::query()->where('is_default', true)->value('name'))->toBe('Owner edited')
        ->and($template->fresh()->is_active)->toBeFalse()
        ->and($platform->fresh()->fetch_interval_minutes)->toBe(120)
        ->and(Menu::query()->pluck('id')->all())->toBe($menuIds);
});

test('role permissions follow the code-defined matrix on every run', function () {
    $this->seed(DatabaseSeeder::class);
    $role = RoleModel::findByName(Role::ContentManager->value, 'web');
    $expected = $role->permissions()->pluck('name')->sort()->values()->all();
    $role->syncPermissions([]);

    $this->seed(DatabaseSeeder::class);

    expect($role->fresh()->permissions()->pluck('name')->sort()->values()->all())->toBe($expected);
});
