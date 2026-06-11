<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class GradeApprovalBatch extends Model
{
    use HasFactory;
    protected $fillable=['tenant_id','gradebook_id','title','status','submitted_by','approved_by','locked_by','submitted_at','approved_at','locked_at','sync_status','metadata'];
    protected $casts=['submitted_at'=>'datetime','approved_at'=>'datetime','locked_at'=>'datetime','metadata'=>'array'];

    public function gradebook()
    {
        return $this->belongsTo(Gradebook::class);
    }
}
