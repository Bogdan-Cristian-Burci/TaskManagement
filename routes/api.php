<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\BoardColumnController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\BoardTypeController;
use App\Http\Controllers\ChangeTypeController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\PriorityController;
use App\Http\Controllers\RolePermissionController;
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

    // Your other API endpoints go here...
});
