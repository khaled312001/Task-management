<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $fillable = ['list_id', 'email', 'name', 'phone', 'extra_data', 'is_subscribed'];
    protected function casts(): array { return ['extra_data' => 'array', 'is_subscribed' => 'boolean']; }
    public function contactList() { return $this->belongsTo(ContactList::class, 'list_id'); }
}
