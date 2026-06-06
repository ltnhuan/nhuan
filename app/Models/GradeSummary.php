<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class GradeSummary extends Model
{
    use HasFactory;
    protected $fillable=['tenant_id','gradebook_id','user_id','total_score','max_score','percent','letter_grade','pass_status','status','approved_by','approved_at','locked_by','locked_at','metadata'];
    protected $casts=['approved_at'=>'datetime','locked_at'=>'datetime','metadata'=>'array'];
}
