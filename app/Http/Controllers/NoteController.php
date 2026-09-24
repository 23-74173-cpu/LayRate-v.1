<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class NoteController extends Controller
{
    public function index(Request $request)
    {
        $category = in_array($request->query('category'), Note::CATEGORIES, true)
            ? $request->query('category')
            : null;

        $notes = Note::with('cage')
            ->when($category, fn ($q) => $q->where('category', $category))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $categoryCounts = Note::selectRaw('category, COUNT(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        $cages = Cage::orderBy('cage_code')->get();
        $categories = Note::CATEGORIES;

        return view('notes.index', compact('notes', 'cages', 'categories', 'category', 'categoryCounts'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return redirect()->route('notes.index')
                ->withErrors($validator)
                ->withInput();
        }

        Note::create($this->withCategory($validator->validated()));

        return redirect()->route('notes.index', $this->returnQuery($request))->with('success', 'Note added.');
    }

    public function update(Request $request, Note $note)
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return redirect()->route('notes.index')
                ->with('reopen_edit_note', $note->id)
                ->withErrors($validator)
                ->withInput();
        }

        $note->update($this->withCategory($validator->validated()));

        return redirect()->route('notes.index', $this->returnQuery($request))->with('success', 'Note updated.');
    }

    public function destroy(Request $request, Note $note)
    {
        $note->delete();

        return redirect()->route('notes.index', $this->returnQuery($request))->with('success', 'Note deleted.');
    }

    private function rules(): array
    {
        return [
            'body'     => 'required|string|max:' . Note::MAX_LENGTH,
            'category' => ['nullable', Rule::in(Note::CATEGORIES)],
            'cage_id'  => 'nullable|exists:cages,id',
        ];
    }

    // A form that doesn't send a category (older clients) files the note under General.
    private function withCategory(array $data): array
    {
        $data['category'] = $data['category'] ?? Note::DEFAULT_CATEGORY;

        return $data;
    }

    // Keeps the category filter the user was looking at after an add/edit/delete.
    private function returnQuery(Request $request): array
    {
        $filter = $request->input('return_category');

        return in_array($filter, Note::CATEGORIES, true) ? ['category' => $filter] : [];
    }
}
