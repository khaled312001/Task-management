<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\ActivityLog;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    public function show(Task $task)
    {
        $task->load([
            'assignee:id,full_name,avatar_color',
            'creator:id,full_name,avatar_color',
            'labels',
            'checklists',
            'comments.user:id,full_name,avatar_color',
            'activities' => fn($q) => $q->latest()->limit(20),
            'activities.user:id,full_name',
        ]);
        return response()->json($task);
    }

    public function store(Request $request)
    {
        $request->validate([
            'list_id' => 'required|exists:board_lists,id',
            'board_id' => 'required|exists:boards,id',
            'title' => 'required|string|max:255',
        ]);

        $maxPos = Task::where('list_id', $request->list_id)->max('position') ?? -1;

        $task = Task::create([
            'list_id' => $request->list_id,
            'board_id' => $request->board_id,
            'title' => $request->title,
            'description' => $request->description ?? '',
            'priority' => $request->priority ?? 'medium',
            'assigned_to' => $request->assigned_to ?: null,
            'created_by' => Auth::id(),
            'due_date' => $request->due_date ?: null,
            'position' => $maxPos + 1,
        ]);

        ActivityLog::create([
            'board_id' => $request->board_id, 'task_id' => $task->id,
            'user_id' => Auth::id(), 'action' => 'task_created', 'details' => $task->title,
        ]);

        if ($request->assigned_to && $request->assigned_to != Auth::id()) {
            Notification::create([
                'user_id' => $request->assigned_to, 'type' => 'task_assigned',
                'title' => 'مهمة جديدة', 'message' => Auth::user()->full_name . ' أسند إليك: ' . $task->title,
            ]);
        }

        $task->load('assignee:id,full_name,avatar_color');
        return response()->json($task, 201);
    }

    public function update(Request $request, Task $task)
    {
        $data = $request->only([
            'title', 'description', 'priority', 'status', 'assigned_to',
            'due_date', 'estimated_hours', 'actual_hours',
        ]);

        if (isset($data['assigned_to']) && $data['assigned_to'] === '') {
            $data['assigned_to'] = null;
        }
        if (isset($data['due_date']) && $data['due_date'] === '') {
            $data['due_date'] = null;
        }

        if (($data['status'] ?? null) === 'completed' && $task->status !== 'completed') {
            $data['completed_at'] = now();
        }

        $task->update($data);

        if ($request->has('label_ids')) {
            $task->labels()->sync($request->label_ids);
        }

        ActivityLog::create([
            'board_id' => $task->board_id, 'task_id' => $task->id,
            'user_id' => Auth::id(), 'action' => 'task_updated', 'details' => $task->title,
        ]);

        return response()->json(['success' => true]);
    }

    public function move(Request $request, Task $task)
    {
        $task->update([
            'list_id' => $request->list_id,
            'position' => $request->position ?? 0,
        ]);

        if ($request->has('positions')) {
            foreach ($request->positions as $pos) {
                Task::where('id', $pos['id'])->update(['position' => $pos['position']]);
            }
        }

        $listName = $task->list()->value('name');
        ActivityLog::create([
            'board_id' => $task->board_id, 'task_id' => $task->id,
            'user_id' => Auth::id(), 'action' => 'task_moved',
            'details' => $task->title . ' → ' . $listName,
        ]);

        return response()->json(['success' => true]);
    }

    public function archive(Task $task)
    {
        $task->update(['is_archived' => true]);
        return response()->json(['success' => true]);
    }

    public function destroy(Task $task)
    {
        $task->delete();
        return response()->json(['success' => true]);
    }

    public function myTasks()
    {
        $tasks = Task::where('assigned_to', Auth::id())
            ->where('is_archived', false)
            ->with(['board:id,name,color', 'list:id,name', 'labels'])
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'medium', 'low')")
            ->orderBy('due_date')
            ->get();

        return response()->json($tasks);
    }
}
