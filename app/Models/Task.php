<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = [
        'list_id', 'board_id', 'title', 'description', 'priority', 'status',
        'assigned_to', 'created_by', 'due_date', 'position',
        'estimated_hours', 'actual_hours', 'is_archived', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'is_archived' => 'boolean',
        ];
    }

    public function list()
    {
        return $this->belongsTo(BoardList::class, 'list_id');
    }

    public function board()
    {
        return $this->belongsTo(Board::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function labels()
    {
        return $this->belongsToMany(Label::class, 'task_labels');
    }

    public function checklists()
    {
        return $this->hasMany(Checklist::class)->orderBy('position');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class)->latest();
    }

    public function activities()
    {
        return $this->hasMany(ActivityLog::class)->latest();
    }
}
