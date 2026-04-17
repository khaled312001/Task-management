<?php

namespace App\Http\Controllers;

use App\Models\Checklist;
use Illuminate\Http\Request;

class ChecklistController extends Controller
{
    public function store(Request $request)
    {
        $request->validate(['task_id' => 'required|exists:tasks,id', 'title' => 'required|string|max:255']);
        $maxPos = Checklist::where('task_id', $request->task_id)->max('position') ?? -1;
        $item = Checklist::create([
            'task_id' => $request->task_id, 'title' => $request->title, 'position' => $maxPos + 1,
        ]);
        return response()->json($item, 201);
    }

    public function toggle(Checklist $checklist)
    {
        $checklist->update(['is_completed' => !$checklist->is_completed]);
        return response()->json(['success' => true, 'is_completed' => $checklist->is_completed]);
    }

    public function destroy(Checklist $checklist)
    {
        $checklist->delete();
        return response()->json(['success' => true]);
    }
}
