<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostCommentModel extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'post_id',
        'user_id',
        'content',
    ];

    /**
     * Get the post that was commented on.
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(PostModel::class, 'post_id');
    }
    public function userProfile()
{
    return $this->hasOne(ProfileModel::class, 'user_id', 'user_id');
}

    /**
     * Get the user who made the comment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
