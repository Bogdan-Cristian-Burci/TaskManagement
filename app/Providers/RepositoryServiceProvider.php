<?php

namespace App\Providers;

use App\Repositories\ChangeTypeRepository;
use App\Repositories\Interfaces\ChangeTypeRepositoryInterface;
use App\Repositories\Interfaces\PriorityRepositoryInterface;
use App\Repositories\Interfaces\StatusRepositoryInterface;
use App\Repositories\Interfaces\StatusTransitionRepositoryInterface;
use App\Repositories\Interfaces\TaskTypeRepositoryInterface;
use App\Repositories\PriorityRepository;
use App\Repositories\StatusRepository;
use App\Repositories\StatusTransitionRepository;
use App\Repositories\TaskTypeRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TaskTypeRepositoryInterface::class, TaskTypeRepository::class);
        $this->app->bind(PriorityRepositoryInterface::class, PriorityRepository::class);
        $this->app->bind(StatusRepositoryInterface::class, StatusRepository::class);
        $this->app->bind(ChangeTypeRepositoryInterface::class, ChangeTypeRepository::class);
        $this->app->bind(StatusTransitionRepositoryInterface::class, StatusTransitionRepository::class);
    }

    public function boot(): void
    {
    }
}
