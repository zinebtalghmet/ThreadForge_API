<?php

namespace App\Jobs;

use App\Ai\Agents\PostGenerator;
use App\Models\GeneratedPost;
use App\Models\RawContent;
use App\RawContentStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeneratePostJob implements ShouldQueue
{
    use Queueable;

    /**
     * Number of times the job may be attempted before it is marked as failed.
     */
    public int $tries = 3;

    public function __construct(
        public RawContent $rawContent,
    ) {}

    /**
     * Run the asynchronous generation through the laravel/ai Structured Output agent.
     */
    public function handle(): void
    {
        $this->rawContent->update(['status' => RawContentStatus::Processing]);

        $blueprint = $this->rawContent->blueprint;

        // Couche 1 — force a strict, typed JSON contract out of Grok (xAI).
        $response = (new PostGenerator($blueprint))->prompt($this->rawContent->body);

        // Defensive contract validation before persisting (the SDK already
        // enforces the schema, but the spec requires an explicit check).
        $data = $this->validatedPayload($response);

        GeneratedPost::create([
            'raw_content_id'                => $this->rawContent->id,
            'hook_propose'                  => mb_substr($data['hook_propose'], 0, 280),
            'body_points'                   => $data['body_points'],
            'technical_readability_score'   => $data['technical_readability_score'],
            'suggested_hashtags'            => $data['suggested_hashtags'],
            'tone_compliance_justification' => $data['tone_compliance_justification'],
        ]);

        $this->rawContent->update(['status' => RawContentStatus::Done]);
    }

    /**
     * Extract and validate the required keys from the AI structured response.
     *
     * @return array<string, mixed>
     */
    protected function validatedPayload(mixed $response): array
    {
        $data = is_array($response) ? $response : $response->toArray();

        $required = [
            'hook_propose',
            'body_points',
            'technical_readability_score',
            'suggested_hashtags',
            'tone_compliance_justification',
        ];

        foreach ($required as $key) {
            if (! array_key_exists($key, $data)) {
                throw new \RuntimeException("Clé manquante dans la réponse de l'IA : {$key}");
            }
        }

        if (! is_array($data['body_points']) || ! is_array($data['suggested_hashtags'])) {
            throw new \RuntimeException('body_points et suggested_hashtags doivent être des tableaux.');
        }

        $data['technical_readability_score'] = (int) $data['technical_readability_score'];

        return $data;
    }

    /**
     * Handle a definitive job failure after all retries are exhausted.
     */
    public function failed(?Throwable $exception): void
    {
        $this->rawContent->update(['status' => RawContentStatus::Failed]);

        Log::error('GeneratePostJob failed for RawContent #'.$this->rawContent->id, [
            'message' => $exception?->getMessage(),
        ]);
    }
}
