<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionCategory extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'question_bank_id', 'parent_id', 'code', 'name', 'description', 'sort_order', 'metadata'];

    protected $casts = ['metadata' => 'array'];

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }
}
