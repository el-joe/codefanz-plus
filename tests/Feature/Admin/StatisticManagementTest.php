<?php

use App\Enums\ContentStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Statistic;
use App\Models\User;

beforeEach(function () {
    $this->editor = User::factory()->role(Role::ContentManager)->create();
});

test('staff without content permission cannot manage statistics', function () {
    $seo = User::factory()->role(Role::SeoSpecialist)->create();

    $this->actingAs($seo)->get(route('admin.statistics.index'))->assertForbidden();
    $this->actingAs($seo)->post(route('admin.statistics.store'), ['value' => 1, 'sort_order' => 0, 'translations' => ['en' => ['label' => 'x']]])->assertForbidden();
});

test('an editor creates, publishes, edits and deletes a statistic', function () {
    $this->actingAs($this->editor)->post(route('admin.statistics.store'), [
        'value' => '120',
        'sort_order' => 0,
        'translations' => ['en' => ['label' => 'Projects delivered', 'suffix' => '+'], 'ar' => ['label' => 'مشروع منجز', 'suffix' => '+']],
    ])->assertSessionHasNoErrors();

    $statistic = Statistic::query()->sole();
    expect($statistic->status)->toBe(ContentStatus::Draft)->and($statistic->formattedValue())->toBe('120');

    $this->actingAs($this->editor)->post(route('admin.statistics.publish', $statistic))->assertSessionHasNoErrors();
    $this->actingAs($this->editor)->put(route('admin.statistics.update', $statistic), [
        'value' => '98.5',
        'sort_order' => 2,
        'translations' => ['en' => ['label' => 'Milestones on time', 'suffix' => '%']],
    ])->assertSessionHasNoErrors();

    expect($statistic->fresh()->formattedValue())->toBe('98.5');
    $this->actingAs($this->editor)->get(route('admin.statistics.index'))->assertSuccessful()->assertSee('98.5%');

    $this->actingAs($this->editor)->delete(route('admin.statistics.destroy', $statistic))->assertRedirect(route('admin.statistics.index'));
    expect(Statistic::query()->count())->toBe(0)
        ->and(AuditLog::query()->whereIn('operation', ['statistic.created', 'statistic.published', 'statistic.updated', 'statistic.deleted'])->count())->toBe(4);
});

test('statistic values must be non-negative numbers', function () {
    $this->actingAs($this->editor)
        ->post(route('admin.statistics.store'), ['value' => '24/7', 'sort_order' => 0, 'translations' => ['en' => ['label' => 'Monitoring']]])
        ->assertSessionHasErrors('value');
    $this->actingAs($this->editor)
        ->post(route('admin.statistics.store'), ['value' => '-3', 'sort_order' => 0, 'translations' => ['en' => ['label' => 'Monitoring', 'suffix' => str_repeat('x', 11)]]])
        ->assertSessionHasErrors(['value', 'translations.en.suffix']);
});
