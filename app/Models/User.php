<?php

namespace App\Models;

use App\Models\KycProfile;
use App\Models\Role;
use App\Models\SecurityEvent;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use App\Support\SpursMailer;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /** The user's security activity, most recent first. */
    public function securityEvents(): HasMany
    {
        return $this->hasMany(SecurityEvent::class)->latest();
    }

    /**
     * Auth emails go through the platform mailer rather than Laravel's own mail
     * transport — no Spurs app holds SMTP credentials, and every Spurs email is
     * rendered from the same shared templates.
     *
     * These still run through Laravel's notification pipeline, so the password
     * broker keeps minting and carrying its token and `Notification::fake()`
     * keeps working; only the delivery channel changes.
     */
    public function sendEmailVerificationNotification(): bool
    {
        $notification = new VerifyEmailNotification();
        $message = $notification->toSpurs($this);
        $to = $message['to'] ?? $this->routeNotificationFor('mail', $notification);

        if (! $to) {
            return false;
        }

        return SpursMailer::send(
            $message['template'],
            is_array($to) ? array_key_first($to) : $to,
            $message['context'] ?? [],
            $message['idempotencyKey'] ?? null,
        );
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /** The user's KYC record (one per account). */
    public function kyc()
    {
        return $this->hasOne(KycProfile::class);
    }

    /** Verified KYC tier (0 = unverified). Rides the SSO claims to every service. */
    public function kycLevel(): int
    {
        $k = $this->relationLoaded('kyc') ? $this->kyc : $this->kyc()->first();

        return $k && $k->status === 'verified' ? (int) $k->level : 0;
    }

    public function kycStatus(): string
    {
        $k = $this->relationLoaded('kyc') ? $this->kyc : $this->kyc()->first();

        return $k->status ?? 'unverified';
    }

    // ---- Platform RBAC -----------------------------------------------------

    /** Roles assigned to this user. Authoritative for all of Spurs Cloud. */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /** The role slugs this user holds, e.g. ["support"]. */
    public function roleNames(): array
    {
        return $this->roles->pluck('name')->all();
    }

    /** Every permission key granted through this user's roles (deduped). */
    public function permissionKeys(): array
    {
        return $this->roles
            ->loadMissing('permissions')
            ->flatMap(fn (Role $r) => $r->permissions->pluck('key'))
            ->unique()
            ->values()
            ->all();
    }

    public function hasRole(string $name): bool
    {
        return in_array($name, $this->roleNames(), true);
    }

    /** True if the user has a permission directly, is a superadmin, or matches a
     *  service wildcard (e.g. "pay.*" grants "pay.refund"). */
    public function hasPermission(string $key): bool
    {
        if ($this->hasRole('superadmin')) {
            return true;
        }

        $keys = $this->permissionKeys();

        if (in_array($key, $keys, true)) {
            return true;
        }

        $service = explode('.', $key)[0];

        return in_array("{$service}.*", $keys, true);
    }

    /** Replace the user's roles with the given role slugs. */
    public function syncRoles(array $names): void
    {
        $ids = Role::whereIn('name', $names)->pluck('id')->all();
        $this->roles()->sync($ids);
        $this->load('roles');
    }

    public function assignRole(string $name): void
    {
        if ($role = Role::where('name', $name)->first()) {
            $this->roles()->syncWithoutDetaching([$role->id]);
        }
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'country',
        'currency',
        'password',
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
            'trust_updated_at' => 'datetime',
            'trust_score' => 'integer',
            'password' => 'hashed',
        ];
    }
}
