<?php

use App\Models\Industry;
use App\Models\Service;
use App\Models\ServiceFaq;
use App\Models\ServiceFeature;
use App\Models\Technology;
use App\Models\TechnologyCategory;

test('the services index groups published core and specialist services', function () {
    Service::factory()->withTranslation('en', ['title' => 'Web Development', 'slug' => 'web-development'])->create();
    Service::factory()->specialist()->withTranslation('en', ['title' => 'API Development', 'slug' => 'api-development'])->create();
    Service::factory()->draft()->withTranslation('en', ['title' => 'Secret Draft'])->create();
    $parent = Service::factory()->withTranslation('en')->create();
    Service::factory()->withTranslation('en', ['title' => 'Nested Child'])->create(['parent_id' => $parent->id]);

    $this->get('/en/services')
        ->assertSuccessful()
        ->assertSee('<html lang="en" dir="ltr"', false)
        ->assertSeeInOrder(['What we build', 'Web Development', 'Specialist services', 'API Development'])
        ->assertSee(route('services.show', ['locale' => 'en', 'slug' => 'web-development']), false)
        ->assertSee('hreflang="ar"', false)
        ->assertDontSee('Secret Draft')
        ->assertDontSee('Nested Child');
});

test('the services index renders right to left in arabic and lists only arabic translations', function () {
    Service::factory()->withTranslation('ar', ['title' => 'تطوير المواقع', 'slug' => 'تطوير-المواقع'])->create();
    Service::factory()->withTranslation('en', ['title' => 'English Only'])->create();

    $this->get('/ar/services')
        ->assertSuccessful()
        ->assertSee('dir="rtl"', false)
        ->assertSee('تطوير المواقع')
        ->assertDontSee('English Only');
});

test('a service detail page shows deliverables, stack, faq, quote card and translated alternates', function () {
    $service = Service::factory()
        ->withTranslation('en', ['title' => 'Web Development', 'slug' => 'web-development', 'body' => "## Overview\n\nWe ship."])
        ->withTranslation('ar', ['title' => 'تطوير المواقع', 'slug' => 'تطوير-المواقع'])
        ->create();
    ServiceFeature::factory()->for($service)->withTranslation('en', ['title' => 'Discovery workshop'])->create();
    ServiceFaq::factory()->for($service)->withTranslation('en', ['question' => 'How long does it take?'])->create();
    $hidden = Technology::factory()->withTranslation('en', ['name' => 'HiddenTech'])->create(['is_visible' => false]);
    $laravel = Technology::factory()->expertise()->withTranslation('en', ['name' => 'Laravel'])->create();
    $service->technologies()->attach([$laravel->id, $hidden->id]);
    Service::factory()->withTranslation('en', ['title' => 'Laravel APIs'])->create(['parent_id' => $service->id]);

    $this->get('/en/services/web-development')
        ->assertSuccessful()
        ->assertSee('<h2>Overview</h2>', false)
        ->assertSee('Discovery workshop')
        ->assertSee('<details', false)
        ->assertSee('How long does it take?')
        ->assertSee('Laravel')
        ->assertSee('data-level="expertise"', false)
        ->assertDontSee('HiddenTech')
        ->assertSee('Laravel APIs')
        ->assertSee('Request a quote')
        ->assertSee('hreflang="ar"', false)
        ->assertSee(route('services.show', ['locale' => 'ar', 'slug' => 'تطوير-المواقع']), false);

    $this->get('/ar/services/'.rawurlencode('تطوير-المواقع'))
        ->assertSuccessful()
        ->assertSee('dir="rtl"', false)
        ->assertSee('تطوير المواقع')
        ->assertDontSee('Discovery workshop');
});

test('draft, trashed and other-language services are not found', function () {
    Service::factory()->draft()->withTranslation('en', ['slug' => 'draft-service'])->create();
    Service::factory()->withTranslation('en', ['slug' => 'gone'])->create()->delete();
    Service::factory()->withTranslation('en', ['slug' => 'english-only'])->create();

    $this->get('/en/services/draft-service')->assertNotFound();
    $this->get('/en/services/gone')->assertNotFound();
    $this->get('/ar/services/english-only')->assertNotFound();
});

test('raw html in service content is stripped', function () {
    Service::factory()->withTranslation('en', [
        'slug' => 'unsafe',
        'body' => "<script>alert('x')</script>\n\n[click](javascript:alert(1))",
    ])->create();

    $this->get('/en/services/unsafe')
        ->assertSuccessful()
        ->assertDontSee("<script>alert('x')</script>", false)
        ->assertDontSee('javascript:alert', false);
});

test('the technologies page shows the three-level legend and grouped visible chips', function () {
    $category = TechnologyCategory::factory()
        ->withTranslation('en', ['name' => 'Frameworks'])
        ->withTranslation('ar', ['name' => 'أطر العمل'])
        ->create();
    Technology::factory()->expertise()->withTranslation('en', ['name' => 'Laravel'])->create(['technology_category_id' => $category->id]);
    Technology::factory()->withTranslation('en', ['name' => 'Symfony'])->create(['technology_category_id' => $category->id]);
    Technology::factory()->withTranslation('en', ['name' => 'Invisible'])->create(['technology_category_id' => $category->id, 'is_visible' => false]);

    $this->get('/en/technologies')
        ->assertSuccessful()
        ->assertSee(['Verified in projects', 'Expertise', 'Supported'])
        ->assertSee('Frameworks')
        ->assertSee('data-level="expertise"', false)
        ->assertSee('data-level="supported"', false)
        ->assertDontSee('data-level="verified_in_projects"', false)
        ->assertDontSee('Invisible')
        ->assertSee('hreflang="ar"', false);

    $this->get('/ar/technologies')
        ->assertSuccessful()
        ->assertSee('dir="rtl"', false)
        ->assertSee('أطر العمل')
        ->assertSee('Laravel');
});

test('the industries page lists published industries with typical solutions', function () {
    Industry::factory()->withTranslation('en', ['name' => 'Healthcare', 'typical_solutions' => ['Clinic management']])->withTranslation('ar', ['name' => 'الرعاية الصحية', 'slug' => 'الرعاية-الصحية'])->create();
    Industry::factory()->draft()->withTranslation('en', ['name' => 'Draft Industry'])->create();

    $this->get('/en/industries')
        ->assertSuccessful()
        ->assertSee('Healthcare')
        ->assertSee('Clinic management')
        ->assertDontSee('Draft Industry')
        ->assertSee('hreflang="ar"', false);

    $this->get('/ar/industries')
        ->assertSuccessful()
        ->assertSee('dir="rtl"', false)
        ->assertSee('الرعاية الصحية')
        ->assertDontSee('Healthcare');
});

test('catalog routes win over the cms page catch-all', function () {
    $this->get('/en/services')->assertSuccessful();
    $this->get('/en/technologies')->assertSuccessful();
    $this->get('/en/industries')->assertSuccessful();
});
