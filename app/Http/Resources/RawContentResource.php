<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RawContentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'blueprint_id'   => $this->blueprint_id,
            'body'           => $this->body,
            'status'         => $this->status?->value,
            'generated_post' => new GeneratedPostResource($this->whenLoaded('generatedPost')),
            'created_at'     => $this->created_at->format('d/m/Y H:i'),
        ];
    }
}
