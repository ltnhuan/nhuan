<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class ExamAttemptEvent extends Model { use HasFactory; protected $fillable=['tenant_id','attempt_id','user_id','event_type','event_value','metadata']; protected $casts=['metadata'=>'array']; }
