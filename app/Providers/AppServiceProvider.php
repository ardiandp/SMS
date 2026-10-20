<?php

namespace App\Providers;

use App\Domain\Tenancy\CurrentSchool;
use App\Models\Domain\Academics\Quiz;
use App\Models\Domain\Academics\QuizQuestion;
use App\Policies\QuizPolicy;
use App\Policies\QuizQuestionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CurrentSchool::class, fn () => new CurrentSchool);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // QuizQuestion lives under a Domain namespace so Laravel cannot
        // auto-discover its policy by naming convention — map it explicitly.
        // Without this, every $this->authorize() on QuizQuestion throws
        // "Gate policy not defined" instead of running QuizQuestionPolicy.
        Gate::policy(Quiz::class, QuizPolicy::class);
        Gate::policy(QuizQuestion::class, QuizQuestionPolicy::class);
    }
}
