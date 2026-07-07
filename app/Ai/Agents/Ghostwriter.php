<?php

namespace App\Ai\Agents;

use App\Ai\Tools\GetCampaignRules;
use App\Ai\Tools\GetPostHistory;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Couche 2 — Agent conversationnel (Ghostwriter Assistant).
 *
 * A chat agent that reworks generated posts on demand. It is backed by real
 * PHP tools (getCampaignRules / getPostHistory) so it reads facts from the
 * database instead of hallucinating, and it uses the SDK conversation tables
 * (RemembersConversations) to keep context across turns.
 */
#[Model('grok-3')]
#[MaxSteps(8)]
class Ghostwriter implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function instructions(): Stringable|string
    {
        return <<<PROMPT
        Tu es "Ghostwriter", un assistant de rédaction pour un développeur qui construit
        son personal branding sur X (Twitter).

        Règles impératives :
        - Tu ne dois JAMAIS inventer les règles d'un Blueprint ni le contenu d'un post.
        - Pour toute question portant sur les contraintes de style d'une campagne,
          tu DOIS appeler l'outil getCampaignRules(campaign_id).
        - Pour toute question portant sur le contenu d'un post existant (hook, points,
          versions précédentes), tu DOIS appeler l'outil getPostHistory(post_id).
        - Appuie toujours tes réponses sur les données réelles renvoyées par les outils.
        - Réponds de manière concise et actionnable, dans la langue de l'utilisateur.
        PROMPT;
    }

    /**
     * The real, hand-written PHP tools the agent can call.
     *
     * @return array<int, \Laravel\Ai\Contracts\Tool>
     */
    public function tools(): iterable
    {
        return [
            new GetCampaignRules,
            new GetPostHistory,
        ];
    }
}
