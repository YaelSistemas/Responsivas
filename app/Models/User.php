<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        // 'rol',      // ⬅️ opcional: comenta/borra si ya NO quieres seguir grabándolo
        'activo',
        'empresa_id',
    ];

    protected $hidden = ['password','remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ✅ Ahora usa Spatie
    public function isAdmin(): bool
    {
        return $this->hasRole('Administrador');
    }

    // 👀 Para “ver” el/los roles en tablas/blades: {{ $user->rol_label }}
    public function getRolLabelAttribute(): string
    {
        return $this->getRoleNames()->implode(', ') ?: '—';
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }
}
