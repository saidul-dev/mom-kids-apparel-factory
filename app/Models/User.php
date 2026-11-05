<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'current_site_id',
        'is_active',
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
            'is_active' => 'boolean',
        ];
    }

    /**
     * Every Site this user has been assigned to.
     */
    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class, 'user_sites')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function currentSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'current_site_id');
    }

    /**
     * The staff profile behind this login, when one exists — set by
     * Employee::enableLogin(). Most non-HRM users (nothing in Auth/
     * Sales/Accounts flows requires it) will have this be null.
     */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    /**
     * Super Admin and Admin see every site by default ("All Sites") — they
     * are not restricted to the sites they happen to be individually assigned to.
     */
    /**
     * Floor roles get the phone-app shell on small screens (home screen,
     * app bar, bottom tabs) instead of the sidebar admin layout.
     */
    public function usesAppShell(): bool
    {
        return $this->hasRole('Production Supervisor');
    }

    public function seesAllSites(): bool
    {
        return $this->hasRole(['Super Admin', 'Admin']);
    }

    public function defaultSite(): ?Site
    {
        return $this->sites()->wherePivot('is_default', true)->first()
            ?? $this->sites()->first();
    }
}
