<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostLikeModel extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'post_id',
        'user_id',
    ];

    /**
     * Get the post that was liked.
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(PostModel::class, 'post_id');
    }

    /**
     * Get the user who liked the post.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
