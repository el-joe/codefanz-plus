<?php

namespace Database\Seeders;

use App\Enums\PromptPurpose;
use App\Models\BrandProfile;
use App\Models\PromptTemplate;
use Illuminate\Database\Seeder;

/**
 * Starter brand voice and one prompt template per purpose. Existing rows are never overwritten, so edits made in
 * the admin survive re-seeding. These are defaults, not demo content.
 */
class AiStudioSeeder extends Seeder
{
    private const SYSTEM = <<<'TEXT'
You are a senior copywriter for {{brand_name}}, a software development company. Write for: {{audience}}
Brand voice:
{{brand_voice}}
Always:
{{do_rules}}
Never:
{{dont_rules}}
Never use these claims: {{banned_claims}}
Terminology (English = Arabic), use exactly:
{{glossary}}
Write in {{languages}}. Arabic text is Modern Standard Arabic, natural and not a literal translation.
TEXT;

    public function run(): void
    {
        BrandProfile::query()->firstOrCreate(['is_default' => true], [
            'name' => 'CodeFanz',
            'tone' => [
                'en' => 'Clear, practical and confident without hype. Short sentences, concrete benefits, plain technical language.',
                'ar' => 'واضح وعملي وواثق دون مبالغة. جمل قصيرة وفوائد ملموسة ولغة تقنية مبسطة.',
            ],
            'audience' => [
                'en' => 'Founders, CTOs and operations leads of growing companies in the Middle East and beyond.',
                'ar' => 'المؤسسون والمديرون التقنيون وقادة العمليات في الشركات النامية في الشرق الأوسط وخارجه.',
            ],
            'do_rules' => [
                'en' => ['Explain trade-offs honestly', 'Use concrete examples from the brief only', 'End with one clear call to action'],
                'ar' => ['اشرح المفاضلات بصدق', 'استخدم أمثلة من المدخلات فقط', 'اختم بدعوة واضحة واحدة لاتخاذ إجراء'],
            ],
            'dont_rules' => [
                'en' => ['Invent statistics, clients, awards or prices', 'Promise results or rankings', 'Use buzzwords without explaining them'],
                'ar' => ['اختلاق إحصاءات أو عملاء أو جوائز أو أسعار', 'الوعد بنتائج أو ترتيب في محركات البحث', 'استخدام مصطلحات رنانة دون شرح'],
            ],
            'banned_claims' => ['guaranteed', 'guarantee', '#1', 'number one', 'best in the world', 'risk-free', 'نضمن', 'الأفضل في العالم', 'رقم 1'],
            'glossary' => [
                ['en' => 'web application', 'ar' => 'تطبيق ويب'],
                ['en' => 'milestone', 'ar' => 'مرحلة'],
                ['en' => 'customer portal', 'ar' => 'بوابة العملاء'],
                ['en' => 'maintenance', 'ar' => 'الصيانة'],
            ],
            'allowed_domains' => [],
        ]);

        foreach ($this->templates() as $template) {
            $model = PromptTemplate::query()->withTrashed()->firstOrCreate(['key' => $template['key']], [...$template, 'version' => 1, 'is_active' => true]);

            if ($model->wasRecentlyCreated) {
                $model->versions()->create([
                    'version' => 1,
                    'system_template' => $model->system_template,
                    'user_template' => $model->user_template,
                    'variables' => $model->variables,
                ]);
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function templates(): array
    {
        return [
            [
                'key' => 'blog-article', 'name' => 'Blog article', 'purpose' => PromptPurpose::BlogDraft,
                'description' => 'Long-form, SEO-ready article draft',
                'system_template' => self::SYSTEM,
                'user_template' => "Write a blog article.\nTopic: {{topic}}\nAudience: {{audience_note}}\nKeywords: {{keywords}}\nTarget length: {{length}}\nNotes and facts you may use: {{notes}}\nUse H2/H3 headings, short paragraphs and a FAQ section when useful. The excerpt is one or two sentences. The SEO title is 30–60 characters; the meta description 70–160.",
                'variables' => [
                    ['name' => 'topic', 'label' => 'Topic', 'required' => true, 'max' => 300],
                    ['name' => 'audience_note', 'label' => 'Audience', 'required' => false, 'max' => 300],
                    ['name' => 'keywords', 'label' => 'Keywords', 'required' => false, 'max' => 300],
                    ['name' => 'length', 'label' => 'Length (e.g. ~1,200 words)', 'required' => false, 'max' => 60],
                    ['name' => 'notes', 'label' => 'Notes and facts', 'required' => false, 'max' => 4000],
                ],
            ],
            [
                'key' => 'website-copy', 'name' => 'Improve website copy', 'purpose' => PromptPurpose::ServiceCopy,
                'description' => 'Rewrite a page, service, product or case study',
                'system_template' => self::SYSTEM,
                'user_template' => "Improve the current content of this website item. Keep every fact; do not add new ones.\nGoal: {{goal}}\nNotes: {{notes}}",
                'variables' => [
                    ['name' => 'goal', 'label' => 'What should improve', 'required' => true, 'max' => 500],
                    ['name' => 'notes', 'label' => 'Notes and facts', 'required' => false, 'max' => 3000],
                ],
            ],
            [
                'key' => 'seo-meta', 'name' => 'SEO title & description', 'purpose' => PromptPurpose::SeoMeta,
                'description' => 'SEO title and meta description for an existing item',
                'system_template' => self::SYSTEM,
                'user_template' => "Write an SEO title (30–60 characters) and a meta description (70–160 characters) that accurately describe the current content.\nFocus keyword: {{focus_keyword}}",
                'variables' => [
                    ['name' => 'focus_keyword', 'label' => 'Focus keyword', 'required' => false, 'max' => 120],
                ],
            ],
            [
                'key' => 'translate-en-ar', 'name' => 'Translate English → Arabic', 'purpose' => PromptPurpose::Translation,
                'description' => 'Arabic version of an existing item using the glossary',
                'system_template' => self::SYSTEM,
                'user_template' => "Translate the item for Arabic-speaking readers. Keep Markdown structure, product names and code in English.\nNotes: {{notes}}",
                'variables' => [
                    ['name' => 'notes', 'label' => 'Notes for the translator', 'required' => false, 'max' => 1000],
                ],
            ],
            [
                'key' => 'social-post', 'name' => 'Social post', 'purpose' => PromptPurpose::SocialPost,
                'description' => 'Caption, hashtags and call to action',
                'system_template' => self::SYSTEM,
                'user_template' => "Write a social media post for {{platform}}.\nAbout: {{topic}}\nLink to promote (this website only): {{link}}\nKeep it within the platform's usual length. Hashtags: up to five, space separated.",
                'variables' => [
                    ['name' => 'platform', 'label' => 'Platform', 'required' => true, 'max' => 40],
                    ['name' => 'topic', 'label' => 'Topic', 'required' => true, 'max' => 1000],
                    ['name' => 'link', 'label' => 'Link', 'required' => false, 'max' => 300],
                ],
            ],
            [
                'key' => 'email-draft', 'name' => 'Email draft', 'purpose' => PromptPurpose::Email,
                'description' => 'Campaigns and follow-ups',
                'system_template' => self::SYSTEM,
                'user_template' => "Write a marketing email.\nGoal: {{goal}}\nAudience: {{recipients}}\nKey points: {{points}}",
                'variables' => [
                    ['name' => 'goal', 'label' => 'Goal', 'required' => true, 'max' => 500],
                    ['name' => 'recipients', 'label' => 'Recipients', 'required' => false, 'max' => 300],
                    ['name' => 'points', 'label' => 'Key points', 'required' => false, 'max' => 2000],
                ],
            ],
            [
                'key' => 'ad-copy', 'name' => 'Ad copy', 'purpose' => PromptPurpose::AdCopy,
                'description' => 'Headline, description and call to action',
                'system_template' => self::SYSTEM,
                'user_template' => "Write ad copy for {{channel}}.\nOffer: {{offer}}\nThe headline has at most 30 characters; the description at most 90.",
                'variables' => [
                    ['name' => 'channel', 'label' => 'Channel', 'required' => true, 'max' => 60],
                    ['name' => 'offer', 'label' => 'What is promoted', 'required' => true, 'max' => 1000],
                ],
            ],
            [
                'key' => 'radar-proposal', 'name' => 'Freelance proposal', 'purpose' => PromptPurpose::RadarProposal,
                'description' => 'Proposal draft for a Freelance Radar project (never submitted)',
                'system_template' => self::SYSTEM,
                'user_template' => "Draft a proposal for this freelance project. A person will review, edit and send it themselves.\nProject title: {{project_title}}\nProject description: {{project_description}}\nSkills requested: {{skills}}\nFacts about our relevant experience (mention no other projects, clients, portfolio items, years or numbers): {{our_facts}}\nPrice or rate to quote (if this is empty, do not mention any price, rate, budget or currency): {{price}}\nStructure: a short opening that shows we understood the need, our approach in three to five bullet points, one or two clarifying questions, and a short closing. Plain text with simple dash bullets, under 250 words. Never claim we already built the same product unless the facts say so.",
                'variables' => [
                    ['name' => 'project_title', 'label' => 'Project title', 'required' => true, 'max' => 255],
                    ['name' => 'project_description', 'label' => 'Project description', 'required' => true, 'max' => 8000],
                    ['name' => 'skills', 'label' => 'Skills requested', 'required' => false, 'max' => 500],
                    ['name' => 'our_facts', 'label' => 'Facts about our experience', 'required' => false, 'max' => 2000],
                    ['name' => 'price', 'label' => 'Price or rate to quote', 'required' => false, 'max' => 100],
                ],
            ],
        ];
    }
}
