<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Board extends Model
{
    protected $fillable = ['name', 'description', 'category', 'color', 'owner_id', 'is_archived'];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'board_members')->withPivot('role')->withTimestamps();
    }

    public function lists()
    {
        return $this->hasMany(BoardList::class)->orderBy('position');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function labels()
    {
        return $this->hasMany(Label::class);
    }

    public function activities()
    {
        return $this->hasMany(ActivityLog::class);
    }
}
