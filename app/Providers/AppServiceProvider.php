<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\ChangeRequest;
use App\Models\Industry;
use App\Models\KillSwitch;
use App\Models\Media;
use App\Models\Page;
use App\Models\Service;
use App\Models\Technology;
use App\Models\TechnologyCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
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
        ]);

        Gate::before(fn (User $user): ?bool => $user->hasRole(Role::SuperAdmin->value) && $user->is_active ? true : null);
    }
}
