<?php

namespace App\Http\Controllers;

use App\Http\Requests\BlueprintRequest;
use App\Http\Resources\BlueprintResource;
use App\Models\Blueprint;
use Illuminate\Http\JsonResponse;

/**
 * @group Blueprints (Campaigns)
 *
 * Manage the strict style rules (tone, hashtag limits, character limits,
 * audience) that drive the AI generation for a user's content.
 */
class BlueprintController extends Controller
{
    /**
     * List blueprints
     *
     * Returns the authenticated user's blueprints, each with the number of
     * posts generated from it (via a single withCount query — no N+1).
     */
    public function index(): JsonResponse
    {
        $blueprints = Blueprint::where('user_id', auth()->id())
            ->withCount('generatedPosts')
            ->latest()
            ->get();

        return response()->json(['data' => BlueprintResource::collection($blueprints)], 200);
    }

    /**
     * Create a blueprint
     *
     * @bodyParam name string required Example: Tech Community Voice
     * @bodyParam tone string required Example: professionnel mais décontracté
     * @bodyParam max_hashtags integer 0-10. Example: 1
     * @bodyParam max_characters integer 1-280. Example: 280
     * @bodyParam target_audience string Example: développeurs backend
     * @bodyParam style_rules string Example: Pas d'emojis, jamais de superlatifs.
     */
    public function store(BlueprintRequest $request): JsonResponse
    {
        $blueprint = Blueprint::create([
            ...$request->validated(),
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Blueprint created successfully.',
            'data'    => new BlueprintResource($blueprint),
        ], 201);
    }

    /**
     * Show a blueprint
     */
    public function show(Blueprint $blueprint): JsonResponse
    {
        if ($blueprint->user_id !== auth()->id()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $blueprint->loadCount('generatedPosts');

        return response()->json(['data' => new BlueprintResource($blueprint)], 200);
    }

    /**
     * Update a blueprint
     */
    public function update(BlueprintRequest $request, Blueprint $blueprint): JsonResponse
    {
        if ($blueprint->user_id !== auth()->id()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $blueprint->update($request->validated());

        return response()->json([
            'message' => 'Blueprint updated successfully.',
            'data'    => new BlueprintResource($blueprint),
        ], 200);
    }

    /**
     * Delete a blueprint
     */
    public function destroy(Blueprint $blueprint): JsonResponse
    {
        if ($blueprint->user_id !== auth()->id()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $blueprint->delete();

        return response()->json(['message' => 'Blueprint deleted successfully.'], 200);
    }
}
