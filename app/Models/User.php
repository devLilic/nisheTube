<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string $timezone
 * @property string|null $default_market_key
 * @property int $default_result_depth
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'timezone', 'default_market_key', 'default_result_depth'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @return HasMany<ResearchProject, $this> */
    public function researchProjects(): HasMany
    {
        return $this->hasMany(ResearchProject::class);
    }

    /** @return HasMany<ResearchQuery, $this> */
    public function researchQueries(): HasMany
    {
        return $this->hasMany(ResearchQuery::class);
    }

    /** @return HasMany<ResearchRun, $this> */
    public function researchRuns(): HasMany
    {
        return $this->hasMany(ResearchRun::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'default_result_depth' => 'integer',
            'password' => 'hashed',
        ];
    }
}
