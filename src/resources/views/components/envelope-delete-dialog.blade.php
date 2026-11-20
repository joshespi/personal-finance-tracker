@props(['envelopes'])

{{-- Delete-an-envelope dialog with an optional "roll its transactions into another envelope"
     picker. One per page; open it from any element carrying data-id / data-name / data-action:
         @click="$dispatch('delete-envelope', { ...$el.dataset })"
     $envelopes is the list of possible targets — the one being deleted is hidden from it. --}}
<div x-data="{ id: '', name: '', action: '', target: '' }"
     x-on:delete-envelope.window="id = $event.detail.id; name = $event.detail.name; action = $event.detail.action; target = ''; $refs.dialog.showModal()">
    <dialog x-ref="dialog"
            class="rounded-lg p-0 shadow-xl backdrop:bg-black/50 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 w-[calc(100%-2rem)] max-w-md">
        <form method="POST" :action="action" class="p-6">
            @csrf
            @method('DELETE')

            <h2 class="text-base font-semibold">Delete <span x-text="name"></span>?</h2>

            <label for="envelope-delete-reassign" class="mt-4 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Move its transactions to
            </label>
            <x-select-input id="envelope-delete-reassign" name="reassign_to" x-model="target" class="text-sm">
                <option value="">Don't move them</option>
                @foreach ($envelopes as $option)
                    <option value="{{ $option->id }}"
                            :hidden="id === '{{ $option->id }}'"
                            :disabled="id === '{{ $option->id }}'">{{ $option->name }}</option>
                @endforeach
            </x-select-input>

            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300"
               x-text="target === ''
                   ? 'Its assigned amounts will be deleted, and its spending will be left without an envelope.'
                   : 'Its assigned amounts, spending and scheduled transactions will move to the selected envelope, along with its balance.'"></p>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button @click="$refs.dialog.close()">Cancel</x-secondary-button>
                <x-danger-button x-text="target === '' ? 'Delete' : 'Move & delete'">Delete</x-danger-button>
            </div>
        </form>
    </dialog>
</div>
