<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\Note;
use Illuminate\Http\Request;

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
        $data = $request->validate([
            'body'    => 'required|string|max:2000',
            'cage_id' => 'nullable|exists:cages,id',
        ]);

        Note::create($data);

        return redirect()->route('notes.index')->with('success', 'Note added.');
    }

    public function update(Request $request, Note $note)
    {
        $data = $request->validate([
            'body'    => 'required|string|max:2000',
            'cage_id' => 'nullable|exists:cages,id',
        ]);

        $note->update($data);

        return redirect()->route('notes.index')->with('success', 'Note updated.');
    }

    public function destroy(Note $note)
    {
        $note->delete();

        return redirect()->route('notes.index')->with('success', 'Note deleted.');
    }
}
