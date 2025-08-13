<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $fillable = [
        'name',          // slug interno (único)
        'guard_name',
        'display_name',  // nombre secundario / público
        'description',   // descripción
    ];
}
