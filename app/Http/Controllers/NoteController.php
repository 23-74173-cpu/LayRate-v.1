<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NoteController extends Controller
{
    public function index(Request $request)
    {
        $category = $request->get('category');

        $notes = Note::with('cage')
            ->when($category, fn ($q) => $q->where('category', $category))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $cages = Cage::orderBy('cage_code')->get();
        $categories = Note::CATEGORIES;

        return view('notes.index', compact('notes', 'cages', 'categories', 'category'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'body'     => 'required|string|max:2000',
            'category' => 'nullable|in:' . implode(',', Note::CATEGORIES),
            'cage_id'  => 'nullable|exists:cages,id',
        ]);

        if ($validator->fails()) {
            return redirect()->route('notes.index')
                ->withErrors($validator)
                ->withInput();
        }

        $data = $validator->validated();
        $data['category'] = $data['category'] ?? 'General';

        Note::create($data);

        return redirect()->route('notes.index')->with('success', 'Note added.');
    }

    public function update(Request $request, Note $note)
    {
        $validator = Validator::make($request->all(), [
            'body'     => 'required|string|max:2000',
            'category' => 'nullable|in:' . implode(',', Note::CATEGORIES),
            'cage_id'  => 'nullable|exists:cages,id',
        ]);

        if ($validator->fails()) {
            return redirect()->route('notes.index')
                ->with('reopen_edit_note', $note->id)
                ->withErrors($validator)
                ->withInput();
        }

        $data = $validator->validated();
        $data['category'] = $data['category'] ?? 'General';

        $note->update($data);

        return redirect()->route('notes.index')->with('success', 'Note updated.');
    }

    public function destroy(Note $note)
    {
        $note->delete();

        return redirect()->route('notes.index')->with('success', 'Note deleted.');
    }
}
