<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PostModel extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'caption',
        'media_path',
        'media_type',
    ];

    /**
     * Get the user who created the post.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function userProfile()
    {
        return $this->hasOne(ProfileModel::class, 'user_id', 'user_id');
    }

    /**
     * Get the profile of the user who created the post.
     */
    public function profile()
    {
        return $this->hasOne(ProfileModel::class, 'user_id', 'user_id');
    }

    /**
     * Get all likes for the post.
     */
    public function likes(): HasMany
    {
        return $this->hasMany(PostLikeModel::class, 'post_id');
    }

    /**
     * Get all comments for the post.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(PostCommentModel::class, 'post_id');
    }
}
