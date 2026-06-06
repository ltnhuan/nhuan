<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class LearnerGrade extends Model
{
    use HasFactory;
    protected $fillable=['tenant_id','gradebook_id','grade_item_id','user_id','raw_score','final_score','letter_grade','pass_status','source_status','feedback','updated_by'];
    public function item(){return $this->belongsTo(GradeItem::class,'grade_item_id');}
    public function user(){return $this->belongsTo(LmsUser::class,'user_id');}
}
