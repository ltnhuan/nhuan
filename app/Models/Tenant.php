<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasFactory;

    protected $table = 'tenants';

    protected $fillable = ['code', 'name', 'legal_name', 'domain', 'status', 'logo_url', 'primary_color', 'secondary_color', 'locale', 'timezone', 'settings'];

    protected $casts = [
        'settings' => 'array'
    ];
}
