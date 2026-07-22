<div>
    @include('livewire.partials.balance-card', ['bal' => $this->balances, 'demo' => $demo, 'workingLabel' => 'Working Balance'])

    @include('livewire.partials.scheduled-panel', [
        'scheduled'   => $this->scheduled,
        'demo'        => $demo,
        'heading'     => 'Scheduled',
        'emptyText'   => 'No scheduled transactions linked to this account.',
        'showAccount' => false,
    ])

    @include('livewire.partials.record-transaction-form', [
        'envelopes'        => $this->envelopes,
        'incomeCategories' => $this->incomeCategories,
        'accounts'         => null,
        'account'          => $account,
        'demo'             => $demo,
    ])

    @include('livewire.partials.transaction-table', [
        'heading'            => 'Transactions',
        'transactions'       => $this->transactions,
        'editingId'          => $editingId,
        'filter'             => $filter,
        'demo'               => $demo,
        'envelopes'          => $this->envelopes,
        'incomeCategories'   => $this->incomeCategories,
        'showAccountColumn'  => false,
        'accounts'           => null,
        'showAccountFilter'  => false,
        'showStatusFilter'   => false,
        'accountFilter'      => null,
        'statusFilter'       => null,
        'emptyFilteredText'  => 'No transactions match the current filter.',
        'emptyText'          => 'No transactions yet.',
    ])
</div>
