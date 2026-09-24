<?php

namespace Tests\Feature;

use App\Models\FinanceTransaction;
use App\Models\User;
use App\Services\ReportingDateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->operator = User::factory()->create(['role' => 'operator']);
    }

    private function transaction(string $type, string $category, float $amount, ?string $date = null): FinanceTransaction
    {
        $txn = new FinanceTransaction([
            'type'     => $type,
            'category' => $category,
            'amount'   => $amount,
            'date'     => $date ?? ReportingDateService::reportingDateString(),
        ]);
        $txn->recorded_by = $this->admin->id;
        $txn->save();

        return $txn;
    }

    public function test_index_page_loads_with_totals_and_net(): void
    {
        $this->transaction('income', 'Egg Sales', 1000);
        $this->transaction('expense', 'Feed Cost', 250.50);

        $this->actingAs($this->operator)
            ->get(route('finance.index'))
            ->assertOk()
            ->assertSee('Record Transaction')
            ->assertSee('₱1,000.00')
            ->assertSee('₱250.50')
            ->assertSee('₱749.50')
            ->assertSee('Egg Sales')
            ->assertSee('Feed Cost');
    }

    public function test_dates_are_shown_as_month_day_year(): void
    {
        $this->transaction('income', 'Egg Sales', 50, '2026-01-15');

        $this->actingAs($this->operator)
            ->get(route('finance.index'))
            ->assertOk()
            ->assertSee('01/15/2026');
    }

    public function test_store_records_transaction_with_signed_in_user(): void
    {
        $this->actingAs($this->operator)
            ->post(route('finance.store'), [
                'type'        => 'expense',
                'category'    => 'Medicine',
                'amount'      => '320.75',
                'date'        => ReportingDateService::reportingDateString(),
                'description' => 'Vitamins',
            ])
            ->assertRedirect(route('finance.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('finance_transactions', [
            'type'        => 'expense',
            'category'    => 'Medicine',
            'amount'      => 320.75,
            'description' => 'Vitamins',
            'recorded_by' => $this->operator->id,
        ]);
    }

    public function test_store_ignores_recorded_by_from_request(): void
    {
        $this->actingAs($this->operator)
            ->post(route('finance.store'), [
                'type'        => 'income',
                'category'    => 'Hen Sales',
                'amount'      => 100,
                'date'        => ReportingDateService::reportingDateString(),
                'recorded_by' => $this->admin->id,
            ]);

        $this->assertSame($this->operator->id, FinanceTransaction::first()->recorded_by);
    }

    public function test_category_must_match_type(): void
    {
        $this->actingAs($this->operator)
            ->post(route('finance.store'), [
                'type'     => 'expense',
                'category' => 'Egg Sales',
                'amount'   => 100,
                'date'     => ReportingDateService::reportingDateString(),
            ])
            ->assertSessionHasErrors(['category']);

        $this->assertDatabaseCount('finance_transactions', 0);
    }

    public function test_future_date_and_zero_amount_are_rejected(): void
    {
        $this->actingAs($this->operator)
            ->post(route('finance.store'), [
                'type'     => 'income',
                'category' => 'Egg Sales',
                'amount'   => 0,
                'date'     => ReportingDateService::reportingDate()->addDays(2)->toDateString(),
            ])
            ->assertSessionHasErrors(['amount', 'date']);
    }

    public function test_date_filter_limits_list_and_totals(): void
    {
        $this->transaction('income', 'Egg Sales', 111, '2026-01-10');
        $this->transaction('income', 'Hen Sales', 222, '2026-02-10');

        $this->actingAs($this->operator)
            ->get(route('finance.index', ['from' => '2026-02-01', 'to' => '2026-02-28']))
            ->assertOk()
            ->assertSee('₱222.00')
            ->assertDontSee('₱111.00')
            ->assertDontSee('₱333.00');
    }

    public function test_bad_filter_values_are_ignored(): void
    {
        $this->transaction('income', 'Egg Sales', 111, '2026-01-10');

        $this->actingAs($this->operator)
            ->get(route('finance.index', ['from' => 'not-a-date', 'type' => 'loan', 'category' => 'Nope']))
            ->assertOk()
            ->assertSee('₱111.00');
    }

    public function test_update_changes_transaction(): void
    {
        $txn = $this->transaction('expense', 'Labor', 500);

        $this->actingAs($this->operator)
            ->put(route('finance.update', $txn), [
                'type'     => 'expense',
                'category' => 'Utilities',
                'amount'   => 650,
                'date'     => '2026-03-01',
            ])
            ->assertRedirect(route('finance.index'));

        $txn->refresh();
        $this->assertSame('Utilities', $txn->category);
        $this->assertSame('650.00', $txn->amount);
        $this->assertSame('2026-03-01', $txn->date->toDateString());
    }

    public function test_invalid_update_reopens_edit_modal(): void
    {
        $txn = $this->transaction('expense', 'Labor', 500);

        $this->actingAs($this->operator)
            ->put(route('finance.update', $txn), ['type' => 'expense', 'category' => 'Labor', 'amount' => '', 'date' => '2026-03-01'])
            ->assertRedirect(route('finance.index'))
            ->assertSessionHas('reopen_edit_finance', $txn->id)
            ->assertSessionHasErrors(['amount']);
    }

    public function test_only_admin_can_delete(): void
    {
        $txn = $this->transaction('expense', 'Labor', 500);

        $this->actingAs($this->operator)
            ->delete(route('finance.destroy', $txn))
            ->assertForbidden();
        $this->assertDatabaseHas('finance_transactions', ['id' => $txn->id]);

        $this->actingAs($this->admin)
            ->delete(route('finance.destroy', $txn))
            ->assertRedirect(route('finance.index'));
        $this->assertDatabaseMissing('finance_transactions', ['id' => $txn->id]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('finance.index'))->assertRedirect(route('login'));
        $this->post(route('finance.store'), [])->assertRedirect(route('login'));
    }

    public function test_finance_link_is_in_sidebar(): void
    {
        $this->actingAs($this->operator)
            ->get(route('finance.index'))
            ->assertSee('href="' . route('finance.index') . '"', false);
    }
}
