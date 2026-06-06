<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfflineQuizAnswer extends Model
{
    protected $table = 'offline_quiz_answers';

    protected $fillable = [
        'tenant_id', 'user_id', 'exam_id', 'attempt_id', 'attempt_question_id',
        'question_id', 'device_id', 'answer_data', 'status', 'sync_result',
        'client_updated_at', 'synced_at',
    ];

    protected $casts = [
        'answer_data' => 'array',
        'sync_result' => 'array',
        'client_updated_at' => 'datetime',
        'synced_at' => 'datetime',
    ];
}
