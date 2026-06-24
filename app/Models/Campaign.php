<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }


}
