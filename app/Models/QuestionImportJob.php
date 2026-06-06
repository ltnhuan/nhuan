<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionImportJob extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'file_path', 'format', 'status', 'total_rows', 'success_rows', 'failed_rows', 'error_report_path', 'created_by'];
}
