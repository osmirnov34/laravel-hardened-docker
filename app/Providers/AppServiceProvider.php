<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Support\Facades\Log;
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
        Model::preventLazyLoading();

        Model::handleLazyLoadingViolationUsing(
            function (Model $model, string $relation): void {
                if (! $model->exists || $model->wasRecentlyCreated) {
                    return;
                }

                if (! $this->app->isProduction()) {
                    throw new LazyLoadingViolationException($model, $relation);
                }

                Log::warning('Lazy loaded relation.', [
                    'model' => $model::class,
                    'relation' => $relation,
                ]);
            }
        );
    }
}
