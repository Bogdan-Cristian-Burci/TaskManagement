<?php

namespace App\Providers;

use App\Models\Attachment;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Observers\AttachmentObserver;
use App\Observers\BoardColumnObserver;
use App\Observers\BoardObserver;
use App\Observers\TaskObserver;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

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
        Passport::tokensCan([
            'read-user' => 'Read user information',
            'write-user' => 'Update user information',
            // Add more scopes as needed
        ]);

        Passport::tokensExpireIn(now()->addDays(15));
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::personalAccessTokensExpireIn(now()->addMonths(6));

        Task::observe(TaskObserver::class);
        Attachment::observe(AttachmentObserver::class);
        Board::observe(BoardObserver::class);
        BoardColumn::observe(BoardColumnObserver::class);
    }
}
