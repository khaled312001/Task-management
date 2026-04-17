<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactList extends Model
{
    protected $fillable = ['user_id', 'name', 'description', 'contacts_count'];
    public function user() { return $this->belongsTo(User::class); }
    public function contacts() { return $this->hasMany(Contact::class, 'list_id'); }
}
