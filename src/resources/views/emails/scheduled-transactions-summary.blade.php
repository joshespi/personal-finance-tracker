@component('mail::message')
# Scheduled transactions recorded

Hi {{ $user->name }},

{{ $intro }}

@component('mail::table')
| Description | Amount | Type |
|:------------|-------:|:-----|
@foreach ($fired as $tx)
| {{ $tx->description }} | ${{ number_format($tx->amount, 2) }} | {{ $tx->typeLabel() }} |
@endforeach
@endcomponent

These entries have already been added to your accounts. Log in to review them.

@component('mail::button', ['url' => route('scheduled-transactions.index')])
View Scheduled Transactions
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
