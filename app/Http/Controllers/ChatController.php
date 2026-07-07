<?php

namespace App\Http\Controllers;

use App\Ai\Agents\Ghostwriter;
use App\Http\Requests\ChatRequest;
use App\Models\GeneratedPost;
use Illuminate\Http\JsonResponse;

/**
 * @group Ghostwriter Assistant
 *
 * A contextual chat agent (Couche 2) that reworks a generated post. It uses
 * real PHP tools to read the database and remembers the conversation across
 * turns via the SDK persistence tables.
 */
class ChatController extends Controller
{
    /**
     * Chat about a generated post
     *
     * Sends a natural-language message to the Ghostwriter agent for a given post.
     * Omit conversation_id to start a new conversation; pass the returned
     * conversation_id back on the next request to keep context.
     *
     * @urlParam generatedPost integer required The post to discuss. Example: 1
     * @bodyParam message string required The user's question or instruction. Example: Donne-moi 3 variantes plus agressives pour le hook.
     * @bodyParam conversation_id string The conversation UUID to continue. Example: 9b1c...
     *
     * @response 200 {"reply": "Voici 3 variantes...", "conversation_id": "9b1c..."}
     */
    public function chat(ChatRequest $request, GeneratedPost $generatedPost): JsonResponse
    {
        if (! $generatedPost->rawContent()->where('user_id', $request->user()->id)->exists()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $user = $request->user();
        $conversationId = $request->validated('conversation_id');

        $agent = $conversationId
            ? (new Ghostwriter)->continue($conversationId, as: $user)
            : (new Ghostwriter)->forUser($user);

        // Give the agent the concrete IDs so its tools query the right rows.
        $blueprintId = $generatedPost->rawContent->blueprint_id;
        $message = "[Contexte : post_id={$generatedPost->id}, campaign_id={$blueprintId}]\n"
            .$request->validated('message');

        $response = $agent->prompt($message);

        return response()->json([
            'reply'           => $response->text,
            'conversation_id' => $agent->currentConversation(),
        ], 200);
    }
}
