<?php

namespace App\Http\Controllers;

use App\Http\Requests\RawContentRequest;
use App\Http\Resources\RawContentResource;
use App\Jobs\GeneratePostJob;
use App\Models\RawContent;
use App\RawContentStatus;
use Illuminate\Http\JsonResponse;

/**
 * @group Content Generation
 *
 * Submit raw developer notes and monitor their asynchronous transformation
 * into structured X (Twitter) posts.
 */
class RawContentController extends Controller
{
    /**
     * Submit raw content (async repurpose)
     *
     * Accepts a piece of raw content, persists it, and dispatches a background
     * Job to generate a post via the AI gateway. Returns immediately with 202.
     *
     * @bodyParam blueprint_id integer required The blueprint whose style rules apply. Example: 1
     * @bodyParam body string required The raw developer notes / markdown to transform. Example: Today I refactored our queue workers and cut memory usage by 40%.
     *
     * @response 202 {"message": "Content submitted. Generation in progress.", "data": {"id": 1, "blueprint_id": 1, "status": "pending"}}
     */
    public function repurpose(RawContentRequest $request): JsonResponse
    {
        $rawContent = RawContent::create([
            'user_id'      => $request->user()->id,
            'blueprint_id' => $request->validated('blueprint_id'),
            'body'         => $request->validated('body'),
            'status'       => RawContentStatus::Pending,
        ]);

        GeneratePostJob::dispatch($rawContent);

        return response()->json([
            'message' => 'Content submitted. Generation in progress.',
            'data'    => new RawContentResource($rawContent),
        ], 202);
    }

    /**
     * List raw contents
     *
     * Returns the authenticated user's raw contents with their generated post
     * (if any), newest first. Uses eager loading to avoid N+1 queries.
     */
    public function index(): JsonResponse
    {
        $contents = RawContent::with('generatedPost')
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        return response()->json([
            'data' => RawContentResource::collection($contents),
        ], 200);
    }

    /**
     * Show a raw content
     *
     * Returns a single raw content with its generation status and, once ready,
     * the generated post.
     *
     * @response 200 {"data": {"id": 1, "status": "done", "generated_post": {"hook_propose": "..."}}}
     */
    public function show(RawContent $rawContent): JsonResponse
    {
        if ($rawContent->user_id !== auth()->id()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $rawContent->load('generatedPost');

        return response()->json([
            'data' => new RawContentResource($rawContent),
        ], 200);
    }
}
