<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LmsUser extends Model
{
    use HasFactory;

    protected $table = 'lms_users';

    protected $fillable = ['tenant_id', 'sis_user_id', 'code', 'full_name', 'email', 'phone', 'avatar_url', 'user_type', 'status', 'metadata'];

    protected $casts = [
        'metadata' => 'array'
    ];
}
