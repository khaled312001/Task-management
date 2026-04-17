<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    protected $fillable = ['campaign_id', 'contact_id', 'email', 'status', 'error_message', 'sent_at'];
    protected function casts(): array { return ['sent_at' => 'datetime']; }
    public function campaign() { return $this->belongsTo(EmailCampaign::class, 'campaign_id'); }
    public function contact() { return $this->belongsTo(Contact::class); }
}
