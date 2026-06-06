<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class PortfolioItem extends Model
{
    use HasFactory;
    protected $fillable=['tenant_id','portfolio_id','user_id','item_type','title','description','issuer','evidence_url','file_path','issued_at','expired_at','verification_code','visibility','status','metadata'];
    protected $casts=['issued_at'=>'date','expired_at'=>'date','metadata'=>'array'];
    public function portfolio(){return $this->belongsTo(DigitalPortfolio::class,'portfolio_id');}
}
