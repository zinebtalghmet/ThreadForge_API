<?php

namespace App\Http\Controllers;

use App\Http\Requests\CampaignRequest;
use App\Http\Resources\CampaignResource;
use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;


class CampaignController extends Controller
{
    public function index(): JsonResponse
{
    $campaigns = Campaign::where('user_id', auth()->id())->get();
    return response()->json(['data' => CampaignResource::collection($campaigns)], 200);
}

public function store(CampaignRequest $request): JsonResponse
{
    $campaign = Campaign::create([
        ...$request->validated(),
        'user_id' => auth()->id(),
    ]);
    return response()->json([
        'message' => 'Campaign created successfully.',
        'data'    => new CampaignResource($campaign),
    ], 201);
}

public function show(Campaign $campaign): JsonResponse
{
    if ($campaign->user_id !== auth()->id()) {
        return response()->json(['message' => 'Forbidden.'], 403);
    }
    return response()->json(['data' => new CampaignResource($campaign)], 200);
}

public function update(CampaignRequest $request, Campaign $campaign): JsonResponse
{
    if ($campaign->user_id !== auth()->id()) {
        return response()->json(['message' => 'Forbidden.'], 403);
    }
    $campaign->update($request->validated());
    return response()->json([
        'message' => 'Campaign updated successfully.',
        'data'    => new CampaignResource($campaign),
    ], 200);
}

public function destroy(Campaign $campaign): JsonResponse
{
    if ($campaign->user_id !== auth()->id()) {
        return response()->json(['message' => 'Forbidden.'], 403);
    }
    $campaign->delete();
    return response()->json(['message' => 'Campaign deleted successfully.'], 200);
}}