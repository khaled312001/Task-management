<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmtpSetting extends Model
{
    protected $fillable = ['user_id', 'name', 'host', 'port', 'encryption', 'username', 'password', 'from_email', 'from_name', 'is_active'];
    protected $hidden = ['password'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function user() { return $this->belongsTo(User::class); }
}
