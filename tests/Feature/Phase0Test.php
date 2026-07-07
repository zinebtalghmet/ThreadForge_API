<?php

use App\Jobs\GeneratePostJob;
use App\Models\Blueprint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

// Migre la base de test (threadforge_api_test) et la remet à zéro avant chaque test.
uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// TEST 1 — Login (200 avec bons identifiants, 401 avec mauvais)
// ---------------------------------------------------------------------------

it('returns a token when credentials are correct', function () {
    // Arrange : le factory crée un user avec le mot de passe 'password'.
    $user = User::factory()->create();

    // Act : requête POST avec les BONS identifiants.
    $response = postJson('/api/auth/login', [
        'email'    => $user->email,
        'password' => 'password',
    ]);

    // Assert : 200 + un token présent dans la réponse.
    $response->assertOk()
        ->assertJsonStructure(['token']);
});

it('returns 401 when the password is wrong', function () {
    $user = User::factory()->create();

    postJson('/api/auth/login', [
        'email'    => $user->email,
        'password' => 'mauvais-mot-de-passe',
    ])->assertStatus(401);
});

// ---------------------------------------------------------------------------
// TEST 2 — Route protégée (401 sans token, 200 avec Sanctum::actingAs)
// ---------------------------------------------------------------------------

it('blocks blueprints without a token', function () {
    getJson('/api/blueprints')->assertStatus(401);
});

it('allows blueprints with a valid Sanctum token', function () {
    // Simule un utilisateur authentifié sans passer par le vrai login.
    Sanctum::actingAs(User::factory()->create());

    getJson('/api/blueprints')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

// ---------------------------------------------------------------------------
// TEST 3 — Validation (422 quand un champ obligatoire manque)
// ---------------------------------------------------------------------------

it('rejects a blueprint without the required name field', function () {
    Sanctum::actingAs(User::factory()->create());

    postJson('/api/blueprints', [
        'tone' => 'professionnel',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

// ---------------------------------------------------------------------------
// TEST 4 — Génération asynchrone (202 + Job dispatché, sans appeler l'IA)
// ---------------------------------------------------------------------------

it('accepts raw content, returns 202 and dispatches the job', function () {
    // On intercepte la queue : le Job est enregistré mais PAS exécuté (aucun appel Groq).
    Queue::fake();

    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // Un blueprint qui appartient bien au user (sinon la validation le refuse).
    $blueprint = Blueprint::create([
        'user_id' => $user->id,
        'name'    => 'Tech Voice',
        'tone'    => 'pro',
    ]);

    postJson('/api/content/repurpose', [
        'blueprint_id' => $blueprint->id,
        'body'         => 'Notes de dev assez longues pour passer la validation.',
    ])->assertStatus(202);

    // Le Job de génération a bien été mis en file d'attente.
    Queue::assertPushed(GeneratePostJob::class);
});
