<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $table = 'organizations';

    protected $fillable = ['tenant_id', 'parent_id', 'code', 'name', 'type', 'status', 'settings'];

    protected $casts = [
        'settings' => 'array'
    ];
}
