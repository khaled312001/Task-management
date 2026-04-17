<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Checklist extends Model
{
    protected $fillable = ['task_id', 'title', 'is_completed', 'position'];
    protected function casts(): array { return ['is_completed' => 'boolean']; }

    public function task() { return $this->belongsTo(Task::class); }
}
