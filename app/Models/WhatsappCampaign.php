<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappCampaign extends Model
{
    protected $fillable = [
        'user_id', 'session_id', 'name', 'message', 'media_path',
        'status', 'total_recipients', 'sent_count', 'failed_count', 'delay_seconds',
    ];
    public function user() { return $this->belongsTo(User::class); }
    public function session() { return $this->belongsTo(WhatsappSession::class, 'session_id'); }
    public function contacts() { return $this->hasMany(WhatsappContact::class, 'campaign_id'); }
}
