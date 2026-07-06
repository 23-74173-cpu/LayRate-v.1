<?php

namespace Tests\Feature;

use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\Hen;
use App\Models\ProductionLog;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

/**
 * P5: Farm-layout remove-cell invariants — cage unplacing, grid shrink,
 * edge-only messaging, and data integrity (no cascading side effects).
 *
 * Items 1, 2 (this session).
 */
class FarmLayoutRemoveCellTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->user = User::where('email', 'admin@layrate.local')->first();
        Setting::set('farm_grid_rows', 4);
        Setting::set('farm_grid_cols', 4);
    }

    /**
     * 1a: Row-spanning removal — a lone cage in a row does NOT stretch.
     *
     * Not a removeCell endpoint test, but an assertion about the rendered
     * grid. Verifies via the view that all cells have equal rendered width.
     */
    public function test_lone_cage_does_not_span_full_row(): void
    {
        $response = $this->actingAs($this->user)->get('/cages');
        $response->assertStatus(200);

        $html = $response->getContent();

        // Count how many .farm-cell elements appear
        preg_match_all('/class="[^"]*farm-cell[^"]*"/', $html, $cells);
        $this->assertCount(16, $cells[0], 'Grid should have 16 cells (4×4)');

        // Verify each cell has a data-row and data-col attribute (no spanning)
        preg_match_all('/data-row="(\d+)"[^>]*data-col="(\d+)"/', $html, $coords);
        $this->assertCount(16, $coords[0], 'All 16 cells should have data-row/data-col');

        // Verify all rows have 4 cells each
        $rowCounts = array_count_values($coords[1]);
        foreach ($rowCounts as $row => $count) {
            $this->assertEquals(4, $count, "Row {$row} should have 4 cells (no spanning)");
        }
    }

    /**
     * 1b: Cage-occupied cell removal — cage is unplaced, data untouched.
     */
    public function test_cage_occupied_cell_removal_unplaces_cage(): void
    {
        $cage = Cage::first();
        $this->assertNotNull($cage);
        $cage->update(['location_row' => 0, 'location_column' => 0]);

        $row = $cage->location_row;
        $col = $cage->location_column;

        // Count associated data before
        $slotsBefore = CageSlot::where('cage_id', $cage->id)->count();
        $hensBefore = Hen::whereIn('cage_slot_id', $cage->cageSlots->pluck('id'))
            ->where('is_active', 1)->count();
        $prodLogsBefore = ProductionLog::whereIn('cage_slot_id', $cage->cageSlots->pluck('id'))->count();

        $this->actingAs($this->user)
            ->postJson('/cages/remove-cell', [
                'row' => $row,
                'col' => $col,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        // Cage coordinates nulled
        $cage->refresh();
        $this->assertNull($cage->location_row);
        $this->assertNull($cage->location_column);

        // All associated data completely untouched
        $this->assertEquals($slotsBefore, CageSlot::where('cage_id', $cage->id)->count(),
            'Slots unchanged after remove-cell');
        $this->assertEquals($hensBefore, Hen::whereIn('cage_slot_id', $cage->cageSlots->pluck('id'))
            ->where('is_active', 1)->count(),
            'Hens unchanged after remove-cell');
        $this->assertEquals($prodLogsBefore, ProductionLog::whereIn('cage_slot_id', $cage->cageSlots->pluck('id'))->count(),
            'Production logs unchanged after remove-cell');

        // Cage still exists (NOT deleted)
        $this->assertNotNull(Cage::find($cage->id), 'Cage should still exist');
    }

    /**
     * 1c: Edge empty-cell removal shrinks grid columns when last column is empty.
     */
    public function test_edge_empty_cell_removal_shrinks_grid_cols(): void
    {
        $this->assertEquals(4, (int) Setting::get('farm_grid_cols'));

        // Remove empty cell at last column, last row
        $this->actingAs($this->user)
            ->postJson('/cages/remove-cell', [
                'row' => 3,
                'col' => 3,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertEquals(3, (int) Setting::get('farm_grid_cols'),
            'Grid cols should shrink from 4 to 3');
    }

    /**
     * 1c cont'd: Edge empty-cell removal shrinks grid rows when last row is empty.
     */
    public function test_edge_empty_cell_removal_shrinks_grid_rows(): void
    {
        // Unplace all cages on last row first
        Cage::where('location_row', 3)->update(['location_row' => null, 'location_column' => null]);

        $this->assertEquals(4, (int) Setting::get('farm_grid_rows'));

        $this->actingAs($this->user)
            ->postJson('/cages/remove-cell', [
                'row' => 3,
                'col' => 0,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertEquals(3, (int) Setting::get('farm_grid_rows'),
            'Grid rows should shrink from 4 to 3');
    }

    /**
     * 1c cont'd: Edge cell with occupied last column returns error.
     */
    public function test_edge_empty_cell_removal_blocked_when_last_col_occupied(): void
    {
        // Position a cage at (1, 3) so col 3 is occupied,
        // then try to remove empty cell at (0, 3) — should be blocked
        Cage::whereNotNull('id')->update(['location_row' => null, 'location_column' => null]);
        $cage = Cage::first();
        $cage->update(['location_row' => 1, 'location_column' => 3]);

        $this->actingAs($this->user)
            ->postJson('/cages/remove-cell', [
                'row' => 0,
                'col' => 3,
            ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot shrink the grid — the last column still contains cages. Move or remove them first.',
            ]);
    }

    /**
     * 1d+2: Mid-grid empty cell removal returns error with edge-only policy message.
     */
    public function test_mid_grid_empty_cell_removal_returns_edge_only_policy(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/cages/remove-cell', [
                'row' => 1,
                'col' => 1,
            ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);

        $msg = $response->json('message');
        $this->assertNotNull($msg, 'Error response should have a message');
        $this->assertStringContainsString('edge', strtolower($msg),
            'Error message should explain edge-only policy');
        $this->assertStringContainsString('only', strtolower($msg),
            'Error message should explain edge-only policy');
    }

    /**
     * Remove-cell is admin-guarded; operators get 403.
     */
    public function test_remove_cell_requires_admin(): void
    {
        $operator = User::where('email', 'operator@layrate.local')->first();
        $this->assertNotNull($operator);

        $this->actingAs($operator)
            ->postJson('/cages/remove-cell', [
                'row' => 0,
                'col' => 0,
            ])
            ->assertStatus(403);
    }

    /**
     * Validation: row and col are required, must be non-negative integers.
     */
    public function test_remove_cell_validates_input(): void
    {
        $this->actingAs($this->user)
            ->postJson('/cages/remove-cell', [])
            ->assertStatus(422);

        $this->actingAs($this->user)
            ->postJson('/cages/remove-cell', [
                'row' => -1,
                'col' => 0,
            ])
            ->assertStatus(422);

        $this->actingAs($this->user)
            ->postJson('/cages/remove-cell', [
                'row' => 0,
                'col' => -1,
            ])
            ->assertStatus(422);
    }

    /**
     * Grid settings are never reduced below 1×1.
     */
    public function test_grid_never_below_1x1(): void
    {
        Setting::set('farm_grid_rows', 1);
        Setting::set('farm_grid_cols', 1);

        $this->actingAs($this->user)
            ->postJson('/cages/remove-cell', [
                'row' => 0,
                'col' => 0,
            ])
            ->assertOk();

        $this->assertEquals(1, (int) Setting::get('farm_grid_rows'), 'Rows should stay at 1');
        $this->assertEquals(1, (int) Setting::get('farm_grid_cols'), 'Cols should stay at 1');
    }
}
