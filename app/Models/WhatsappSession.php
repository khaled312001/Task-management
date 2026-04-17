<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappSession extends Model
{
    protected $fillable = ['user_id', 'session_name', 'status', 'phone_number', 'qr_code'];
    public function user() { return $this->belongsTo(User::class); }
}
