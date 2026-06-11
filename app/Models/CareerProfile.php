<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class CareerProfile extends Model
{
    use HasFactory;
    protected $fillable=['tenant_id','user_id','headline','summary','public_slug','public_url','qr_payload','visibility','lifecycle_status','resume_data','settings'];
    protected $casts=['resume_data'=>'array','settings'=>'array'];
    public function user(){return $this->belongsTo(LmsUser::class,'user_id');}
    public function portfolio(){return $this->hasOne(DigitalPortfolio::class);}
}
