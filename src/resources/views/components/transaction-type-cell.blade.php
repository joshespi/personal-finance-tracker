@props(['transaction'])

{{-- Type column for a cash-ledger row. A transfer leg (one that has a linked
     counterpart) shows a "Transfer to/from <account>" badge linking to the other
     account; everything else shows a plain Deposit or Withdrawal badge. --}}
@php $counterpart = $transaction->transferCounterpart(); @endphp
<td class="px-6 py-3">
    @if ($counterpart)
        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 dark:bg-indigo-900/40 text-indigo-800 dark:text-indigo-300">
            Transfer {{ $transaction->type === 'withdrawal' ? 'to' : 'from' }}
        </span>
        <a href="{{ route('cash-accounts.show', $counterpart->cash_account_id) }}"
           wire:click.stop
           class="block text-xs text-indigo-600 dark:text-indigo-400 hover:underline mt-0.5">
            {{ $demo->n($counterpart->cashAccount->name) }}
        </a>
    @elseif ($transaction->type === 'deposit')
        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300">Deposit</span>
    @else
        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300">Withdrawal</span>
    @endif
</td>
