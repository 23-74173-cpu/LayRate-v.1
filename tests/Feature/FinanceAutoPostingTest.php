<?php

namespace Tests\Feature;

use App\Models\EggPrice;
use App\Models\FeedBatch;
use App\Models\FinanceTransaction;
use App\Models\PreOrder;
use App\Models\Setting;
use App\Models\User;
use App\Services\ReportingDateService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Finance auto-posting from pre-orders (Egg Sales income) and feed batches
 * (Feed Cost expense): every way a source changes must leave Finance right.
 */
class FinanceAutoPostingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        EggPrice::create(['egg_size' => 'medium', 'price_per_tray' => 200, 'price_per_piece' => 7]);
    }

    private function autopostOn(?string $from = null): void
    {
        Setting::set('finance_autopost_start', $from ?? ReportingDateService::reportingDateString());
    }

    private function order(array $attributes = []): PreOrder
    {
        return PreOrder::create(array_merge([
            'customer_name' => 'Aling Nena',
            'egg_size' => 'medium',
            'egg_count' => 60,
            'requested_date' => ReportingDateService::reportingDateString(),
            'status' => 'pending',
        ], PreOrder::priceSnapshot('medium', 60), $attributes));
    }

    private function save(PreOrder $order, array $changes = [])
    {
        return $this->actingAs($this->admin)->patch(route('eggs.preorders.update', $order), array_merge([
            'customer_name' => $order->customer_name,
            'egg_size' => $order->egg_size,
            'egg_count' => $order->egg_count,
            'requested_date' => $order->requested_date->toDateString(),
            'status' => $order->status,
        ], $changes));
    }

    private function net(string $type): float
    {
        return (float) FinanceTransaction::where('type', $type)->sum('amount');
    }

    // ── pre-orders ───────────────────────────────────────────────────

    public function test_mark_paid_with_autopost_off_still_records_payment(): void
    {
        $order = $this->order();

        $this->save($order, ['mark_paid' => 1])
            ->assertRedirect(route('eggs.preorders'))
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'auto-posting is off'));

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertDatabaseCount('finance_transactions', 0);
    }

    public function test_mark_paid_without_price_still_records_payment(): void
    {
        $this->autopostOn();
        $order = $this->order(['unit_price_tray' => null, 'unit_price_piece' => null, 'total_amount' => null, 'tray_size' => null]);

        $this->save($order, ['mark_paid' => 1])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'no price'));

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertDatabaseCount('finance_transactions', 0);
    }

    public function test_mark_paid_with_autopost_on_posts_income(): void
    {
        $this->autopostOn();
        $order = $this->order();

        $this->save($order, ['mark_paid' => 1])
            ->assertSessionHas('success', 'Pre-order updated.');

        $this->assertSame((float) $order->total_amount, $this->net('income'));
        $this->assertDatabaseHas('finance_transactions', ['source_type' => 'pre_order', 'source_id' => $order->id, 'kind' => 'original']);
    }

    public function test_deleting_paid_order_reverses_its_income(): void
    {
        $this->autopostOn();
        $order = $this->order();
        $this->save($order, ['mark_paid' => 1]);

        $this->actingAs($this->admin)->delete(route('eggs.preorders.destroy', $order))
            ->assertRedirect(route('eggs.preorders'));

        $this->assertDatabaseMissing('pre_orders', ['id' => $order->id]);
        $this->assertSame(0.0, $this->net('income'));
        $this->assertDatabaseHas('finance_transactions', ['source_id' => $order->id, 'kind' => 'reversal']);
    }

    public function test_cancel_then_reopen_restores_income(): void
    {
        $this->autopostOn();
        $order = $this->order();
        $this->save($order, ['mark_paid' => 1]);

        $this->save($order->fresh(), ['status' => 'cancelled']);
        $this->assertSame(0.0, $this->net('income'));

        $this->save($order->fresh(), ['status' => 'fulfilled', 'fulfillment_date' => ReportingDateService::reportingDateString()]);
        $this->assertSame((float) $order->total_amount, $this->net('income'));
        $this->assertDatabaseMissing('finance_transactions', ['source_id' => $order->id, 'kind' => 'reversal']);

        // A second cancel still works (fresh reversal).
        $this->save($order->fresh(), ['status' => 'cancelled']);
        $this->assertSame(0.0, $this->net('income'));
    }

    public function test_repriced_paid_order_updates_income(): void
    {
        $this->autopostOn();
        $order = $this->order();
        $this->save($order, ['mark_paid' => 1]);

        $this->save($order->fresh(), ['egg_count' => 30]);

        $expected = (float) PreOrder::priceSnapshot('medium', 30)['total_amount'];
        $this->assertSame($expected, (float) $order->fresh()->total_amount);
        $this->assertSame($expected, $this->net('income'));
        $this->assertDatabaseCount('finance_transactions', 1);
    }

    public function test_paid_then_cancelled_in_one_save_posts_nothing(): void
    {
        $this->autopostOn();
        $order = $this->order();

        $this->save($order, ['mark_paid' => 1, 'status' => 'cancelled']);

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertDatabaseCount('finance_transactions', 0);
    }

    // ── feed batches ─────────────────────────────────────────────────

    private function batchPayload(array $changes = []): array
    {
        return array_merge([
            'crude_protein' => 17,
            'total_quantity_kg' => 100,
            'unit_cost' => 25,
            'date_received' => ReportingDateService::reportingDateString(),
        ], $changes);
    }

    public function test_batch_cost_cleared_then_entered_again_counts_again(): void
    {
        $this->autopostOn();
        $this->actingAs($this->admin)->post(route('feed.batch.store'), $this->batchPayload());
        $batch = FeedBatch::latest('id')->firstOrFail();
        $this->assertSame(2500.0, $this->net('expense'));

        $this->actingAs($this->admin)->put(route('feed.batch.update', $batch), $this->batchPayload(['unit_cost' => '']));
        $this->assertSame(0.0, $this->net('expense'));

        $this->actingAs($this->admin)->put(route('feed.batch.update', $batch), $this->batchPayload(['unit_cost' => 30]));
        $this->assertSame(3000.0, $this->net('expense'));
        $this->assertDatabaseMissing('finance_transactions', ['source_id' => $batch->id, 'kind' => 'reversal']);
    }

    public function test_batch_moved_before_cutover_keeps_its_expense_in_sync(): void
    {
        $this->autopostOn();
        $this->actingAs($this->admin)->post(route('feed.batch.store'), $this->batchPayload());
        $batch = FeedBatch::latest('id')->firstOrFail();

        $earlier = Carbon::parse(ReportingDateService::reportingDateString())->subDays(3)->toDateString();
        $this->actingAs($this->admin)->put(route('feed.batch.update', $batch), $this->batchPayload([
            'unit_cost' => 30,
            'date_received' => $earlier,
        ]));

        $row = FinanceTransaction::where('source_type', 'feed_batch')->where('source_id', $batch->id)->firstOrFail();
        $this->assertSame('3000.00', (string) $row->amount);
        $this->assertSame($earlier, $row->date->toDateString());
    }

    // ── Finance page ─────────────────────────────────────────────────

    public function test_breakdown_does_not_count_cancelled_pair_as_entries(): void
    {
        $this->autopostOn();
        $order = $this->order();
        $this->save($order, ['mark_paid' => 1]);
        $this->save($order->fresh(), ['status' => 'cancelled']);

        $response = $this->actingAs($this->admin)->get(route('finance.index'))->assertOk();
        $row = $response->viewData('breakdown')['income']->firstWhere('category', 'Egg Sales');

        $this->assertSame(0, (int) $row->entries);
    }
}
