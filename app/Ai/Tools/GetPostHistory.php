<?php

namespace App\Ai\Tools;

use App\Models\GeneratedPost;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Real PHP tool — getPostHistory(int $postId).
 *
 * Lets the Ghostwriter agent read the actual generated post and its originating
 * raw content from the database instead of inventing previous versions.
 */
class GetPostHistory implements Tool
{
    public function description(): Stringable|string
    {
        return 'Extrait depuis la base de données le post généré (hook, points clés, hashtags, '
            .'score de lisibilité) ainsi que le contenu brut d\'origine. '
            .'À utiliser dès que la question porte sur le contenu réel d\'un post existant.';
    }

    public function handle(Request $request): Stringable|string
    {
        $post = GeneratedPost::with('rawContent.blueprint')->find($request['post_id']);

        if (! $post) {
            return "Aucun post généré trouvé pour l'identifiant {$request['post_id']}.";
        }

        return json_encode([
            'post_id'                       => $post->id,
            'status'                        => $post->status->value,
            'hook_propose'                  => $post->hook_propose,
            'body_points'                   => $post->body_points,
            'suggested_hashtags'            => $post->suggested_hashtags,
            'technical_readability_score'   => $post->technical_readability_score,
            'tone_compliance_justification' => $post->tone_compliance_justification,
            'original_raw_content'          => $post->rawContent?->body,
            'blueprint_id'                  => $post->rawContent?->blueprint_id,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'post_id' => $schema->integer()
                ->description('Identifiant du post généré dont il faut lire le contenu et l\'historique.')
                ->required(),
        ];
    }
}
