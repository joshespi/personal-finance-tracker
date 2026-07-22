<div>
    @include('livewire.partials.balance-card', ['bal' => $this->balances, 'demo' => $demo, 'workingLabel' => 'Total Working Balance'])

    @include('livewire.partials.scheduled-panel', [
        'scheduled'   => $this->scheduled,
        'demo'        => $demo,
        'heading'     => 'Upcoming & Scheduled',
        'emptyText'   => 'No scheduled transactions yet.',
        'showAccount' => true,
    ])

    @include('livewire.partials.record-transaction-form', [
        'envelopes'        => $this->envelopes,
        'incomeCategories' => $this->incomeCategories,
        'accounts'         => $this->accounts,
        'account'          => null,
        'demo'             => $demo,
    ])

    @include('livewire.partials.transaction-table', [
        'heading'            => 'All Transactions',
        'transactions'       => $this->transactions,
        'editingId'          => $editingId,
        'filter'             => $filter,
        'demo'               => $demo,
        'envelopes'          => $this->envelopes,
        'incomeCategories'   => $this->incomeCategories,
        'showAccountColumn'  => true,
        'accounts'           => $this->accounts,
        'showAccountFilter'  => true,
        'showStatusFilter'   => true,
        'accountFilter'      => $accountFilter,
        'statusFilter'       => $statusFilter,
        'emptyFilteredText'  => 'No transactions match the current filter.',
        'emptyText'          => 'No transactions yet.',
    ])
</div>
