<?php

use App\Http\Controllers\BoardTemplateController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\UserPermissionOverrideController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\BoardTypeController;
use App\Http\Controllers\ChangeTypeController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PriorityController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\SprintController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\StatusTransitionController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskTypeController;
use App\Http\Controllers\TwoFactorAuthController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\APIAuthenticationController;
use App\Http\Controllers\OAuthSocialController;
use App\Http\Controllers\OrganisationController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\ProjectController;

// Authentication routes
Route::post('/register', [APIAuthenticationController::class, 'register']);
Route::post('/login', [APIAuthenticationController::class, 'login'])
    ->middleware('throttle-login:5,1'); // 5 attempts per minute;
Route::post('/logout', [APIAuthenticationController::class, 'logout'])
    ->middleware('auth:api');
// Refresh token endpoint doesn't need auth:api middleware since it validates the refresh token itself
Route::post('/refresh-token', [APIAuthenticationController::class, 'refreshToken']);

// Password reset routes
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink']);
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])->name('password.update');

// Email verification routes
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->name('verification.verify')->middleware('signed');
Route::post('/email/verification-notification', [EmailVerificationController::class, 'resendVerificationEmail'])
    ->middleware(['auth:api', 'throttle:6,1'])
    ->name('verification.send');

// Two-factor authentication routes
Route::prefix('two-factor')->middleware('auth:api')->group(function () {
    Route::post('/enable', [TwoFactorAuthController::class, 'enable']);
    Route::post('/verify', [TwoFactorAuthController::class, 'verify']);
    Route::post('/disable', [TwoFactorAuthController::class, 'disable']);
    Route::post('/authenticate', [TwoFactorAuthController::class, 'authenticate']);
    Route::post('/recovery', [TwoFactorAuthController::class, 'recovery']);
});

// OAuth routes
Route::get('auth/{provider}', [OAuthSocialController::class, 'redirectToProvider'])->name('oauth.redirect');
Route::get('auth/{provider}/callback', [OAuthSocialController::class, 'handleProviderCallback'])->name('oauth.callback');
Route::get('auth/providers', [OAuthSocialController::class, 'providers'])->name('oauth.providers');
// Protected routes
//*PUT and PATCH methods are not Laravel default methods for updating resources, so we use POST method to update resources*//
Route::middleware(['auth:api','org.context'])->group(function () {

    // User routes
    Route::apiResource('users', UserController::class);
    Route::post('users/{id}/restore', [UserController::class, 'restore'])->name('users.restore');

   // User related resources
    Route::get('users/{user}/teams', [UserController::class, 'teams'])->name('users.teams');
    Route::get('users/{user}/tasks', [UserController::class, 'tasks'])->name('users.tasks');
    Route::get('users/{user}/projects', [UserController::class, 'projects'])->name('users.projects');

    // User invitation routes
    Route::post('/users/invite', [UserController::class, 'inviteToOrganisation']);

    // Organization switching
    Route::post('/users/switch-organisation', [UserController::class, 'switchOrganisation']);

    // User roles management
    Route::put('users/{user}/roles', [UserController::class, 'updateRoles'])->name('users.roles.update');
   // Route::get('roles', [UserController::class, 'roles'])->name('roles.index');

    Route::get('/role-permissions/permissions', [RolePermissionController::class, 'getPermissions']);

    Route::middleware(['permission:manage roles'])->group(function () {
        Route::post('/roles/assign', [RolePermissionController::class, 'assignRole']);
        Route::post('/roles/remove', [RolePermissionController::class, 'removeRole']);

        Route::post('/permissions/assign', [RolePermissionController::class, 'assignPermission']);
        Route::post('/permissions/remove', [RolePermissionController::class, 'removePermission']);
    });

    Route::post('logout', [APIAuthenticationController::class, 'logout']);
    Route::get('user', [APIAuthenticationController::class, 'user']);

    // Role and Permission Management API Routes

        // Role routes
        Route::apiResource('roles', RoleController::class);
        Route::post('roles/{id}/permissions', [RoleController::class, 'addPermissions']);
        Route::delete('roles/{id}/permissions', [RoleController::class, 'removePermissions']);
        Route::post('roles/{id}/revert', [RoleController::class, 'revertToSystem']);

        // Permissions
        Route::get('/permissions', [PermissionController::class, 'index']);
        Route::get('/permissions/categories', [PermissionController::class, 'categories']);


        // User permission overrides
        Route::get('users/{user}/permission-overrides', [UserPermissionOverrideController::class, 'index']);
        Route::post('users/{user}/permission-overrides', [UserPermissionOverrideController::class, 'store']);
        Route::delete('users/{user}/permission-overrides/{permission}', [UserPermissionOverrideController::class, 'destroy']);



    // Organisation routes
    Route::apiResource('organisations', OrganisationController::class);
    Route::post('organisations/{id}/restore', [OrganisationController::class, 'restore'])
        ->name('organisations.restore');

// Organisation members management
    Route::get('organisations/{organisation}/users', [OrganisationController::class, 'users'])
        ->name('organisations.users.index');
    Route::post('organisations/{organisation}/users', [OrganisationController::class, 'addUser'])
        ->name('organisations.users.add');
    Route::put('organisations/{organisation}/users/{userId}', [OrganisationController::class, 'updateUserRole'])
        ->name('organisations.users.update-role');
    Route::delete('organisations/{organisation}/users/{userId}', [OrganisationController::class, 'removeUser'])
        ->name('organisations.users.remove');
    Route::post('organisations/{organisation}/transfer-ownership', [OrganisationController::class, 'transferOwnership'])
        ->name('organisations.transfer-ownership');

    // Organisation related resources
    Route::get('organisations/{organisation}/teams', [OrganisationController::class, 'teams'])
        ->name('organisations.teams.index');
    Route::get('organisations/{organisation}/projects', [OrganisationController::class, 'projects'])
        ->name('organisations.projects.index');

    // Team routes
    Route::apiResource('teams', TeamController::class);
    Route::post('teams/{id}/restore', [TeamController::class, 'restore'])->name('teams.restore');

    // Cross-organization endpoints
    Route::prefix('admin')->group(function () {
        Route::get('teams', [TeamController::class, 'indexAll']);
        Route::get('teams/{team}', [TeamController::class, 'showAll'])->name('admin.teams.show');
    });

    // Team members management
    Route::get('teams/{team}/members', [TeamController::class, 'members'])->name('teams.members');
    Route::post('teams/{team}/members', [TeamController::class, 'addMembers'])->name('teams.addMembers');
    Route::delete('teams/{team}/members', [TeamController::class, 'removeMembers'])->name('teams.removeMembers');
    Route::post('teams/{team}/change-lead', [TeamController::class, 'changeTeamLead'])->name('teams.changeTeamLead');

    // Team related resources
    Route::get('teams/{team}/projects', [TeamController::class, 'projects'])->name('teams.projects');
    Route::get('teams/{team}/tasks', [TeamController::class, 'tasks'])->name('teams.tasks');


    // Project routes
    Route::prefix('projects')->group(function () {
        Route::get('/', [ProjectController::class, 'index'])->name('projects.index');
        Route::post('/', [ProjectController::class, 'store'])->name('projects.store');
        Route::get('/{project}', [ProjectController::class, 'show'])->name('projects.show');
        Route::patch('/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::delete('/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
        Route::post('/{project}/restore', [ProjectController::class, 'restore'])->name('projects.restore');

        // Project user management
        Route::post('/{project}/users', [ProjectController::class, 'attachUsers'])->name('projects.users.attach');
        Route::delete('/{project}/users/{user}', [ProjectController::class, 'detachUser'])->name('projects.users.detach');
        Route::get('/{project}/users', [ProjectController::class, 'users'])->name('projects.users.index');

        // Project boards
        Route::get('/{project}/boards', [ProjectController::class, 'boards'])->name('projects.boards.index');

        // Project tasks
        Route::get('/{project}/tasks', [ProjectController::class, 'tasks'])->name('projects.tasks.index');

        // Project statistics
        Route::get('/{project}/statistics', [ProjectController::class, 'statistics'])->name('projects.statistics');
        Route::get('/{project}/tags', [TagController::class, 'forProject'])->name('projects.tags.index');
        Route::post('/{project}/tags/batch', [TagController::class, 'batchCreate'])->name('projects.tags.batch');

    });

    // Board routes
    Route::apiResource('boards', BoardController::class);
    Route::prefix('boards')->group(function () {
        // Board actions
        Route::post('/{board}/duplicate', [BoardController::class, 'duplicate'])->name('boards.duplicate');
        Route::get('/{board}/columns', [BoardController::class,'columns'])->name('boards.columns.index');
        Route::get('/{board}/tasks', [BoardController::class,'tasks'])->name('boards.tasks.index');
        Route::get('/{board}/statistics', [BoardController::class,'statistics']);
    });

    // Board Templates
    Route::post('board-templates/{id}/duplicate', [BoardTemplateController::class,'duplicate']);
    Route::post('board-templates/{id}/toggle-active', [BoardTemplateController::class,'toggleActive']);
    Route::get('board-templates/system', [BoardTemplateController::class,'systemTemplates']);
    Route::apiResource('board-templates', BoardTemplateController::class);

    Route::apiResource('board-types', BoardTypeController::class);

    Route::apiResource('attachments', AttachmentController::class);


    Route::apiResource('change-types', ChangeTypeController::class);

    //Task comments
    Route::get('tasks/{task}/comments', [CommentController::class, 'index']);
    Route::post('tasks/{task}/comments', [CommentController::class, 'store']);


    // Additional task endpoints
    Route::patch('tasks/{task}/change-status', [TaskController::class, 'changeStatus']);
    Route::patch('tasks/{task}/assign', [TaskController::class, 'assignTask']);

    Route::post('tasks/{task}/tags', [TaskController::class, 'assignTags'])->name('tasks.tags.assign');
    Route::delete('tasks/{task}/tags/{tag}', [TaskController::class, 'removeTag'])->name('tasks.tags.remove');

    Route::apiResource('tasks', TaskController::class);

    Route::post('task-types/find-by-name', [TaskTypeController::class, 'findByName']);
    Route::post('task-types/clear-cache', [TaskTypeController::class, 'clearCache']);
    Route::apiResource('task-types', TaskTypeController::class);


    // Comments routes
// Individual comment actions
    Route::get('comments/{comment}', [CommentController::class, 'show'])->name('comments.show');
    Route::patch('comments/{comment}', [CommentController::class, 'update']);
    Route::delete('comments/{comment}', [CommentController::class, 'destroy']);

    Route::post('comments/{id}/restore', [CommentController::class, 'restore'])->name('comments.restore');
    Route::get('user/comments', [CommentController::class, 'getUserComments'])->name('user.comments');

    // Attachment routes
    Route::get('attachments/by-task/{task}', [AttachmentController::class, 'getByTask']);
    Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');
    Route::get('/attachments/{attachment}/file', [AttachmentController::class, 'downloadFile'])
        ->name('attachments.download.file')
        ->middleware('signed');
    Route::post('attachments/{attachment}/restore', [AttachmentController::class, 'restore'])
        ->name('attachments.restore');
    Route::apiResource('attachments', AttachmentController::class)->except(['index']);

    // Status routes
    //TODO: add organisation scope fo better flexibility
    Route::post('statuses/find-by-name', [StatusController::class, 'findByName']);
    Route::get('statuses/default', [StatusController::class, 'getDefault']);
    Route::post('statuses/reorder', [StatusController::class, 'reorder'])->name('statuses.reorder');
    Route::post('statuses/clear-cache', [StatusController::class, 'clearCache']);
    Route::apiResource('statuses', StatusController::class)->except(['store','update','destroy']);

    // ChangeType routes
    //TODO: add organisation scope fo better flexibility

    Route::post('change-types/find-by-name', [ChangeTypeController::class, 'findByName'])
        ->name('change-types.find-by-name');
    Route::post('change-types/sync-task-histories', [ChangeTypeController::class, 'syncTaskHistories'])
        ->name('change-types.sync-task-histories');
    Route::post('change-types/clear-cache', [ChangeTypeController::class, 'clearCache'])
        ->name('change-types.clear-cache');
    Route::apiResource('change-types', ChangeTypeController::class)->except(['store','update','destroy']);

    // Priority routes
    //TODO: add organisation scope fo better flexibility
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
    Route::apiResource('priorities', PriorityController::class)->except(['store', 'update', 'destroy']);

    // Status Transition routes
    Route::get('status-transitions/from-status/{status}', [StatusTransitionController::class, 'getFromStatus'])
        ->name('status-transitions.from-status');
    Route::post('status-transitions/is-valid', [StatusTransitionController::class, 'isValidTransition'])
        ->name('status-transitions.is-valid');
    Route::post('status-transitions/clear-cache', [StatusTransitionController::class, 'clearCache'])
        ->name('status-transitions.clear-cache');
    Route::apiResource('status-transitions', StatusTransitionController::class)->except(
        ['store', 'update', 'destroy']
    );


    // Tag routes
    Route::post('tags/{id}/restore', [TagController::class, 'restore'])->name('tags.restore');
    // Get all tags available for import across organizations
    Route::get('tags/import/available', [TagController::class, 'getAvailableTagsForImport'])
        ->name('tags.import.available');
    // Batch import multiple tags from any project/org to a target project
    Route::post('tags/projects/{project}/import/batch', [TagController::class, 'batchImportTags'])
        ->name('tags.import.batch');
    Route::apiResource('tags', TagController::class);



    // Nested resource routes (sprints under boards)
    Route::prefix('boards/{board}')->group(function () {
        Route::get('/sprints', [SprintController::class, 'boardSprints'])->name('boards.sprints.index');
        Route::post('/sprints', [SprintController::class, 'storeForBoard'])->name('boards.sprints.store');
    });

    // Keep the non-nested routes for backward compatibility and convenience

    // Sprint routes
    Route::prefix('sprints')->group(function () {
        Route::post('/{id}/restore', [SprintController::class, 'restore'])->name('sprints.restore');

        // Sprint actions
        Route::post('/{sprint}/start', [SprintController::class, 'start'])->name('sprints.start');
        Route::post('/{sprint}/complete', [SprintController::class, 'complete'])->name('sprints.complete');

        // Sprint tasks
        Route::get('/{sprint}/tasks', [SprintController::class, 'tasks'])->name('sprints.tasks.index');
        Route::post('/{sprint}/tasks', [SprintController::class, 'addTasks'])->name('sprints.tasks.add');
        Route::delete('/{sprint}/tasks', [SprintController::class, 'removeTasks'])->name('sprints.tasks.remove');

        // Sprint statistics
        Route::get('/{sprint}/statistics', [SprintController::class, 'statistics'])->name('sprints.statistics');
        // Removed duplicate route that was causing conflicts
    });
    Route::apiResource('sprints', SprintController::class)->except(['index']);
    // Your other API endpoints go here...



});

