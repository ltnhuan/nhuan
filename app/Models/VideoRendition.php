<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VideoRendition extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'video_asset_id', 'quality', 'width', 'height', 'bitrate', 'hls_path', 'status'];

    public function asset()
    {
        return $this->belongsTo(VideoAsset::class, 'video_asset_id');
    }
}
