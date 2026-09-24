<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NoteController extends Controller
{
    public function index()
    {
        $notes = Note::with('cage')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $cages = Cage::orderBy('cage_code')->get();

        return view('notes.index', compact('notes', 'cages'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'body'    => 'required|string|max:2000',
            'cage_id' => 'nullable|exists:cages,id',
        ]);

        if ($validator->fails()) {
            return redirect()->route('notes.index')
                ->withErrors($validator)
                ->withInput();
        }

        Note::create($validator->validated());

        return redirect()->route('notes.index')->with('success', 'Note added.');
    }

    public function update(Request $request, Note $note)
    {
        $validator = Validator::make($request->all(), [
            'body'    => 'required|string|max:2000',
            'cage_id' => 'nullable|exists:cages,id',
        ]);

        if ($validator->fails()) {
            return redirect()->route('notes.index')
                ->with('reopen_edit_note', $note->id)
                ->withErrors($validator)
                ->withInput();
        }

        $note->update($validator->validated());

        return redirect()->route('notes.index')->with('success', 'Note updated.');
    }

    public function destroy(Note $note)
    {
        $note->delete();

        return redirect()->route('notes.index')->with('success', 'Note deleted.');
    }
}
