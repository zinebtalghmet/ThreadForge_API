<?php

namespace App\Ai\Tools;

use App\Models\Blueprint;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Real PHP tool — getCampaignRules(int $campaignId).
 *
 * Lets the Ghostwriter agent read the actual style constraints of a Blueprint
 * (campaign) from the database instead of hallucinating them.
 */
class GetCampaignRules implements Tool
{
    public function description(): Stringable|string
    {
        return 'Récupère les règles de style réelles (ton, hashtags max, caractères max, '
            .'audience, règles de style) d\'un Blueprint (campagne) depuis la base de données. '
            .'À utiliser dès que la question porte sur les contraintes ou le ton du Blueprint.';
    }

    public function handle(Request $request): Stringable|string
    {
        $blueprint = Blueprint::find($request['campaign_id']);

        if (! $blueprint) {
            return "Aucun Blueprint trouvé pour l'identifiant {$request['campaign_id']}.";
        }

        return json_encode([
            'id'              => $blueprint->id,
            'name'            => $blueprint->name,
            'tone'            => $blueprint->tone,
            'max_hashtags'    => $blueprint->max_hashtags,
            'max_characters'  => $blueprint->max_characters,
            'target_audience' => $blueprint->target_audience,
            'style_rules'     => $blueprint->style_rules,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'campaign_id' => $schema->integer()
                ->description('Identifiant du Blueprint (campagne) dont il faut lire les règles.')
                ->required(),
        ];
    }
}
