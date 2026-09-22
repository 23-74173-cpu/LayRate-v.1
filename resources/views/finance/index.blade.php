@extends('layouts.app')
@section('title', 'Finance')

@section('content')
<div class="space-y-5">

    <x-page-header title="Finance" subtitle="Track farm income, expenses, and net position" />

    {{-- ── Summary ── --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <x-kpi-card label="Total Income" icon="trending-up" cardGradient="linear-gradient(135deg,#16a34a,#2D7D46)" delay="0ms" :value="'₱' . number_format($totalIncome, 2)" />
        <x-kpi-card label="Total Expenses" icon="trending-down" cardGradient="linear-gradient(135deg,#dc2626,#9b1c24)" delay="60ms" :value="'₱' . number_format($totalExpense, 2)" />
        <x-kpi-card label="Net" icon="wallet" cardGradient="{{ $net >= 0 ? 'linear-gradient(135deg,#0075de,#1D4E8F)' : 'linear-gradient(135deg,#dc2626,#9b1c24)' }}" delay="120ms" :value="($net >= 0 ? '₱' : '-₱') . number_format(abs($net), 2)" />
    </div>

    {{-- ── Filter ── --}}
    <form method="GET" action="{{ route('finance.index') }}" class="flex flex-wrap items-end gap-3 rounded-xl border p-4" style="background-color: #ffffff; border-color: #e6e6e6;">
        <div>
            <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">TYPE</label>
            <select name="type" class="border rounded-lg px-3 py-2 text-sm bg-white" style="border-color: #e6e6e6;">
                <option value="all" @selected($type === 'all')>All</option>
                @foreach($types as $t)
                <option value="{{ $t }}" @selected($type === $t)>{{ ucfirst($t) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">FROM</label>
            <input type="date" name="from" value="{{ $from }}" class="border rounded-lg px-3 py-2 text-sm bg-white" style="border-color: #e6e6e6;">
        </div>
        <div>
            <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">TO</label>
            <input type="date" name="to" value="{{ $to }}" class="border rounded-lg px-3 py-2 text-sm bg-white" style="border-color: #e6e6e6;">
        </div>
        <x-button type="submit" class="px-5 py-2">Filter</x-button>
        @if($from || $to || $type !== 'all')
        <a href="{{ route('finance.index') }}" class="text-sm px-3 py-2" style="color: #6B7280;">Clear</a>
        @endif
    </form>

    {{-- ── Add Transaction ── --}}
    <div class="rounded-xl border p-6" style="background-color: #ffffff; border-color: #e6e6e6;">
        <h2 class="text-sm font-medium text-[#333333] mb-4 flex items-center gap-2">
            <i data-lucide="plus-circle" class="w-4 h-4 text-[#6B7280]"></i>
            Record Transaction
        </h2>
        <form method="POST" action="{{ route('finance.store') }}" class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            @csrf
            <div>
                <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">TYPE</label>
                <select name="type" required class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white" style="border-color: #e6e6e6;">
                    <option value="">Select…</option>
                    @foreach($types as $t)
                    <option value="{{ $t }}" @selected(old('type') === $t)>{{ ucfirst($t) }}</option>
                    @endforeach
                </select>
                <x-input-error name="type" />
            </div>
            <div>
                <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">CATEGORY</label>
                <select name="category" required class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white" style="border-color: #e6e6e6;">
                    <option value="">Select…</option>
                    @foreach($categories as $cat)
                    <option value="{{ $cat }}" @selected(old('category') === $cat)>{{ $cat }}</option>
                    @endforeach
                </select>
                <x-input-error name="category" />
            </div>
            <div>
                <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">AMOUNT (₱)</label>
                <input type="number" name="amount" step="0.01" min="0.01" required value="{{ old('amount') }}"
                       class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white" style="border-color: #e6e6e6;">
                <x-input-error name="amount" />
            </div>
            <div>
                <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">DATE</label>
                <input type="date" name="date" required value="{{ old('date', today()->toDateString()) }}"
                       class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white" style="border-color: #e6e6e6;">
                <x-input-error name="date" />
            </div>
            <div>
                <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">CAGE (OPTIONAL)</label>
                <select name="cage_id" class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white" style="border-color: #e6e6e6;">
                    <option value="">Farm-wide</option>
                    @foreach($cages as $cage)
                    <option value="{{ $cage->id }}" @selected(old('cage_id') == $cage->id)>{{ $cage->cage_code }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <x-button type="submit" class="w-full py-2.5">Add</x-button>
            </div>
            <div class="sm:col-span-3 lg:col-span-6">
                <label class="block text-xs tracking-wider text-[#6B7280] mb-1.5">DESCRIPTION (OPTIONAL)</label>
                <input type="text" name="description" maxlength="1000" value="{{ old('description') }}"
                       placeholder="e.g. 50kg layer feed from ABC Supplier"
                       class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white" style="border-color: #e6e6e6;">
                <x-input-error name="description" />
            </div>
        </form>
    </div>

    {{-- ── Transactions List ── --}}
    <div class="rounded-xl border overflow-hidden overflow-x-auto" style="background-color: #ffffff; border-color: #e6e6e6;">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left border-b" style="border-color: #e6e6e6; color: #6B7280;">
                    <th class="px-5 py-3 font-medium">Date</th>
                    <th class="px-5 py-3 font-medium">Type</th>
                    <th class="px-5 py-3 font-medium">Category</th>
                    <th class="px-5 py-3 font-medium">Cage</th>
                    <th class="px-5 py-3 font-medium">Description</th>
                    <th class="px-5 py-3 font-medium text-right">Amount</th>
                    <th class="px-5 py-3 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $txn)
                <tr class="border-b" style="border-color: #f3f4f6;">
                    <td class="px-5 py-3" style="color: #1f1f1f;">{{ $txn->date->display() }}</td>
                    <td class="px-5 py-3">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                              style="background-color: {{ $txn->type === 'income' ? '#e8f5ec' : '#fbe4e6' }}; color: {{ $txn->type === 'income' ? '#1f6b3a' : '#9b1c24' }};">
                            {{ ucfirst($txn->type) }}
                        </span>
                    </td>
                    <td class="px-5 py-3" style="color: #1f1f1f;">{{ $txn->category }}</td>
                    <td class="px-5 py-3" style="color: #6B7280;">{{ $txn->cage?->cage_code ?? 'Farm-wide' }}</td>
                    <td class="px-5 py-3" style="color: #6B7280;">{{ $txn->description ?? '—' }}</td>
                    <td class="px-5 py-3 text-right font-semibold" style="color: {{ $txn->type === 'income' ? '#1f6b3a' : '#9b1c24' }};">
                        {{ $txn->type === 'income' ? '+' : '-' }}₱{{ number_format($txn->amount, 2) }}
                    </td>
                    <td class="px-5 py-3 text-right">
                        <div class="flex items-center justify-end gap-1">
                            <button onclick="openFinanceEdit({{ $txn->id }}, '{{ $txn->type }}', {{ Js::from($txn->category) }}, {{ $txn->amount }}, '{{ $txn->date->toDateString() }}', {{ $txn->cage_id ?? 'null' }}, {{ Js::from($txn->description) }})"
                                    class="p-1.5 rounded hover:bg-black/5 transition-colors" style="color: #615d59;" aria-label="Edit transaction">
                                <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                            </button>
                            <form method="POST" action="{{ route('finance.destroy', $txn) }}"
                                  data-confirm="Delete this transaction?" data-confirm-action="Delete" data-confirm-severity="destructive">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-1.5 rounded hover:bg-red-50 transition-colors" style="color: #a39e98;" aria-label="Delete transaction">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="p-10 text-center text-sm" style="color: #a39e98;">
                        No transactions recorded yet.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-paginator :paginator="$transactions" />

    {{-- ── Edit Transaction Modal ── --}}
    <div id="financeEditModal" data-modal class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" style="display: none;">
        <div class="absolute inset-0" style="background-color: rgba(0,0,0,0.35); backdrop-filter: blur(4px);" onclick="closeFinanceEdit()"></div>
        <div class="relative w-full max-w-md rounded-2xl p-6 max-h-screen max-h-[100dvh] overflow-y-auto" style="background-color: #ffffff; box-shadow: rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px;">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-[20px] font-semibold leading-[1.4] tracking-[-0.125px]" style="color: #1f1f1f;">Edit Transaction</h2>
                <button onclick="closeFinanceEdit()" class="p-1.5 rounded-full hover:bg-black/5 transition-colors" aria-label="Close">
                    <i data-lucide="x" class="w-5 h-5" style="color: #615d59;"></i>
                </button>
            </div>
            <form id="financeEditForm" method="POST" action="" class="space-y-3">
                @csrf
                @method('PUT')
                <select name="type" id="financeEditType" required class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white" style="border-color: #e6e6e6;">
                    @foreach($types as $t)
                    <option value="{{ $t }}">{{ ucfirst($t) }}</option>
                    @endforeach
                </select>
                <x-input-error name="type" />
                <select name="category" id="financeEditCategory" required class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white" style="border-color: #e6e6e6;">
                    @foreach($categories as $cat)
                    <option value="{{ $cat }}">{{ $cat }}</option>
                    @endforeach
                </select>
                <x-input-error name="category" />
                <input type="number" name="amount" id="financeEditAmount" step="0.01" min="0.01" required
                       class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white" style="border-color: #e6e6e6;">
                <x-input-error name="amount" />
                <input type="date" name="date" id="financeEditDate" required
                       class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white" style="border-color: #e6e6e6;">
                <x-input-error name="date" />
                <select name="cage_id" id="financeEditCage" class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white" style="border-color: #e6e6e6;">
                    <option value="">Farm-wide</option>
                    @foreach($cages as $cage)
                    <option value="{{ $cage->id }}">{{ $cage->cage_code }}</option>
                    @endforeach
                </select>
                <input type="text" name="description" id="financeEditDescription" maxlength="1000"
                       class="w-full border rounded-lg px-3 py-2.5 text-sm bg-white" style="border-color: #e6e6e6;">
                <x-input-error name="description" />
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" onclick="closeFinanceEdit()"
                            class="px-4 py-2 text-sm font-medium rounded-lg transition-colors"
                            style="color: #1f1f1f; border: 1px solid #e6e6e6;"
                            onmouseover="this.style.backgroundColor='#f6f5f4'"
                            onmouseout="this.style.backgroundColor='transparent'">
                        Cancel
                    </button>
                    <x-button type="submit" class="px-5 py-2">Save</x-button>
                </div>
            </form>
        </div>
    </div>

@if(session('reopen_edit_finance'))
@php $editFinance = \App\Models\FinanceTransaction::find(session('reopen_edit_finance')); @endphp
@if($editFinance)
<x-modal-reopen modal-id="financeEditModal" session-key="reopen_edit_finance" guard="editFinance">
    openFinanceEdit(
        {{ $editFinance->id }},
        '{{ $editFinance->type }}',
        {{ Js::from($editFinance->category) }},
        {{ $editFinance->amount }},
        '{{ $editFinance->date->toDateString() }}',
        {{ $editFinance->cage_id ?? 'null' }},
        {{ Js::from($editFinance->description) }}
    );
</x-modal-reopen>
@endif
@endif

</div>

<script>
function openFinanceEdit(id, type, category, amount, date, cageId, description) {
    document.getElementById('financeEditForm').action = '/finance/' + id;
    document.getElementById('financeEditType').value = type;
    document.getElementById('financeEditCategory').value = category;
    document.getElementById('financeEditAmount').value = amount;
    document.getElementById('financeEditDate').value = date;
    document.getElementById('financeEditCage').value = cageId === null ? '' : cageId;
    document.getElementById('financeEditDescription').value = description || '';
    document.getElementById('financeEditModal').style.display = 'flex';
}
function closeFinanceEdit() {
    document.getElementById('financeEditModal').style.display = 'none';
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeFinanceEdit();
});
</script>
@endsection
