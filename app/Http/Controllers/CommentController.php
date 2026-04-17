<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommentController extends Controller
{
    public function store(Request $request)
    {
        $request->validate(['task_id' => 'required|exists:tasks,id', 'content' => 'required|string']);

        $comment = Comment::create([
            'task_id' => $request->task_id,
            'user_id' => Auth::id(),
            'content' => $request->content,
        ]);

        $task = $comment->task;
        if ($task->assigned_to && $task->assigned_to != Auth::id()) {
            Notification::create([
                'user_id' => $task->assigned_to, 'type' => 'comment',
                'title' => 'تعليق جديد', 'message' => Auth::user()->full_name . ' علق على: ' . $task->title,
            ]);
        }

        $comment->load('user:id,full_name,avatar_color');
        return response()->json($comment, 201);
    }

    public function destroy(Comment $comment)
    {
        if ($comment->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $comment->delete();
        return response()->json(['success' => true]);
    }
}
