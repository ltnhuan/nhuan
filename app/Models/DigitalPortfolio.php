<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class DigitalPortfolio extends Model
{
    use HasFactory;
    protected $fillable=['tenant_id','user_id','career_profile_id','title','summary','status','portfolio_score','settings'];
    protected $casts=['settings'=>'array'];
    public function items(){return $this->hasMany(PortfolioItem::class,'portfolio_id')->latest();}
    public function profile(){return $this->belongsTo(CareerProfile::class,'career_profile_id');}
    public function user(){return $this->belongsTo(LmsUser::class,'user_id');}
}
