<?php

use App\Enums\ContentStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\User;

beforeEach(function () {
    $this->editor = User::factory()->role(Role::ContentManager)->create();
});

test('staff without content permission cannot manage faqs', function () {
    $seo = User::factory()->role(Role::SeoSpecialist)->create();

    $this->actingAs($seo)->get(route('admin.faqs.index'))->assertForbidden();
    $this->actingAs($seo)->post(route('admin.faq-categories.store'), ['sort_order' => 0, 'translations' => ['en' => ['name' => 'Pricing']]])->assertForbidden();
});

test('an editor manages faq categories and questions', function () {
    $this->actingAs($this->editor)
        ->post(route('admin.faq-categories.store'), ['sort_order' => 0, 'translations' => ['en' => ['name' => 'Pricing'], 'ar' => ['name' => 'الأسعار']]])
        ->assertRedirect(route('admin.faqs.index'));
    $category = FaqCategory::query()->sole();

    $this->actingAs($this->editor)->post(route('admin.faqs.store'), [
        'faq_category_id' => $category->id,
        'is_featured' => '1',
        'sort_order' => 1,
        'translations' => [
            'en' => ['question' => 'How much does it cost?', 'answer' => 'It **depends** on scope.'],
            'ar' => ['question' => 'كم التكلفة؟', 'answer' => 'يعتمد على النطاق.'],
        ],
    ])->assertSessionHasNoErrors();

    $faq = Faq::query()->with('translations')->sole();
    expect($faq->status)->toBe(ContentStatus::Draft)
        ->and($faq->is_featured)->toBeTrue()
        ->and($faq->faq_category_id)->toBe($category->id);

    $this->actingAs($this->editor)->post(route('admin.faqs.publish', $faq))->assertSessionHasNoErrors();
    expect($faq->fresh()->status)->toBe(ContentStatus::Published);

    $this->actingAs($this->editor)->get(route('admin.faqs.index'))->assertSuccessful()->assertSee('How much does it cost?')->assertSee('Pricing');

    $this->actingAs($this->editor)->delete(route('admin.faq-categories.destroy', $category))->assertRedirect(route('admin.faqs.index'));
    expect($faq->fresh()->faq_category_id)->toBeNull();

    $this->actingAs($this->editor)->delete(route('admin.faqs.destroy', $faq));
    expect($faq->fresh()->trashed())->toBeTrue()
        ->and(AuditLog::query()->whereIn('operation', ['faq_category.created', 'faq.created', 'faq.published', 'faq_category.deleted', 'faq.deleted'])->count())->toBe(5);
});

test('a faq needs a default-language question', function () {
    $this->actingAs($this->editor)
        ->post(route('admin.faqs.store'), ['sort_order' => 0, 'faq_category_id' => 999, 'translations' => ['en' => ['question' => ''], 'ar' => ['question' => 'سؤال؟']]])
        ->assertSessionHasErrors(['translations.en.question', 'faq_category_id']);
});

test('every new content form renders for editors', function (string $route) {
    $this->actingAs($this->editor)->get(route($route, $route === 'admin.menus.items.create' ? ['menu' => 'header'] : []))->assertSuccessful();
})->with(['admin.faqs.create', 'admin.faq-categories.create', 'admin.testimonials.create', 'admin.statistics.create', 'admin.careers.create', 'admin.menus.items.create']);

test('a faq category form renders for editing', function () {
    $category = FaqCategory::factory()->withTranslation('en', ['name' => 'Pricing'])->create();

    $this->actingAs($this->editor)->get(route('admin.faq-categories.edit', $category))->assertSuccessful()->assertSee('Pricing');
});
