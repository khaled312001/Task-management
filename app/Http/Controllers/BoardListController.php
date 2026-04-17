<?php

namespace App\Http\Controllers;

use App\Models\BoardList;
use Illuminate\Http\Request;

class BoardListController extends Controller
{
    public function store(Request $request)
    {
        $request->validate(['board_id' => 'required|exists:boards,id', 'name' => 'required|string|max:100']);

        $maxPos = BoardList::where('board_id', $request->board_id)->max('position') ?? -1;

        $list = BoardList::create([
            'board_id' => $request->board_id,
            'name' => $request->name,
            'position' => $maxPos + 1,
        ]);

        return response()->json($list, 201);
    }

    public function update(Request $request, BoardList $list)
    {
        $list->update($request->only(['name', 'color']));
        return response()->json(['success' => true]);
    }

    public function reorder(Request $request)
    {
        foreach ($request->positions as $pos) {
            BoardList::where('id', $pos['id'])->update(['position' => $pos['position']]);
        }
        return response()->json(['success' => true]);
    }

    public function destroy(BoardList $list)
    {
        $list->delete();
        return response()->json(['success' => true]);
    }
}
