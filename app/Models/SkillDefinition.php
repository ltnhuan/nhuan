<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class SkillDefinition extends Model
{
    use HasFactory;
    protected $fillable=['tenant_id','category','name','description','level_scale','metadata'];
    protected $casts=['metadata'=>'array'];
}
