<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class XapiStatement extends Model
{
    protected $fillable = ['tenant_id', 'statement_uuid', 'actor', 'verb', 'object', 'result', 'context', 'raw_statement', 'stored_at', 'timestamp'];
    protected $casts = ['actor' => 'array', 'verb' => 'array', 'object' => 'array', 'result' => 'array', 'context' => 'array', 'raw_statement' => 'array', 'stored_at' => 'datetime', 'timestamp' => 'datetime'];
}
