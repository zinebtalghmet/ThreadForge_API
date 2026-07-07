<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlueprintResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
    return [
        'id'              => $this->id,
        'name'            => $this->name,
        'tone'            => $this->tone,
        'max_hashtags'    => $this->max_hashtags,
        'max_characters'  => $this->max_characters,
        'target_audience' => $this->target_audience,
        'style_rules'     => $this->style_rules,
        'generated_posts_count' => $this->whenCounted('generatedPosts'),
        'created_at'      => $this->created_at->format('d/m/Y H:i'),
    ];

    }
}
