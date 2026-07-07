<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Blueprint extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'tone',
        'max_hashtags',
        'max_characters',
        'target_audience',
        'style_rules',
    ];

    protected $casts = [
        'max_hashtags'   => 'integer',
        'max_characters' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rawContents(): HasMany
    {
        return $this->hasMany(RawContent::class);
    }

    /**
     * All generated posts produced from this blueprint's raw contents.
     */
    public function generatedPosts(): HasManyThrough
    {
        return $this->hasManyThrough(
            GeneratedPost::class,
            RawContent::class,
            'blueprint_id',   // FK on raw_contents
            'raw_content_id', // FK on generated_posts
            'id',
            'id'
        );
    }
}
