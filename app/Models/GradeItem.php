<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class GradeItem extends Model
{
    use HasFactory;
    protected $fillable=['tenant_id','gradebook_id','category_id','source_type','source_id','title','max_score','weight','required','formula','sort_order','settings'];
    protected $casts=['required'=>'boolean','settings'=>'array'];
    public function gradebook(){return $this->belongsTo(Gradebook::class);}
    public function grades(){return $this->hasMany(LearnerGrade::class);}
}
