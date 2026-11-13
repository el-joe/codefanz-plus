<?php

namespace App\Providers;

use App\Actions\Seo\RecordSlugRedirect;
use App\Contracts\AiProvider;
use App\Enums\MenuLocation;
use App\Enums\Role;
use App\Events\LeadCreated;
use App\Models\AiGeneration;
use App\Models\AutomationWorkflow;
use App\Models\BrandProfile;
use App\Models\Career;
use App\Models\CareerTranslation;
use App\Models\ChangeRequest;
use App\Models\ChatSession;
use App\Models\ContactMessage;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\Industry;
use App\Models\Invoice;
use App\Models\KillSwitch;
use App\Models\Lead;
use App\Models\McpClient;
use App\Models\Media;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuItemTranslation;
use App\Models\Milestone;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\PageTranslation;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\PortfolioCategory;
use App\Models\PortfolioProject;
use App\Models\PortfolioProjectTranslation;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\PostTranslation;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\ProductVersion;
use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\PromptTemplate;
use App\Models\RadarFilter;
use App\Models\RadarPlatform;
use App\Models\RadarProject;
use App\Models\RadarProposal;
use App\Models\Receipt;
use App\Models\Redirect;
use App\Models\Refund;
use App\Models\SeoAudit;
use App\Models\SeoMeta;
use App\Models\Service;
use App\Models\ServiceTranslation;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\SocialPostVariant;
use App\Models\Statistic;
use App\Models\SupportTicket;
use App\Models\Tag;
use App\Models\Technology;
use App\Models\TechnologyCategory;
use App\Models\Testimonial;
use App\Models\TicketReply;
use App\Models\User;
use App\Services\Ai\AiGateway;
use App\Services\Analytics\MeasurementProtocol;
use App\Services\CurrentCustomer;
use App\Services\LocaleAwareVite;
use App\Services\MenuResolver;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Seo\RedirectMap;
use App\Services\Seo\SeoMetaResolver;
use App\Services\Seo\SitemapBuilder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One per request (and per queued job): the customer a portal user is acting for.
        $this->app->scoped(CurrentCustomer::class);

        // Payment gateway drivers by key (config codefanz.payments.gateways), credentials from Settings.
        $this->app->scoped(PaymentGatewayManager::class);

        // The provider selected in Settings → Integrations (a NullAiProvider until credentials are saved).
        // Features should call AiGateway, which adds the kill switch, budget and usage logging.
        $this->app->bind(AiProvider::class, fn ($app): AiProvider => $app->make(AiGateway::class)->provider());

        // @vite / @fonts: locale-aware font preloads, WOFF2 only (M11 performance).
        $this->app->singleton(Vite::class, LocaleAwareVite::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        // Behind a load balancer / CDN: trust its X-Forwarded-* headers for the client IP and https scheme (M12).
        if (config('codefanz.security.trusted_proxies') !== []) {
            $proxies = config('codefanz.security.trusted_proxies');
            TrustProxies::at($proxies === ['*'] ? '*' : $proxies);
        }

        Relation::enforceMorphMap([
            'user' => User::class,
            'page' => Page::class,
            'change_request' => ChangeRequest::class,
            'kill_switch' => KillSwitch::class,
            'media' => Media::class,
            'service' => Service::class,
            'technology' => Technology::class,
            'technology_category' => TechnologyCategory::class,
            'industry' => Industry::class,
            'portfolio_category' => PortfolioCategory::class,
            'portfolio_project' => PortfolioProject::class,
            'product' => Product::class,
            'product_version' => ProductVersion::class,
            'testimonial' => Testimonial::class,
            'faq' => Faq::class,
            'faq_category' => FaqCategory::class,
            'statistic' => Statistic::class,
            'career' => Career::class,
            'menu' => Menu::class,
            'menu_item' => MenuItem::class,
            'page_section' => PageSection::class,
            'contact_message' => ContactMessage::class,
            'lead' => Lead::class,
            'customer' => Customer::class,
            'project' => Project::class,
            'milestone' => Milestone::class,
            'project_note' => ProjectNote::class,
            'document' => Document::class,
            'conversation' => Conversation::class,
            'conversation_message' => ConversationMessage::class,
            'support_ticket' => SupportTicket::class,
            'ticket_reply' => TicketReply::class,
            'payment_link' => PaymentLink::class,
            'payment' => Payment::class,
            'invoice' => Invoice::class,
            'receipt' => Receipt::class,
            'refund' => Refund::class,
            'credit_note' => CreditNote::class,
            'post' => Post::class,
            'brand_profile' => BrandProfile::class,
            'prompt_template' => PromptTemplate::class,
            'ai_generation' => AiGeneration::class,
            'post_category' => PostCategory::class,
            'tag' => Tag::class,
            'seo_meta' => SeoMeta::class,
            'redirect' => Redirect::class,
            'seo_audit' => SeoAudit::class,
            'social_account' => SocialAccount::class,
            'social_post' => SocialPost::class,
            'social_post_variant' => SocialPostVariant::class,
            'mcp_client' => McpClient::class,
            'radar_project' => RadarProject::class,
            'radar_filter' => RadarFilter::class,
            'radar_platform' => RadarPlatform::class,
            'radar_proposal' => RadarProposal::class,
            'automation_workflow' => AutomationWorkflow::class,
        ]);

        // Password policy (docs/SECURITY_POLICY.md §1): min 12 characters, breached-password check.
        Password::defaults(function (): Password {
            $rule = Password::min((int) config('codefanz.security.password_min_length'))->max(128);

            return config('codefanz.security.check_breached_passwords') ? $rule->uncompromised() : $rule;
        });

        // Fortify's password reset / confirmation / change and 2FA confirmation routes (ThrottleSensitiveAuthRoutes).
        RateLimiter::for('auth-sensitive', fn (Request $request): Limit => Limit::perMinute((int) config('codefanz.security.auth_attempts_per_minute'))
            ->by('auth-sensitive:'.$request->route()?->getName().'|'.$request->ip()));

        // Payment gateway webhooks: generous per gateway and IP; bad signatures are rejected before any work.
        RateLimiter::for('webhooks', fn (Request $request): Limit => Limit::perMinute(120)->by('webhooks:'.$request->ip()));

        // Public pay page: opening and starting checkouts per visitor IP.
        RateLimiter::for('pay', fn (Request $request): Limit => Limit::perMinute(30)->by('pay:'.$request->ip()));

        // Public contact form: a few messages a minute and a daily cap per visitor IP.
        RateLimiter::for('contact', fn (Request $request): array => [
            Limit::perMinute((int) config('codefanz.contact_form.per_minute'))->by('contact-minute:'.$request->ip()),
            Limit::perDay((int) config('codefanz.contact_form.per_day'))->by('contact-day:'.$request->ip()),
        ]);

        // Request a Quote: same limits as the contact form, counted separately.
        RateLimiter::for('quote', fn (Request $request): array => [
            Limit::perMinute((int) config('codefanz.contact_form.per_minute'))->by('quote-minute:'.$request->ip()),
            Limit::perDay((int) config('codefanz.contact_form.per_day'))->by('quote-day:'.$request->ip()),
        ]);

        // AI Project Assistant: new conversations per IP, and messages per conversation and per IP.
        RateLimiter::for('assistant-start', fn (Request $request): Limit => Limit::perHour((int) config('codefanz.assistant.sessions_per_ip_per_hour'))->by('assistant-start:'.$request->ip()));

        RateLimiter::for('assistant-message', function (Request $request): array {
            $session = $request->route('chatSession');
            $sessionKey = $session instanceof ChatSession ? $session->ulid : (string) $session;

            return [
                Limit::perMinute((int) config('codefanz.assistant.messages_per_session_per_minute'))->by('assistant-session:'.$sessionKey),
                Limit::perMinute((int) config('codefanz.assistant.messages_per_ip_per_minute'))->by('assistant-ip-minute:'.$request->ip()),
                Limit::perDay((int) config('codefanz.assistant.messages_per_ip_per_day'))->by('assistant-ip-day:'.$request->ip()),
            ];
        });

        // Resolved menus are cached per locale; any change to menus or to content they link to invalidates them.
        foreach ([
            Menu::class, MenuItem::class, MenuItemTranslation::class,
            Page::class, PageTranslation::class, Service::class, ServiceTranslation::class,
            Product::class, ProductTranslation::class, PortfolioProject::class, PortfolioProjectTranslation::class,
            Career::class, CareerTranslation::class,
        ] as $model) {
            $model::saved(fn () => MenuResolver::flush());
            $model::deleted(fn () => MenuResolver::flush());
        }

        // Sitemaps are cached as strings: any change to listed content or SEO settings rebuilds them on next request.
        foreach ([
            Page::class, PageTranslation::class, Service::class, ServiceTranslation::class,
            Product::class, ProductTranslation::class, PortfolioProject::class, PortfolioProjectTranslation::class,
            Career::class, CareerTranslation::class, Post::class, PostTranslation::class, SeoMeta::class,
        ] as $model) {
            $model::saved(fn () => SitemapBuilder::flush());
            $model::deleted(fn () => SitemapBuilder::flush());
        }

        SeoMeta::saved(fn () => SeoMetaResolver::flush());
        SeoMeta::deleted(fn () => SeoMetaResolver::flush());
        Redirect::saved(fn () => RedirectMap::flush());
        Redirect::deleted(fn () => RedirectMap::flush());

        // A published item whose slug changes keeps its old URL working through an automatic 301.
        foreach ([PageTranslation::class, ServiceTranslation::class, PortfolioProjectTranslation::class, ProductTranslation::class, CareerTranslation::class, PostTranslation::class] as $translation) {
            $translation::updated(fn (Model $model) => app(RecordSlugRedirect::class)->fromTranslation($model));
        }

        // Consented GA4 Measurement Protocol event for public lead submissions (decided in the visitor's request).
        Event::listen(LeadCreated::class, function (LeadCreated $event): void {
            app(MeasurementProtocol::class)->leadSubmitted($event->lead, request());
        });

        // A technology used in a published, publicly listed portfolio project shows as "Verified in projects".
        Technology::verifiedThrough('publishedPortfolioProjects');

        // Admin menu URLs use the location key; the row is created on first use.
        Route::bind('menu', fn (string $value): Menu => Menu::for(MenuLocation::tryFrom($value) ?? abort(404)));

        Gate::before(fn (User $user): ?bool => $user->hasRole(Role::SuperAdmin->value) && $user->is_active ? true : null);
    }
}
