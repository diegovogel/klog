<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Mail\UserInvited;
use App\Models\User;
use App\Models\UserInvite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class UserInviteService
{
    public const TOKEN_LENGTH = 64;

    public const EXPIRATION_DAYS = 2;

    /**
     * Invite a new user. Creates the user (with a random password they'll
     * replace on accept) and a single-use invite token, then emails them
     * the signed setup link.
     *
     * @param  array{name: string, email: string, role: UserRole}  $data
     */
    public function invite(array $data): UserInvite
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make(Str::random(40)),
                'role' => $data['role'],
            ]);

            return $this->createInviteFor($user);
        });
    }

    /**
     * Resend (or freshly issue) an invite for a user that was previously
     * invited. Replaces any existing unaccepted invite.
     */
    public function resend(User $user): UserInvite
    {
        $existing = $user->invite;

        if ($existing && $existing->isAccepted()) {
            throw new \RuntimeException('User has already accepted their invite.');
        }

        $existing?->delete();

        return $this->createInviteFor($user);
    }

    public function accept(UserInvite $invite, string $name, string $password): User
    {
        return DB::transaction(function () use ($invite, $name, $password) {
            /** @var User $user */
            $user = $invite->user()->firstOrFail();

            $user->forceFill([
                'name' => $name,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ])->save();

            $invite->forceFill(['accepted_at' => now()])->save();

            return $user->fresh();
        });
    }

    public function purgeExpired(): int
    {
        $expired = UserInvite::query()
            ->with(['user' => fn ($q) => $q->withCount('memories')])
            ->whereNull('accepted_at')
            ->where('expires_at', '<', now())
            ->get();

        $count = 0;
        foreach ($expired as $invite) {
            $user = $invite->user;
            $invite->delete();

            if ($user && ($user->memories_count ?? 0) === 0) {
                $user->delete();
            }
            $count++;
        }

        return $count;
    }

    private function createInviteFor(User $user): UserInvite
    {
        $invite = UserInvite::create([
            'user_id' => $user->id,
            'token' => $this->generateToken(),
            'expires_at' => now()->addDays(self::EXPIRATION_DAYS),
        ]);

        Mail::to($user->email)->send(new UserInvited($invite));

        return $invite;
    }

    private function generateToken(): string
    {
        do {
            $token = Str::random(self::TOKEN_LENGTH);
        } while (UserInvite::where('token', $token)->exists());

        return $token;
    }
}
