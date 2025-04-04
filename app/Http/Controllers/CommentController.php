<?php

namespace App\Http\Controllers;

use App\Http\Requests\CommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Task;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class CommentController extends Controller
{

    /**
     * Display a listing of the comments for a task.
     *
     * @param Request $request
     * @param Task $task
     * @return AnonymousResourceCollection
     * @throws AuthorizationException
     */
    public function index(Request $request, Task $task): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [Comment::class, $task]);

        $query = $task->comments()->with(['user', 'replies.user'])
            ->whereNull('parent_id'); // Only top-level comments

        // Allow sorting by latest or oldest
        if ($request->has('sort')) {
            $direction = $request->get('sort') === 'oldest' ? 'asc' : 'desc';
            $query->orderBy('created_at', $direction);
        } else {
            $query->latest(); // Default to newest first
        }

        $comments = $query->paginate($request->get('per_page', 15));

        return CommentResource::collection($comments);
    }

    /**
     * Store a newly created comment.
     *
     * @param CommentRequest $request
     * @param Task $task
     * @return CommentResource
     */
    public function store(CommentRequest $request, Task $task): CommentResource
    {
        $this->authorize('create', [Comment::class, $task]);

        $comment = new Comment($request->validated());
        $comment->user_id = $request->user()->id;
        $comment->task_id = $task->id;

        $comment->save();

        // If this is a reply, update the parent's updated_at timestamp
        if ($comment->parent_id) {
            $parent = Comment::find($comment->parent_id);
            $parent->touch();
        }

        // Touch the task to update its timestamp
        $task->touch();

        return new CommentResource($comment->load(['user']));
    }

    /**
     * Display the specified comment with its replies.
     *
     * @param Comment $comment
     * @return CommentResource
     * @throws AuthorizationException
     */
    public function show(Comment $comment): CommentResource
    {
        $this->authorize('view', $comment);

        // Load the comment with user and nested replies
        $comment->load(['user', 'replies' => function($query) {
            $query->with('user')->latest();
        }]);

        return new CommentResource($comment);
    }

    /**
     * Update the specified comment.
     *
     * @param CommentRequest $request
     * @param Comment $comment
     * @return CommentResource
     * @throws AuthorizationException
     */
    public function update(CommentRequest $request, Comment $comment): CommentResource
    {
        $this->authorize('update', $comment);

        $comment->update($request->validated());

        // Touch the task to update its timestamp
        $comment->task->touch();

        return new CommentResource($comment->load(['user']));
    }

    /**
     * Remove the specified comment.
     *
     * @param Comment $comment
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        $this->authorize('delete', $comment);

        try {
            // If the comment has replies and user is not an admin
            if ($comment->hasReplies()) {
                // Encrypt and save the original content
                $metadata = [
                    'original_content' => $comment->content,
                    'deleted_by' => $request->user()->name,
                    'deleted_at' => now()->toIso8601String(),
                    'deletion_type' => 'content_only'
                ];

                \Log::debug('metadata is: ' . json_encode($metadata));

                // Update the comment - the metadata will be auto-encrypted by the model
                $comment->update([
                    'metadata' => $metadata,
                    'content' => '[Comment deleted by ' . $request->user()->name . ']'
                ]);

                return response()->json([
                    'message' => 'Comment content removed but structure preserved due to existing replies.',
                    'comment_id' => $comment->id
                ]);
            }

            // For regular deletion, still store encrypted metadata
            // but perform a soft delete of the entire comment
            $metadata = [
                'original_content' => $comment->content,
                'deleted_by' => $request->user()->name,
                'deleted_at' => now()->toIso8601String(),
                'deletion_type' => 'full_delete'
            ];

            $comment->metadata = $metadata;
            $comment->save();

            // Soft delete the comment
            $comment->delete();

            // Touch the task to update its timestamp
            $comment->task->touch();

            return response()->json([
                'message' => 'Comment deleted successfully.',
                'comment_id' => $comment->id
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            \Log::error('Error deleting comment: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to delete comment: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Restore a soft-deleted comment.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function restore(Request $request, int $id): JsonResponse
    {
        try {
            // Find the comment even if soft-deleted
            $comment = Comment::withTrashed()->findOrFail($id);
            $this->authorize('restore', $comment);

            // Get the metadata (automatically decrypted by the model)
            $metadata = $comment->metadata;

            // Check if we have valid metadata
            if (empty($metadata) || !isset($metadata['deletion_type'])) {
                return response()->json([
                    'message' => 'Comment cannot be restored due to missing metadata.'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Handle different types of deletion
            if ($metadata['deletion_type'] === 'full_delete' && $comment->trashed()) {
                // Restore a fully soft-deleted comment
                $comment->restore();

                // Optional: restore original content if it was modified before deletion
                if (isset($metadata['original_content'])) {
                    $comment->content = $metadata['original_content'];
                    $comment->save();
                }

                $message = 'Comment fully restored successfully.';
            }
            elseif ($metadata['deletion_type'] === 'content_only' && isset($metadata['original_content'])) {
                // Restore just the content for comments with replies
                $comment->update([
                    'content' => $metadata['original_content'],
                ]);
                $message = 'Comment content restored successfully.';
            }
            else {
                return response()->json([
                    'message' => 'Unknown deletion type. Cannot restore comment.'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Clear the metadata after successful restoration
            $comment->metadata = null;
            $comment->save();

            // Touch the task to update its timestamp
            $comment->task->touch();

            return response()->json([
                'message' => $message,
                'comment' => new CommentResource($comment->fresh())
            ]);
        } catch (\Exception $e) {
            \Log::error('Error restoring comment: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to restore comment: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get all comments by the current user.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function getUserComments(Request $request): AnonymousResourceCollection
    {
        $query = Comment::where('user_id', $request->user()->id)
            ->with(['task', 'user']);

        if ($request->has('sort')) {
            $direction = $request->get('sort') === 'oldest' ? 'asc' : 'desc';
            $query->orderBy('created_at', $direction);
        } else {
            $query->latest(); // Default to newest first
        }

        $comments = $query->paginate($request->get('per_page', 15));

        return CommentResource::collection($comments);
    }
}
