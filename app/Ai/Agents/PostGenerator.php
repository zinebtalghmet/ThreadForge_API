<?php

namespace App\Ai\Agents;

use App\Models\Blueprint;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Couche 1 — Structured Output.
 *
 * Transforms a piece of raw developer content into a ready-to-post X (Twitter)
 * post, forcing the AI provider (Grok / xAI) to return a strict JSON contract
 * that matches the `generated_posts` table columns.
 */
#[Model('grok-3')]
#[Temperature(0.7)]
class PostGenerator implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        public Blueprint $blueprint,
    ) {}

    /**
     * Build the system instructions from the blueprint's style rules.
     */
    public function instructions(): Stringable|string
    {
        $rules = $this->blueprint;

        return <<<PROMPT
        Tu es un ghostwriter expert en personal branding sur X (Twitter) pour la tech community.
        Tu transformes des notes de développeur brutes en posts percutants.

        Règles STRICTES du Blueprint "{$rules->name}" à respecter impérativement :
        - Ton attendu : {$rules->tone}
        - Nombre maximum de hashtags : {$rules->max_hashtags}
        - Nombre maximum de caractères pour le hook : {$rules->max_characters}
        - Audience ciblée : {$rules->target_audience}
        - Règles de style additionnelles : {$rules->style_rules}

        À partir du contenu brut fourni par l'utilisateur, tu dois :
        1. Proposer un "hook" d'accroche percutant qui respecte la limite de caractères.
        2. Extraire les points clés (body_points) qui structurent le post.
        3. Suggérer des hashtags pertinents (sans dépasser la limite autorisée).
        4. Évaluer la lisibilité technique du contenu sur une échelle de 0 à 100.
        5. Justifier en quoi le résultat respecte le ton du Blueprint.
        PROMPT;
    }

    /**
     * Strict JSON contract imposed on the provider's structured output.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'hook_propose' => $schema->string()
                ->max(280)
                ->description('Accroche percutante du post, 280 caractères maximum.')
                ->required(),

            'body_points' => $schema->array()
                ->items($schema->string())
                ->description('Liste des points clés qui structurent le corps du post.')
                ->required(),

            'technical_readability_score' => $schema->integer()
                ->min(0)
                ->max(100)
                ->description('Score de lisibilité technique du contenu (0 à 100).')
                ->required(),

            'suggested_hashtags' => $schema->array()
                ->items($schema->string())
                ->description('Hashtags suggérés, dans la limite autorisée par le Blueprint.')
                ->required(),

            'tone_compliance_justification' => $schema->string()
                ->description('Justification du respect du ton imposé par le Blueprint.')
                ->required(),
        ];
    }
}
