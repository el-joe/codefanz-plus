<?php

use App\Enums\EmploymentType;
use App\Models\Career;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\PortfolioProject;
use App\Models\Testimonial;

/**
 * @return list<array<string, mixed>>
 */
function jsonLd(string $html): array
{
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

    return array_map(fn (string $json): array => json_decode($json, true, flags: JSON_THROW_ON_ERROR), $matches[1]);
}

test('the faq page groups questions by category with sanitized answers', function () {
    $pricing = FaqCategory::factory()->withTranslation('en', ['name' => 'Projects & pricing'])->create(['sort_order' => 0]);
    $support = FaqCategory::factory()->withTranslation('en', ['name' => 'Support'])->create(['sort_order' => 1]);
    Faq::factory()->withTranslation('en', ['question' => 'What happens after launch?'])->create(['faq_category_id' => $support->id]);
    Faq::factory()->withTranslation('en', ['question' => 'How much does it cost?', 'answer' => "It **depends**.<script>alert(1)</script>\n\n[x](javascript:alert(1))"])->create(['faq_category_id' => $pricing->id]);
    Faq::factory()->draft()->withTranslation('en', ['question' => 'Secret draft question?'])->create();
    Faq::factory()->withTranslation('en', ['question' => 'Trashed question?'])->create()->delete();

    $this->get('/en/faq')
        ->assertSuccessful()
        ->assertSeeInOrder(['Projects &amp; pricing', 'How much does it cost?', 'Support', 'What happens after launch?'], false)
        ->assertSee('<strong>depends</strong>', false)
        ->assertSee('data-faq-search hidden', false)
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('javascript:alert', false)
        ->assertDontSee('Secret draft question?')
        ->assertDontSee('Trashed question?');
});

test('the faq page emits valid, escaped FAQPage structured data', function () {
    Faq::factory()->withTranslation('en', ['question' => 'Is </script><b>"quoted"</b> safe?', 'answer' => 'Yes & no.'])->create();
    Faq::factory()->withTranslation('ar', ['question' => 'هل الكود ملكي؟', 'answer' => 'نعم.'])->create();

    $html = $this->get('/en/faq')->assertSuccessful()->getContent();
    $schema = collect(jsonLd($html))->firstWhere('@type', 'FAQPage');

    expect($html)->not->toContain('Is </script>')
        ->and($schema['mainEntity'])->toHaveCount(1)
        ->and($schema['mainEntity'][0]['name'])->toBe('Is </script><b>"quoted"</b> safe?')
        ->and($schema['mainEntity'][0]['acceptedAnswer']['text'])->toBe('Yes & no.');

    $arabic = collect(jsonLd($this->get('/ar/faq')->assertSee('dir="rtl"', false)->getContent()))->firstWhere('@type', 'FAQPage');
    expect($arabic['mainEntity'][0]['name'])->toBe('هل الكود ملكي؟')
        ->and($arabic['inLanguage'])->toBe('ar');
});

test('the careers page lists only open positions', function () {
    Career::factory()->withTranslation('en', ['title' => 'Laravel Developer', 'department' => 'Engineering'])->create(['is_remote' => true]);
    Career::factory()->withTranslation('en', ['title' => 'Closing Today'])->create(['closes_at' => today()->toDateString()]);
    Career::factory()->closed()->withTranslation('en', ['title' => 'Closed Role'])->create();
    Career::factory()->draft()->withTranslation('en', ['title' => 'Draft Role'])->create();
    Career::factory()->withTranslation('ar', ['title' => 'وظيفة عربية', 'slug' => 'وظيفة-عربية'])->create();

    $this->get('/en/careers')
        ->assertSuccessful()
        ->assertSee('Laravel Developer')
        ->assertSee('Closing Today')
        ->assertSee('Remote')
        ->assertDontSee('Closed Role')
        ->assertDontSee('Draft Role')
        ->assertDontSee('وظيفة عربية');

    $this->get('/ar/careers')->assertSee('dir="rtl"', false)->assertSee('وظيفة عربية')->assertDontSee('Laravel Developer');
});

test('closed, draft and trashed positions are not found', function () {
    Career::factory()->closed()->withTranslation('en', ['slug' => 'closed-role'])->create();
    Career::factory()->draft()->withTranslation('en', ['slug' => 'draft-role'])->create();
    Career::factory()->withTranslation('en', ['slug' => 'trashed-role'])->create()->delete();
    Career::factory()->withTranslation('en', ['slug' => 'english-only'])->create();

    $this->get('/en/careers/closed-role')->assertNotFound();
    $this->get('/en/careers/draft-role')->assertNotFound();
    $this->get('/en/careers/trashed-role')->assertNotFound();
    $this->get('/ar/careers/english-only')->assertNotFound();
});

test('a position page shows the role, an email apply link and JobPosting data', function () {
    $career = Career::factory()
        ->withTranslation('en', [
            'title' => 'Senior </script> Developer', 'slug' => 'senior-developer', 'location' => 'Cairo',
            'responsibilities' => ['Build APIs'], 'requirements' => ['5 years PHP'], 'body' => "## About\n\n<script>alert(1)</script>Great team.",
        ])
        ->withTranslation('ar', ['title' => 'مطور أول', 'slug' => 'مطور-أول'])
        ->create(['employment_type' => EmploymentType::Contract, 'is_remote' => true, 'closes_at' => now()->addMonth()->toDateString()]);

    $html = $this->get('/en/careers/senior-developer')
        ->assertSuccessful()
        ->assertSee('Build APIs')
        ->assertSee('5 years PHP')
        ->assertSee('<h2>About</h2>', false)
        ->assertSee('mailto:hello@codefanz.com?subject=', false)
        ->assertSee('hreflang="ar"', false)
        ->assertDontSee('<script>alert(1)</script>', false)
        ->getContent();

    $posting = collect(jsonLd($html))->firstWhere('@type', 'JobPosting');

    expect($posting['title'])->toBe('Senior </script> Developer')
        ->and($posting['employmentType'])->toBe('CONTRACT')
        ->and($posting['jobLocationType'])->toBe('TELECOMMUTE')
        ->and($posting['jobLocation']['address']['addressLocality'])->toBe('Cairo')
        ->and($posting['validThrough'])->toStartWith($career->closes_at->toDateString())
        ->and($posting['hiringOrganization']['name'])->toBe('CodeFanz')
        ->and($posting['datePosted'])->toBe($career->published_at->toDateString());
});

test('the testimonials page shows only consented, published quotes with their case study', function () {
    $project = PortfolioProject::factory()->withTranslation('en', ['title' => 'ClinicFlow', 'slug' => 'clinicflow'])->create();
    Testimonial::factory()->withTranslation('en', ['quote' => 'Delivered on time.'])->create(['author_name' => 'Rana Hassan', 'rating' => 5, 'portfolio_project_id' => $project->id]);
    Testimonial::factory()->withoutConsent()->withTranslation('en', ['quote' => 'Unconsented quote.'])->create();
    Testimonial::factory()->draft()->withTranslation('en', ['quote' => 'Draft quote.'])->create();

    $this->get('/en/testimonials')
        ->assertSuccessful()
        ->assertSee('Delivered on time.')
        ->assertSee('RH')
        ->assertSee('Rated 5 out of 5')
        ->assertSee(route('portfolio.show', ['locale' => 'en', 'slug' => 'clinicflow']), false)
        ->assertDontSee('Unconsented quote.')
        ->assertDontSee('Draft quote.');

    $this->get('/en/portfolio/clinicflow')->assertSee('What the client says')->assertSee('Delivered on time.');
});
