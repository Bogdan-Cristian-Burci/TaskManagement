<?php

namespace App\Http\Controllers;

use App\Http\Requests\CommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Task;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    use AuthorizesRequests;
    public function __construct()
    {
        $this->authorizeResource(Comment::class, 'comment');
    }

    public function index(Request $request, Task $task)
    {
        $query = $task->comments()->with(['user', 'replies.user'])
            ->whereNull('parent_id'); // Only top-level comments

        $comments = $query->paginate($request->get('per_page', 15));

        return CommentResource::collection($comments);
    }

    public function store(CommentRequest $request, Task $task)
    {
        $comment = new Comment($request->validated());
        $comment->user_id = auth()->id();
        $comment->task_id = $task->id;

        $comment->save();

        return new CommentResource($comment->load(['user']));
    }

    public function show(Comment $comment)
    {
        $this->authorize('view', $comment);
        return new CommentResource($comment->load(['user', 'replies.user']));
    }

    public function update(CommentRequest $request, Comment $comment)
    {
        $comment->update($request->validated());

        return new CommentResource($comment->load(['user']));
    }

    public function destroy(Comment $comment)
    {
        $this->authorize('delete', $comment);
        $comment->delete();

        return response()->noContent();
    }
}
