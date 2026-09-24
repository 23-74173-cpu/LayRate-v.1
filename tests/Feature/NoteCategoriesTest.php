<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\Hen;
use App\Models\MortalityLog;
use App\Models\Note;
use App\Models\User;
use App\Services\ReportingDateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Notes have a category, can be filtered by it, and notes typed inside other
 * sections (mortality, egg logging, feed, hens) are copied into the central
 * Notes list under that section's category, ready to be reused.
 */
class NoteCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Cage $cage;
    private CageSlot $slot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'admin']);

        $this->cage = Cage::create([
            'cage_code' => 'CAGE-N', 'location' => 'Test', 'rows' => 1,
            'slots_per_row' => 1, 'max_chickens_per_slot' => 4, 'total_capacity' => 4, 'is_active' => 1,
        ]);
        $this->slot = CageSlot::create([
            'cage_id' => $this->cage->id, 'slot_number' => 1, 'row_number' => 1, 'column_number' => 1, 'current_occupancy' => 4,
        ]);
    }

    private function hen(): Hen
    {
        $hen = new Hen;
        $hen->chicken_id = 'N-HEN-' . uniqid();
        $hen->breed = 'ISA Brown';
        $hen->cage_slot_id = $this->slot->id;
        $hen->date_acquired = now()->subDays(30);
        $hen->placement_date = now()->subDays(30);
        $hen->age_at_placement_weeks = 0;
        $hen->flock_age_weeks = 20;
        $hen->is_active = 1;
        $hen->save();

        return $hen;
    }

    // ── Notes page ──

    public function test_store_saves_category(): void
    {
        $this->actingAs($this->user)
            ->post(route('notes.store'), ['body' => 'Check fan belts', 'category' => 'Environment'])
            ->assertRedirect(route('notes.index'));

        $this->assertDatabaseHas('notes', ['body' => 'Check fan belts', 'category' => 'Environment']);
    }

    public function test_store_without_category_defaults_to_general(): void
    {
        $this->actingAs($this->user)->post(route('notes.store'), ['body' => 'Buy nails']);

        $this->assertDatabaseHas('notes', ['body' => 'Buy nails', 'category' => 'General']);
    }

    public function test_store_rejects_unknown_category(): void
    {
        $this->actingAs($this->user)
            ->post(route('notes.store'), ['body' => 'x', 'category' => 'Secret'])
            ->assertSessionHasErrors(['category']);
    }

    public function test_update_changes_category(): void
    {
        $note = Note::create(['body' => 'Old', 'category' => 'General']);

        $this->actingAs($this->user)
            ->put(route('notes.update', $note), ['body' => 'Old', 'category' => 'Reports'])
            ->assertRedirect(route('notes.index'));

        $this->assertSame('Reports', $note->fresh()->category);
    }

    public function test_index_filters_by_category_and_shows_counts(): void
    {
        Note::create(['body' => 'Mortality note body', 'category' => 'Mortality']);
        Note::create(['body' => 'Feed note body', 'category' => 'Feed']);

        $this->actingAs($this->user)
            ->get(route('notes.index', ['category' => 'Mortality']))
            ->assertOk()
            ->assertSee('Mortality note body')
            ->assertDontSee('Feed note body')
            ->assertSee('All <span class="opacity-75">(2)</span>', false);

        $this->actingAs($this->user)
            ->get(route('notes.index'))
            ->assertSee('Mortality note body')
            ->assertSee('Feed note body');
    }

    public function test_index_ignores_unknown_category_filter(): void
    {
        Note::create(['body' => 'Visible note', 'category' => 'General']);

        $this->actingAs($this->user)
            ->get(route('notes.index', ['category' => 'Nope']))
            ->assertOk()
            ->assertSee('Visible note');
    }

    public function test_redirect_keeps_category_filter(): void
    {
        $this->actingAs($this->user)
            ->post(route('notes.store'), ['body' => 'Keep filter', 'category' => 'Feed', 'return_category' => 'Feed'])
            ->assertRedirect(route('notes.index', ['category' => 'Feed']));
    }

    // ── Note::saveFromSection ──

    public function test_save_from_section_dedupes_and_ignores_blank_or_bad_input(): void
    {
        $first = Note::saveFromSection('  Found dead at feeder  ', 'Mortality', $this->cage->id);
        $again = Note::saveFromSection('Found dead at feeder', 'Mortality');

        $this->assertNotNull($first);
        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, Note::where('category', 'Mortality')->count());
        $this->assertSame($this->cage->id, $first->cage_id);

        $this->assertNull(Note::saveFromSection('   ', 'Mortality'));
        $this->assertNull(Note::saveFromSection(null, 'Mortality'));
        $this->assertNull(Note::saveFromSection('Text', 'NotACategory'));
        $this->assertNull(Note::saveFromSection(str_repeat('a', Note::MAX_LENGTH + 1), 'Mortality'));

        // Same text in another category is its own note.
        Note::saveFromSection('Found dead at feeder', 'Hens');
        $this->assertSame(2, Note::count());
    }

    // ── Notes typed in other sections ──

    public function test_mortality_note_is_saved_to_notes_list(): void
    {
        $hen = $this->hen();

        $this->actingAs($this->user)
            ->postJson(route('mortality.store'), [
                'log_date' => ReportingDateService::reportingDateString(),
                'hen_ids'  => [$hen->id],
                'reason'   => 'Disease',
                'notes'    => 'Swollen eyes, isolated cage',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('notes', [
            'body'     => 'Swollen eyes, isolated cage',
            'category' => 'Mortality',
            'cage_id'  => $this->cage->id,
        ]);
    }

    public function test_mortality_without_note_adds_nothing(): void
    {
        $hen = $this->hen();

        $this->actingAs($this->user)->postJson(route('mortality.store'), [
            'log_date' => ReportingDateService::reportingDateString(),
            'hen_ids'  => [$hen->id],
            'reason'   => 'Disease',
            'notes'    => '',
        ])->assertOk();

        $this->assertSame(0, Note::count());
    }

    public function test_mortality_edit_saves_only_a_changed_note(): void
    {
        $this->hen();
        $log = MortalityLog::create([
            'cage_id' => $this->cage->id, 'log_date' => ReportingDateService::reportingDateString(),
            'count' => 1, 'reason' => 'Disease', 'notes' => 'Original note', 'recorded_by' => $this->user->id,
        ]);

        // Same note, only the reason changes: nothing copied.
        $this->actingAs($this->user)->put(route('mortality.update', $log), [
            'log_date' => $log->log_date->toDateString(), 'count' => 1, 'reason' => 'Injury', 'notes' => 'Original note',
        ]);
        $this->assertSame(0, Note::count());

        $this->actingAs($this->user)->put(route('mortality.update', $log), [
            'log_date' => $log->log_date->toDateString(), 'count' => 1, 'reason' => 'Injury', 'notes' => 'Updated note',
        ]);
        $this->assertDatabaseHas('notes', ['body' => 'Updated note', 'category' => 'Mortality']);
    }

    public function test_egg_log_note_is_saved_as_production_note(): void
    {
        $this->hen();
        $this->hen();

        $this->actingAs($this->user)->post(route('eggs.logging.store'), [
            'cage_slot_id' => $this->slot->id,
            'log_date'     => ReportingDateService::reportingDateString(),
            'egg_count'    => 2,
            'notes'        => '1 cracked egg',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('notes', ['body' => '1 cracked egg', 'category' => 'Production', 'cage_id' => $this->cage->id]);
    }

    public function test_egg_log_without_note_does_not_save_placeholder(): void
    {
        $this->hen();

        $this->actingAs($this->user)->post(route('eggs.logging.store'), [
            'cage_slot_id' => $this->slot->id,
            'log_date'     => ReportingDateService::reportingDateString(),
            'egg_count'    => 1,
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, Note::count());
    }

    public function test_feed_batch_note_is_saved_as_feed_note(): void
    {
        $this->actingAs($this->user)->post(route('feed.batch.store'), [
            'brand'         => 'Test Feed',
            'crude_protein' => 17,
            'date_received' => now()->toDateString(),
            'notes'         => 'Delivered slightly damp',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('notes', ['body' => 'Delivered slightly damp', 'category' => 'Feed']);
    }

    public function test_cull_note_is_saved_as_hens_note(): void
    {
        $hen = $this->hen();

        $this->actingAs($this->user)->postJson(route('chickens.cull'), [
            'hen_id'    => (string) $hen->id,
            'cull_date' => now()->toDateString(),
            'reason'    => 'low_production',
            'notes'     => 'Stopped laying for 3 weeks',
        ])->assertOk();

        $this->assertDatabaseHas('notes', ['body' => 'Stopped laying for 3 weeks', 'category' => 'Hens']);
    }

    // ── Reusing saved notes ──

    public function test_hens_page_offers_saved_mortality_notes(): void
    {
        Note::create(['body' => 'Found dead during morning check', 'category' => 'Mortality']);
        Note::create(['body' => 'General reminder only', 'category' => 'General']);

        $response = $this->actingAs($this->user)->get(route('chickens.index', ['tab' => 'mortality']));

        $response->assertOk();
        $response->assertSee('data-note-category="Mortality"', false);
        $response->assertSee('<option value="Found dead during morning check">', false);
        $response->assertDontSee('<option value="General reminder only">', false);
    }

    public function test_picker_says_when_there_are_no_saved_notes(): void
    {
        $this->actingAs($this->user)
            ->get(route('chickens.index', ['tab' => 'mortality']))
            ->assertOk()
            ->assertSee('No saved mortality notes yet');
    }
}
