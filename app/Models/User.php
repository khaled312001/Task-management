<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $fillable = ['username', 'email', 'password', 'full_name', 'role', 'avatar_color', 'is_active'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'is_active' => 'boolean'];
    }

    public function ownedBoards()
    {
        return $this->hasMany(Board::class, 'owner_id');
    }

    public function boards()
    {
        return $this->belongsToMany(Board::class, 'board_members')->withPivot('role')->withTimestamps();
    }

    public function assignedTasks()
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function getInitialsAttribute(): string
    {
        return mb_substr($this->full_name, 0, 1);
    }
}
