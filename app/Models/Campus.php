<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Campus extends Model
{
    use HasFactory;

    protected $table = 'campuses';

    protected $fillable = ['tenant_id', 'code', 'name', 'address', 'phone', 'email', 'status'];

    protected $casts = [
        
    ];
}
