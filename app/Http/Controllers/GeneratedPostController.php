<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePostStatusRequest;
use App\Http\Resources\GeneratedPostResource;
use App\Models\GeneratedPost;
use Illuminate\Http\JsonResponse;

/**
 * @group Generated Posts
 *
 * List generated posts and manage their editorial lifecycle
 * (draft, posted, archived).
 */
class GeneratedPostController extends Controller
{
    /**
     * List generated posts
     *
     * Returns every post generated from the authenticated user's raw contents,
     * newest first. Eager loads the parent raw content to avoid N+1 queries.
     */
    public function index(): JsonResponse
    {
        $posts = GeneratedPost::with('rawContent')
            ->whereHas('rawContent', fn ($q) => $q->where('user_id', auth()->id()))
            ->latest()
            ->get();

        return response()->json([
            'data' => GeneratedPostResource::collection($posts),
        ], 200);
    }

    /**
     * Show a generated post
     */
    public function show(GeneratedPost $generatedPost): JsonResponse
    {
        if (! $this->owns($generatedPost)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json([
            'data' => new GeneratedPostResource($generatedPost),
        ], 200);
    }

    /**
     * Update post status
     *
     * Moves a generated post through the editorial calendar.
     *
     * @bodyParam status string required One of: draft, posted, archived. Example: posted
     */
    public function updateStatus(UpdatePostStatusRequest $request, GeneratedPost $generatedPost): JsonResponse
    {
        if (! $this->owns($generatedPost)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $generatedPost->update(['status' => $request->validated('status')]);

        return response()->json([
            'message' => 'Post status updated successfully.',
            'data'    => new GeneratedPostResource($generatedPost),
        ], 200);
    }

    /**
     * Determine if the current user owns the post (via its raw content).
     */
    protected function owns(GeneratedPost $post): bool
    {
        return $post->rawContent()->where('user_id', auth()->id())->exists();
    }
}
