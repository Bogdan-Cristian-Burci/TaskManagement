<?php

namespace App\Providers;

use App\Models\Attachment;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\BoardType;
use App\Models\ChangeType;
use App\Models\Comment;
use App\Models\Organisation;
use App\Models\Priority;
use App\Models\Project;
use App\Models\Status;
use App\Models\Task;
use App\Models\TaskType;
use App\Policies\AttachmentPolicy;
use App\Policies\BoardColumnPolicy;
use App\Policies\BoardPolicy;
use App\Policies\BoardTypePolicy;
use App\Policies\ChangeTypePolicy;
use App\Policies\CommentPolicy;
use App\Policies\OrganisationPolicy;
use App\Policies\PriorityPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\StatusPolicy;
use App\Policies\TaskPolicy;
use App\Policies\TaskTypePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{

    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Board::class => BoardPolicy::class,
        Attachment::class => AttachmentPolicy::class,
        BoardColumn::class => BoardColumnPolicy::class,
        BoardType::class => BoardTypePolicy::class,
        ChangeType::class => ChangeTypePolicy::class,
        Comment::class => CommentPolicy::class,
        Organisation::class => OrganisationPolicy::class,
        Priority::class => PriorityPolicy::class,
        Project::class => ProjectPolicy::class,
        Task::class => TaskPolicy::class,
        TaskType::class => TaskTypePolicy::class,
        Status::class => StatusPolicy::class,
    ];

    public function register(): void
    {

    }

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
