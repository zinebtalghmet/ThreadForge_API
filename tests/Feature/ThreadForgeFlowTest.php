<?php

use App\Ai\Agents\Ghostwriter;
use App\Ai\Agents\PostGenerator;
use App\Jobs\GeneratePostJob;
use App\Models\Blueprint;
use App\Models\GeneratedPost;
use App\Models\RawContent;
use App\Models\User;
use App\RawContentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

/**
 * Register a user and return a Bearer-authenticated headers array.
 */
function authHeaders(): array
{
    $res = postJson('/api/auth/register', [
        'name' => 'Dev',
        'email' => 'dev'.uniqid().'@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated();

    return ['Authorization' => 'Bearer '.$res->json('token')];
}

// ---------------------------------------------------------------------------
// US1 — Auth
// ---------------------------------------------------------------------------

it('registers a user and returns a token without leaking the password', function () {
    $res = postJson('/api/auth/register', [
        'name' => 'Zineb',
        'email' => 'zineb@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $res->assertCreated()
        ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);

    expect($res->json('user'))->not->toHaveKey('password');
});

it('rejects login with invalid credentials', function () {
    User::factory()->create(['email' => 'a@b.com', 'password' => 'password123']);

    postJson('/api/auth/login', ['email' => 'a@b.com', 'password' => 'wrong'])
        ->assertStatus(401);
});

it('blocks protected routes without a token (401)', function () {
    getJson('/api/blueprints')->assertStatus(401);
});

// ---------------------------------------------------------------------------
// US2 / US3 — Blueprints
// ---------------------------------------------------------------------------

it('creates a blueprint and validates input (422)', function () {
    $headers = authHeaders();

    postJson('/api/blueprints', [
        'name' => 'Tech Voice',
        'tone' => 'pro mais décontracté',
        'max_hashtags' => 1,
        'max_characters' => 280,
    ], $headers)->assertCreated()->assertJsonPath('data.name', 'Tech Voice');

    postJson('/api/blueprints', ['tone' => 'x'], $headers)
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

it('lists blueprints with generated_posts_count', function () {
    $headers = authHeaders();
    postJson('/api/blueprints', ['name' => 'B', 'tone' => 't'], $headers)->assertCreated();

    getJson('/api/blueprints', $headers)
        ->assertOk()
        ->assertJsonPath('data.0.generated_posts_count', 0);
});

// ---------------------------------------------------------------------------
// US4 — Async submission returns 202 without running the AI inline
// ---------------------------------------------------------------------------

it('accepts raw content, returns 202 and queues the generation job', function () {
    Queue::fake();
    $headers = authHeaders();
    $blueprint = Blueprint::first() ?? tap(new Blueprint([
        'user_id' => User::first()->id, 'name' => 'B', 'tone' => 't',
    ]))->save();

    postJson('/api/content/repurpose', [
        'blueprint_id' => $blueprint->id,
        'body' => 'Today I refactored the queue workers and cut memory by 40%.',
    ], $headers)->assertStatus(202)->assertJsonPath('data.status', 'pending');

    Queue::assertPushed(GeneratePostJob::class);
});

it('rejects repurpose for a blueprint the user does not own (422)', function () {
    $headers = authHeaders();
    $otherBlueprint = Blueprint::create([
        'user_id' => User::factory()->create()->id, 'name' => 'X', 'tone' => 't',
    ]);

    postJson('/api/content/repurpose', [
        'blueprint_id' => $otherBlueprint->id,
        'body' => 'some raw content here that is long enough',
    ], $headers)->assertStatus(422)->assertJsonValidationErrors('blueprint_id');
});

// ---------------------------------------------------------------------------
// US5 — Job forces a strict structured contract and persists typed arrays
// ---------------------------------------------------------------------------

it('generates a typed post from raw content via the structured-output agent', function () {
    PostGenerator::fake([[
        'hook_propose' => 'Le hook percutant',
        'body_points' => ['point 1', 'point 2'],
        'technical_readability_score' => 87,
        'suggested_hashtags' => ['#Laravel'],
        'tone_compliance_justification' => 'Ton respecté.',
    ]]);

    $user = User::factory()->create();
    $blueprint = Blueprint::create(['user_id' => $user->id, 'name' => 'B', 'tone' => 't']);
    $raw = RawContent::create([
        'user_id' => $user->id,
        'blueprint_id' => $blueprint->id,
        'body' => 'raw dev notes long enough to pass validation',
    ]);

    (new GeneratePostJob($raw))->handle();

    $post = GeneratedPost::first();
    expect($post)->not->toBeNull()
        ->and($post->body_points)->toBe(['point 1', 'point 2'])       // native array cast
        ->and($post->suggested_hashtags)->toBe(['#Laravel'])
        ->and($post->technical_readability_score)->toBe(87);

    expect($raw->fresh()->status)->toBe(RawContentStatus::Done);
});

// ---------------------------------------------------------------------------
// US6 — Lifecycle (status transitions)
// ---------------------------------------------------------------------------

it('lists posts and updates their status, validating the enum (422)', function () {
    $headers = authHeaders();
    $user = User::first();
    $blueprint = Blueprint::create(['user_id' => $user->id, 'name' => 'B', 'tone' => 't']);
    $raw = RawContent::create(['user_id' => $user->id, 'blueprint_id' => $blueprint->id, 'body' => 'xxxxxxxxxx']);
    $post = GeneratedPost::create([
        'raw_content_id' => $raw->id,
        'hook_propose' => 'h', 'body_points' => ['a'],
        'technical_readability_score' => 50, 'suggested_hashtags' => ['#x'],
        'tone_compliance_justification' => 'ok',
    ]);

    getJson('/api/posts', $headers)->assertOk()->assertJsonCount(1, 'data');

    patchJson("/api/posts/{$post->id}/status", ['status' => 'posted'], $headers)
        ->assertOk()->assertJsonPath('data.status', 'posted');

    patchJson("/api/posts/{$post->id}/status", ['status' => 'invalid'], $headers)
        ->assertStatus(422)->assertJsonValidationErrors('status');
});

// ---------------------------------------------------------------------------
// US7 — Ghostwriter chat
// ---------------------------------------------------------------------------

it('answers a contextual chat message about a post', function () {
    Ghostwriter::fake(['Voici 3 variantes plus agressives pour le hook...']);

    $headers = authHeaders();
    $user = User::first();
    $blueprint = Blueprint::create(['user_id' => $user->id, 'name' => 'B', 'tone' => 't']);
    $raw = RawContent::create(['user_id' => $user->id, 'blueprint_id' => $blueprint->id, 'body' => 'xxxxxxxxxx']);
    $post = GeneratedPost::create([
        'raw_content_id' => $raw->id,
        'hook_propose' => 'h', 'body_points' => ['a'],
        'technical_readability_score' => 50, 'suggested_hashtags' => ['#x'],
        'tone_compliance_justification' => 'ok',
    ]);

    postJson("/api/posts/{$post->id}/chat", [
        'message' => 'Donne-moi 3 variantes plus agressives pour le hook.',
    ], $headers)->assertOk()->assertJsonStructure(['reply', 'conversation_id']);
});
