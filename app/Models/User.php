<?php

namespace App\Models;

// Убедитесь, что используете правильные импорты
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
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
        ];
    }

    public function dealers(): BelongsToMany
    {
        return $this->belongsToMany(Dealer::class)->withTimestamps();
    }

    public function isAdmin(): bool
    {
        return ($this->role ?? null) === 'admin' || (bool) $this->is_admin;
    }

    public function isManager(): bool
    {
        return in_array($this->role ?? null, ['manager', 'master-consultant'], true);
    }

    public function isMasterConsultant(): bool
    {
        return ($this->role ?? null) === 'master-consultant';
    }

    public function roleLabel(): string
    {
        if ($this->isAdmin()) {
            return 'Администратор';
        }

        if ($this->isMasterConsultant()) {
            return 'Мастер-консультант';
        }

        return 'Менеджер';
    }

    public function canAccessAdminPanel(): bool
    {
        return $this->isAdmin() || $this->isManager();
    }
}
