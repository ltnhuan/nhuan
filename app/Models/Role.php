<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $table = 'roles';

    protected $fillable = ['tenant_id', 'name', 'guard_name', 'display_name', 'scope', 'description'];

    protected $casts = [
        
    ];
}
