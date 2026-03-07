<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProfileModel extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'profile_models';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'profile_for',
        'full_name',
        'gender',
        'date_of_birth',
        'education',
        'profession',
        'annual_income',
        'company_name',
        'father_occupation',
        'mother_occupation',
        'siblings',
        'family_type',
        'family_values',
        'diet',
        'drinking',
        'smoking',
        'partner_age_min',
        'partner_age_max',
        'partner_height_min',
        'partner_height_max',
        'partner_religion',
        'partner_locations',
        'main_photo',
        'additional_photos',
        'current_step',
        'is_complete',
        'is_verified',
        'verification_pin'
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'additional_photos' => 'array',
            'is_complete' => 'boolean',
            'is_verified' => 'boolean',
        ];
    }


    /**
     * Get the user that owns the profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
