@props(['action', 'method' => 'POST', 'submit', 'cancel'])

{{-- White card holding a create/edit form: CSRF + method spoofing, the fields (slot), then Save / Cancel. --}}
<div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
    <form method="POST" action="{{ $action }}" {{ $attributes->merge(['class' => 'space-y-6']) }}>
        @csrf
        @if ($method !== 'POST')
            @method($method)
        @endif
        {{ $slot }}
        <div class="flex items-center gap-4">
            <x-primary-button>{{ $submit }}</x-primary-button>
            <a href="{{ $cancel }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Cancel</a>
        </div>
    </form>
</div>
