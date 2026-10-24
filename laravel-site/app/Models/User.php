<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'password',
        'traveller_details',
        'notification_preferences',
        'account_mfa_enabled',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'mfa_enabled' => 'boolean',
            'mfa_secret' => 'encrypted',
            'traveller_details' => 'encrypted:array',
            'notification_preferences' => 'array',
            'account_mfa_enabled' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (! $user->username) {
                $user->username = static::makeUniqueUsername($user->name ?: $user->email);
            }
        });
    }

    public static function makeUniqueUsername(string $source, ?int $ignoreId = null): string
    {
        $base = Str::lower(Str::slug($source, '.'));
        $base = trim(Str::substr($base, 0, 20), '._-');
        if (strlen($base) < 3) {
            $base = 'traveller';
        }

        $username = $base;
        $suffix = 1;
        while (static::query()
            ->whereRaw('LOWER(username) = ?', [$username])
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $ending = (string) $suffix++;
            $username = Str::substr($base, 0, 20 - strlen($ending)).$ending;
        }

        return $username;
    }

    public function favorites(): BelongsToMany
    {
        return $this->belongsToMany(TravelPackage::class, 'user_favorites')->withTimestamps();
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function enquiryReplies(): HasMany
    {
        return $this->hasMany(EnquiryReply::class, 'admin_id');
    }
}
