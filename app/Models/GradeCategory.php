<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class GradeCategory extends Model
{
    use HasFactory;
    protected $fillable=['tenant_id','gradebook_id','parent_id','title','weight','max_score','aggregation_method','formula','sort_order'];
    public function gradebook(){return $this->belongsTo(Gradebook::class);}
    public function items(){return $this->hasMany(GradeItem::class,'category_id')->orderBy('sort_order');}
}
