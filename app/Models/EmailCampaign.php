<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailCampaign extends Model
{
    protected $fillable = [
        'user_id', 'smtp_setting_id', 'list_id', 'name', 'subject',
        'html_content', 'status', 'total_recipients', 'sent_count',
        'failed_count', 'delay_seconds', 'sent_at',
    ];
    protected function casts(): array { return ['sent_at' => 'datetime']; }
    public function user() { return $this->belongsTo(User::class); }
    public function smtpSetting() { return $this->belongsTo(SmtpSetting::class); }
    public function contactList() { return $this->belongsTo(ContactList::class, 'list_id'); }
    public function logs() { return $this->hasMany(EmailLog::class, 'campaign_id'); }
}
