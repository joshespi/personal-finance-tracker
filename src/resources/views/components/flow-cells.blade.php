@props(['inflow', 'amount', 'cellClass' => 'px-6 py-3'])

{{-- Paired Outflow / Inflow table cells: the amount shows in one column (red for
     outflow, green for inflow) and the other column shows an em-dash placeholder.
     $cellClass lets a dense table (the cash ledger) tighten the padding without
     changing it for every caller. --}}
<td class="{{ $cellClass }} text-right font-mono font-semibold text-red-600 dark:text-red-400">
    @unless ($inflow){{ $amount }}@else<span class="text-gray-300 dark:text-gray-600">—</span>@endunless
</td>
<td class="{{ $cellClass }} text-right font-mono font-semibold text-green-600 dark:text-green-400">
    @if ($inflow){{ $amount }}@else<span class="text-gray-300 dark:text-gray-600">—</span>@endif
</td>
