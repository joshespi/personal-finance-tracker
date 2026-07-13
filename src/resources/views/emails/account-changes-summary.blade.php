@component('mail::message')
# Your {{ strtolower($frequency->label()) }} account summary

Hi {{ $user->name }},

Here's what changed between {{ $since->format('M j') }} and {{ $until->format('M j, Y') }}.

@isset($data['budgeting'])
## Budgeting

- Income: ${{ number_format($data['budgeting']['deposits'], 2) }}
- Spending: ${{ number_format($data['budgeting']['withdrawals'], 2) }}
- Net: ${{ number_format($data['budgeting']['net'], 2) }}
- {{ $data['budgeting']['transactionCount'] }} transaction(s)
@endisset

@isset($data['investing'])
## Investing

- Bought: ${{ number_format($data['investing']['buys'], 2) }}
- Sold: ${{ number_format($data['investing']['sells'], 2) }}
- Fees: ${{ number_format($data['investing']['fees'], 2) }}
@if ($data['investing']['valueChange'] !== null)
- Portfolio value change: ${{ number_format($data['investing']['valueChange'], 2) }}
@endif
@endisset

@isset($data['net_worth'])
## Net Worth

- Current: ${{ number_format($data['net_worth']['current'], 2) }}
@if ($data['net_worth']['change'] !== null)
- Portfolio value change since {{ $since->format('M j') }}: ${{ number_format($data['net_worth']['change'], 2) }} *(cash, debt, and pension changes aren't reflected in this figure)*
@endif
@endisset

@isset($data['upcoming_scheduled'])
@if ($data['upcoming_scheduled']->isNotEmpty())
## Upcoming Scheduled Transactions

@component('mail::table')
| Description | Amount | Due |
|:------------|-------:|:----|
@foreach ($data['upcoming_scheduled'] as $tx)
| {{ $tx->description }} | ${{ number_format($tx->amount, 2) }} | {{ $tx->next_due_at->format('M j') }} |
@endforeach
@endcomponent
@endif
@endisset

@isset($data['category_changes'])
@if ($data['category_changes']->isNotEmpty())
## Spending By Category

@component('mail::table')
| Envelope | This period | Prior period | Change |
|:---------|------------:|-------------:|-------:|
@foreach ($data['category_changes'] as $row)
| {{ $row['envelope'] }} | ${{ number_format($row['current'], 2) }} | ${{ number_format($row['previous'], 2) }} | {{ $row['percentChange'] === null ? '—' : number_format($row['percentChange'], 1) . '%' }} |
@endforeach
@endcomponent
@endif
@endisset

@isset($data['warnings'])
@php
    $hasWarnings = $data['warnings']['overBudgetEnvelopes']->isNotEmpty()
        || $data['warnings']['lowBalanceAccounts']->isNotEmpty()
        || $data['warnings']['upcomingBills']->isNotEmpty()
        || $data['warnings']['emergencyFundBelowTarget'];
@endphp
@if ($hasWarnings)
## Needs Attention

@foreach ($data['warnings']['overBudgetEnvelopes'] as $row)
- **{{ $row['envelope'] }}** is over budget: ${{ number_format($row['spent'], 2) }} spent of ${{ number_format($row['target'], 2) }} target
@endforeach
@foreach ($data['warnings']['lowBalanceAccounts'] as $row)
- **{{ $row['account'] }}** has a negative balance: ${{ number_format($row['balance'], 2) }}
@endforeach
@foreach ($data['warnings']['upcomingBills'] as $row)
- **{{ $row['liability'] }}** payment of ${{ number_format($row['amount'], 2) }} due on day {{ $row['paymentDay'] }}
@endforeach
@if ($data['warnings']['emergencyFundBelowTarget'])
- Your emergency fund is below the 3-month target
@endif
@endif
@endisset

@component('mail::button', ['url' => route('dashboard')])
View Dashboard
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
