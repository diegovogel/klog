<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\TwoFactorMethod;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'deactivated_at',
        'session_invalidated_at',
        'two_factor_method',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'deactivated_at' => 'datetime',
            'session_invalidated_at' => 'datetime',
            'two_factor_method' => TwoFactorMethod::class,
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function rememberedDevices(): HasMany
    {
        return $this->hasMany(TwoFactorRememberedDevice::class);
    }

    public function memories(): HasMany
    {
        return $this->hasMany(Memory::class);
    }

    public function invite(): HasOne
    {
        return $this->hasOne(UserInvite::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    public function isActive(): bool
    {
        return $this->deactivated_at === null;
    }

    public function isDeactivated(): bool
    {
        return ! $this->isActive();
    }

    public function deactivate(): void
    {
        DB::transaction(function () {
            $now = now();
            $this->forceFill([
                'deactivated_at' => $now,
                'session_invalidated_at' => $now,
                'remember_token' => Str::random(60),
            ])->save();
            $this->rememberedDevices()->delete();
            $this->invalidateSessions();
        });
    }

    private function invalidateSessions(): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $this->id)
            ->delete();
    }

    public function reactivate(): void
    {
        $this->update(['deactivated_at' => null]);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_method !== null
            && $this->two_factor_confirmed_at !== null;
    }

    public function usesTwoFactorMethod(TwoFactorMethod $method): bool
    {
        return $this->two_factor_method === $method;
    }

    public function unusedRecoveryCodeCount(): int
    {
        return count($this->two_factor_recovery_codes ?? []);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deactivated_at');
    }

    public function scopeDeactivated(Builder $query): Builder
    {
        return $query->whereNotNull('deactivated_at');
    }

    /**
     * Whether the install has more than one user. Cached for the request
     * (array driver is reset between tests via Laravel's app reset).
     */
    public static function multipleExist(): bool
    {
        return Cache::driver('array')->remember(
            'users.multiple_exist',
            60,
            fn () => static::query()->count() > 1,
        );
    }
}
