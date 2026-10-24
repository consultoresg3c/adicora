<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/** Usuario de una empresa o superadministrador global sin empresa. */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'company_id',
        'status_id',
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

    /** Estado base de acceso, independiente del nombre del estado de catálogo. */
    public function getLifecycleStatusAttribute(): int
    {
        return $this->trashed() ? -1 : (int) (bool) $this->status?->is_active;
    }

    /** Acciones sobre esta cuenta mediante user_id; actor_id identifica a quien las realizó. */
    public function activities(): HasMany
    {
        return $this->hasMany(AuditEntry::class, 'user_id');
    }

    /** Últimas cinco acciones sobre esta cuenta, sin credenciales. */
    public function recentActivities(): HasMany
    {
        return $this->activities()->latest('id')->limit(5);
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
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }

    /** Empresa a la que pertenece el usuario; nulo para el superadministrador. */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Estado que permite o impide iniciar y mantener sesión. */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /** Roles de la misma empresa, o globales para usuarios del sistema. */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /** Permisos asignados directamente, complementarios a los roles. */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }
}
