<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BoardController extends Controller
{
    public function index(Request $request)
    {
        $query = Auth::user()->boards()->where('is_archived', false)->withCount([
            'tasks',
            'tasks as completed_tasks_count' => fn($q) => $q->where('status', 'completed'),
        ]);

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        return response()->json($query->orderBy('updated_at', 'desc')->get());
    }

    public function show(Board $board)
    {
        $board->load([
            'lists.tasks' => fn($q) => $q->where('is_archived', false)->orderBy('position'),
            'lists.tasks.assignee:id,full_name,avatar_color',
            'lists.tasks.labels',
            'lists.tasks.checklists',
            'lists.tasks.comments',
            'members:id,username,full_name,role,avatar_color',
            'labels',
        ]);

        foreach ($board->lists as $list) {
            foreach ($list->tasks as $task) {
                $task->comment_count = $task->comments->count();
                $task->checklist_total = $task->checklists->count();
                $task->checklist_done = $task->checklists->where('is_completed', true)->count();
                unset($task->comments, $task->checklists);
            }
        }

        return response()->json($board);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:100']);

        $board = Board::create([
            'name' => $request->name,
            'description' => $request->description ?? '',
            'category' => $request->category ?? 'development',
            'color' => $request->color ?? '#6366f1',
            'owner_id' => Auth::id(),
        ]);

        // Add all active users as members
        $users = User::where('is_active', true)->pluck('id');
        foreach ($users as $userId) {
            $board->members()->attach($userId, [
                'role' => $userId == Auth::id() ? 'owner' : 'editor',
            ]);
        }

        // Create default lists
        $defaults = ['قيد الانتظار', 'قيد التنفيذ', 'مراجعة', 'مكتمل'];
        foreach ($defaults as $i => $name) {
            $board->lists()->create(['name' => $name, 'position' => $i]);
        }

        ActivityLog::create([
            'board_id' => $board->id, 'user_id' => Auth::id(),
            'action' => 'board_created', 'details' => $board->name,
        ]);

        return response()->json($board, 201);
    }

    public function update(Request $request, Board $board)
    {
        $board->update($request->only(['name', 'description', 'category', 'color']));
        return response()->json(['success' => true]);
    }

    public function archive(Board $board)
    {
        $board->update(['is_archived' => true]);
        return response()->json(['success' => true]);
    }
}
