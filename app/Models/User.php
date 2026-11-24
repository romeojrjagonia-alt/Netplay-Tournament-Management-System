<?php

namespace App\Models;

use App\Notifications\QueuedVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmail
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
        'email',
        'photo_path',
        'google_id',
        'password',
        'role',
        'email_verified_at',
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
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }

    /**
     * The picture shown on the account's profile: the photo uploaded to the
     * account itself, else the one on its player record (set at sign-up).
     */
    public function profilePhotoUrl(): ?string
    {
        if ($this->photo_path) {
            return Storage::disk('public')->url($this->photo_path);
        }

        return $this->player?->photo_url;
    }

    /**
     * Send the email verification link through the queue.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new QueuedVerifyEmail);
    }

    public function player(): HasOne
    {
        return $this->hasOne(Player::class);
    }

    /**
     * Every signed-in player needs a player record (the player pages read
     * it), but accounts created by an admin or switched to the player role
     * don't get one at sign-up. Create it on first use.
     */
    public function ensurePlayer(): Player
    {
        return $this->player()->firstOrCreate([], [
            'name' => $this->name,
            'email' => $this->email,
        ]);
    }

    public function playerLinkRequests(): HasMany
    {
        return $this->hasMany(PlayerLinkRequest::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isPlayer(): bool
    {
        return $this->activeRole() === 'player';
    }

    public function isUmpire(): bool
    {
        return $this->activeRole() === 'umpire';
    }

    /**
     * Session key set while an umpire is using the site as a player.
     */
    public const PLAYER_MODE_SESSION_KEY = 'acting_as_player';

    /**
     * Umpires can step into a player's shoes and back again. Their stored role
     * never changes: the choice lives in the session and ends when they sign out.
     */
    public function canSwitchRoles(): bool
    {
        return $this->role === 'umpire';
    }

    /**
     * Whether this umpire is currently using the site as a player. Only ever
     * true for the signed-in user themselves.
     */
    public function isInPlayerMode(): bool
    {
        return $this->canSwitchRoles()
            && auth()->id() === $this->getKey()
            && session()->get(self::PLAYER_MODE_SESSION_KEY) === true;
    }

    /**
     * The role the account is acting as right now: its stored role, except an
     * umpire in player mode, who is treated as a player.
     */
    public function activeRole(): ?string
    {
        return $this->isInPlayerMode() ? 'player' : $this->role;
    }

    /**
     * The route name for this user's home dashboard, based on the role they are acting as.
     */
    public function homeRouteName(): string
    {
        return match ($this->activeRole()) {
            'admin' => 'admin.dashboard',
            'player' => 'player.dashboard',
            'umpire' => 'umpire.dashboard',
            default => 'dashboard',
        };
    }
}
