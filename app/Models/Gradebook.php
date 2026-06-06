<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class Gradebook extends Model
{
    use HasFactory;
    protected $fillable=['tenant_id','course_id','class_id','title','grading_scheme','status','settings','created_by','locked_by','locked_at'];
    protected $casts=['settings'=>'array','locked_at'=>'datetime'];
    public function categories(){return $this->hasMany(GradeCategory::class)->orderBy('sort_order');}
    public function items(){return $this->hasMany(GradeItem::class)->orderBy('sort_order');}
    public function grades(){return $this->hasMany(LearnerGrade::class);}
    public function summaries(){return $this->hasMany(GradeSummary::class);}
    public function batches(){return $this->hasMany(GradeApprovalBatch::class);}
}
