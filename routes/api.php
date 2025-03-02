<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\BoardColumnController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\BoardTypeController;
use App\Http\Controllers\ChangeTypeController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\PriorityController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\StatusTransitionController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskTypeController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\APIAuthenticationController;
use App\Http\Controllers\OAuthSocialController;
use App\Http\Controllers\OrganisationController;
use App\Http\Controllers\TeamsController;
use App\Http\Controllers\ProjectController;

// Public routes
Route::post('register', [APIAuthenticationController::class, 'register']);
Route::post('login', [APIAuthenticationController::class, 'login']);

// Social login routes
Route::get('auth/{provider}', [OAuthSocialController::class, 'redirectToProvider']);
Route::get('auth/{provider}/callback', [OAuthSocialController::class, 'handleProviderCallback']);

// Protected routes
//*PUT and PATCH methods are not Laravel default methods for updating resources, so we use POST method to update resources*//
Route::middleware('auth:api')->group(function () {

    Route::get('/roles', [RolePermissionController::class, 'getRoles']);
    Route::get('/permissions', [RolePermissionController::class, 'getPermissions']);

    Route::middleware(['permission:manage roles'])->group(function () {
        Route::post('/roles/assign', [RolePermissionController::class, 'assignRole']);
        Route::post('/roles/remove', [RolePermissionController::class, 'removeRole']);

        Route::post('/permissions/assign', [RolePermissionController::class, 'assignPermission']);
        Route::post('/permissions/remove', [RolePermissionController::class, 'removePermission']);
    });

    Route::post('logout', [APIAuthenticationController::class, 'logout']);
    Route::get('user', [APIAuthenticationController::class, 'user']);

    //Organisation resource
    Route::apiResource('organisations', OrganisationController::class);

    //Team resource
    Route::apiResource('teams', TeamsController::class);
    Route::post('teams/{team}', [TeamsController::class, 'update']);
    Route::post('teams/{team}/attach-users', [TeamsController::class, 'attachUsers']);
    Route::post('teams/{team}/detach-users', [TeamsController::class, 'detachUser']);

    //Project resource
    Route::apiResource('projects', ProjectController::class);
    Route::post('projects/{project}/attach-users', [ProjectController::class, 'attachUsers']);
    Route::post('projects/{project}/detach-users', [ProjectController::class, 'detachUser']);

    Route::apiResource('boards', BoardController::class)->except('update');
    Route::post('boards/{board}', [BoardController::class, 'update']);

    Route::apiResource('attachments', AttachmentController::class);

    Route::apiResource('board-columns', BoardColumnController::class);
    Route::post('board-columns/reorder', [BoardColumnController::class, 'reorder']);

    Route::apiResource('board-types', BoardTypeController::class);
    Route::apiResource('change-types', ChangeTypeController::class);

    Route::get('tasks/{task}/comments', [CommentController::class, 'index']);
    Route::post('tasks/{task}/comments', [CommentController::class, 'store']);
    Route::apiResource('comments', CommentController::class)->except(['index', 'store']);

    Route::apiResource('organisations', OrganisationController::class);

    Route::apiResource('priorities', PriorityController::class);
    Route::post('priorities/reorder', [PriorityController::class, 'reorder']);

    Route::apiResource('tasks', TaskController::class);

    // Additional task endpoints
    Route::patch('tasks/{task}/change-status', [TaskController::class, 'changeStatus']);
    Route::patch('tasks/{task}/assign', [TaskController::class, 'assignTask']);

    // Task filtering routes
    Route::get('tasks/by-project/{project}', [TaskController::class, 'getTasksByProject']);
    Route::get('tasks/by-user/{user}', [TaskController::class, 'getTasksByUser']);
    Route::get('tasks/overdue', [TaskController::class, 'getOverdueTasks']);

    // Attachment routes
    Route::apiResource('attachments', AttachmentController::class)->except(['index']);
    Route::get('attachments/by-task/{task}', [AttachmentController::class, 'getByTask']);
    Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');

    Route::apiResource('task-types', TaskTypeController::class);
    Route::post('task-types/find-by-name', [TaskTypeController::class, 'findByName']);
    Route::post('task-types/clear-cache', [TaskTypeController::class, 'clearCache']);

    // Status routes
    Route::apiResource('statuses', StatusController::class);
    Route::post('statuses/find-by-name', [StatusController::class, 'findByName']);
    Route::get('statuses/default', [StatusController::class, 'getDefault']);
    Route::post('statuses/reorder', [StatusController::class, 'reorder']);
    Route::post('statuses/clear-cache', [StatusController::class, 'clearCache']);

    // ChangeType routes
    Route::apiResource('change-types', ChangeTypeController::class);
    Route::post('change-types/find-by-name', [ChangeTypeController::class, 'findByName'])
        ->name('change-types.find-by-name');
    Route::post('change-types/sync-task-histories', [ChangeTypeController::class, 'syncTaskHistories'])
        ->name('change-types.sync-task-histories');
    Route::post('change-types/clear-cache', [ChangeTypeController::class, 'clearCache'])
        ->name('change-types.clear-cache');

    // Priority routes
    Route::apiResource('priorities', PriorityController::class);
    Route::post('priorities/find-by-level', [PriorityController::class, 'findByLevel'])
        ->name('priorities.find-by-level');
    Route::get('priorities/highest', [PriorityController::class, 'getHighest'])
        ->name('priorities.highest');
    Route::get('priorities/lowest', [PriorityController::class, 'getLowest'])
        ->name('priorities.lowest');
    Route::post('priorities/reorder', [PriorityController::class, 'reorder'])
        ->name('priorities.reorder');
    Route::post('priorities/clear-cache', [PriorityController::class, 'clearCache'])
        ->name('priorities.clear-cache');

    // Status Transition routes
    Route::apiResource('status-transitions', StatusTransitionController::class);
    Route::get('status-transitions/from-status/{status}', [StatusTransitionController::class, 'getFromStatus'])
        ->name('status-transitions.from-status');
    Route::post('status-transitions/is-valid', [StatusTransitionController::class, 'isValidTransition'])
        ->name('status-transitions.is-valid');
    Route::post('status-transitions/clear-cache', [StatusTransitionController::class, 'clearCache'])
        ->name('status-transitions.clear-cache');

    // Comments routes
    Route::apiResource('tasks.comments', CommentController::class);
    Route::get('comments/{comment}', [CommentController::class, 'show'])->name('comments.show');
    Route::post('comments/{id}/restore', [CommentController::class, 'restore'])->name('comments.restore');
    Route::get('user/comments', [CommentController::class, 'getUserComments'])->name('user.comments');
    // Your other API endpoints go here...
});
