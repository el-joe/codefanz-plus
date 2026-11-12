<?php

namespace App\Providers;

use App\Enums\MenuLocation;
use App\Enums\Role;
use App\Models\Career;
use App\Models\CareerTranslation;
use App\Models\ChangeRequest;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\Industry;
use App\Models\KillSwitch;
use App\Models\Media;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuItemTranslation;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\PageTranslation;
use App\Models\PortfolioCategory;
use App\Models\PortfolioProject;
use App\Models\PortfolioProjectTranslation;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\ProductVersion;
use App\Models\Service;
use App\Models\ServiceTranslation;
use App\Models\Statistic;
use App\Models\Technology;
use App\Models\TechnologyCategory;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\MenuResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

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
        ]);

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

        // A technology used in a published, publicly listed portfolio project shows as "Verified in projects".
        Technology::verifiedThrough('publishedPortfolioProjects');

        // Admin menu URLs use the location key; the row is created on first use.
        Route::bind('menu', fn (string $value): Menu => Menu::for(MenuLocation::tryFrom($value) ?? abort(404)));

        Gate::before(fn (User $user): ?bool => $user->hasRole(Role::SuperAdmin->value) && $user->is_active ? true : null);
    }
}
