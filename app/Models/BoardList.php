<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BoardList extends Model
{
    protected $table = 'board_lists';
    protected $fillable = ['board_id', 'name', 'position', 'color'];

    public function board()
    {
        return $this->belongsTo(Board::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'list_id')->where('is_archived', false)->orderBy('position');
    }
}
