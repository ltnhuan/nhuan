<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ExamGradingLog extends Model { public $timestamps=false; protected $fillable=['tenant_id','attempt_id','answer_id','action','before','after','actor_id','note','created_at']; protected $casts=['before'=>'array','after'=>'array','created_at'=>'datetime']; }
