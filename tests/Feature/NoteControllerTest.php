<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\Note;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

class NoteControllerTest extends TestCase
{
    private User $user;
    private Cage $cage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->user = User::where('email', 'operator@layrate.local')->firstOrFail();
        $this->cage = Cage::where('cage_code', 'CAGE-A')->firstOrFail();
    }

    public function test_index_page_loads(): void
    {
        Note::create([
            'body'    => 'Test note content',
            'cage_id' => $this->cage->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('notes.index'))
            ->assertOk()
            ->assertSee('Test note content');
    }

    public function test_store_creates_note(): void
    {
        $this->actingAs($this->user)
            ->post(route('notes.store'), [
                'body'    => 'Newly created note',
                'cage_id' => $this->cage->id,
            ])
            ->assertRedirect(route('notes.index'));

        $this->assertDatabaseHas('notes', [
            'body'    => 'Newly created note',
            'cage_id' => $this->cage->id,
        ]);
    }

    public function test_store_validates_body_required(): void
    {
        $this->actingAs($this->user)
            ->post(route('notes.store'), ['body' => ''])
            ->assertSessionHasErrors(['body']);
    }

    public function test_update_modifies_note(): void
    {
        $note = Note::create([
            'body'    => 'Original body text',
            'cage_id' => $this->cage->id,
        ]);

        $this->actingAs($this->user)
            ->put(route('notes.update', $note), [
                'body'    => 'Updated body text',
                'cage_id' => null,
            ])
            ->assertRedirect(route('notes.index'));

        $this->assertDatabaseHas('notes', [
            'id'   => $note->id,
            'body' => 'Updated body text',
        ]);
    }

    public function test_destroy_deletes_note(): void
    {
        $note = Note::create([
            'body'    => 'Note to delete',
            'cage_id' => $this->cage->id,
        ]);

        $this->actingAs($this->user)
            ->delete(route('notes.destroy', $note))
            ->assertRedirect(route('notes.index'));

        $this->assertDatabaseMissing('notes', ['id' => $note->id]);
    }

    public function test_guest_cannot_access_notes(): void
    {
        $this->get(route('notes.index'))
            ->assertRedirect(route('login'));

        $this->post(route('notes.store'), ['body' => 'test'])
            ->assertRedirect(route('login'));
    }
}
