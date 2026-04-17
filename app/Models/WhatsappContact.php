<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappContact extends Model
{
    protected $fillable = ['campaign_id', 'phone', 'name', 'status', 'error_message', 'sent_at'];
    protected function casts(): array { return ['sent_at' => 'datetime']; }
    public function campaign() { return $this->belongsTo(WhatsappCampaign::class, 'campaign_id'); }
}
