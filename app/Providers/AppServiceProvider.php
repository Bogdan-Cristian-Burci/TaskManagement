<?php

namespace App\Providers;

use App\Models\Attachment;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\BoardType;
use App\Models\Task;
use App\Models\Team;
use App\Observers\AttachmentObserver;
use App\Observers\BoardColumnObserver;
use App\Observers\BoardObserver;
use App\Observers\BoardTypeObserver;
use App\Observers\TaskObserver;
use App\Observers\TeamObserver;
use App\Services\BoardService;
use App\Services\BoardTemplateService;
use App\Services\BoardTypeService;
use App\Services\ChangeTypeService;
use App\Services\ProjectService;
use App\Services\SprintService;
use App\Services\TaskService;
use App\Services\TeamService;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register services with proper dependency order

        // Register TaskService first as it has no dependencies
        $this->app->singleton(TaskService::class, function ($app) {
            return new TaskService();
        });

        // Register SprintService next
        $this->app->singleton(SprintService::class, function ($app) {
            return new SprintService();
        });

        // Register BoardService with SprintService dependency
        $this->app->singleton(BoardService::class, function ($app) {
            return new BoardService(
                $app->make(SprintService::class)
            );
        });

        // Register ProjectService with BoardService dependency
        $this->app->singleton(ProjectService::class, function ($app) {
            return new ProjectService(
                $app->make(BoardService::class),
                $app->make(TeamService::class),
                $app->make(TaskService::class)
            );
        });

        $this->app->singleton(BoardTemplateService::class);
        $this->app->singleton(ChangeTypeService::class);
        $this->app->singleton(BoardTypeService::class);
        $this->app->singleton(TeamService::class);
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
        BoardType::observe(BoardTypeObserver::class);
        Team::observe(TeamObserver::class);
    }
}
