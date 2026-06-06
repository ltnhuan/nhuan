<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class CareerTimelineEvent extends Model
{
    use HasFactory;
    protected $fillable=['tenant_id','user_id','event_type','title','description','event_date','metadata'];
    protected $casts=['event_date'=>'date','metadata'=>'array'];
}
