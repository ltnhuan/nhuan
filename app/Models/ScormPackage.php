<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScormPackage extends Model
{
    protected $fillable = ['tenant_id', 'course_id', 'title', 'standard', 'version', 'launch_path', 'zip_path', 'file_size', 'checksum', 'status', 'manifest', 'metadata', 'uploaded_by'];
    protected $casts = ['manifest' => 'array', 'metadata' => 'array'];

    public function attempts()
    {
        return $this->hasMany(ScormAttempt::class, 'package_id');
    }
}
