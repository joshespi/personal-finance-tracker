@props(['action', 'confirm', 'label' => 'Delete', 'variant' => 'link'])

{{-- DELETE form behind a confirm. The onsubmit attribute is parsed by form-guards.js to show the
     styled dialog, so it has to stay an inline `return confirm('…')`; Js::from() makes the message
     safe for both the attribute and the JS string. --}}
<form method="POST" action="{{ $action }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from($confirm) }})" {{ $attributes }}>
    @csrf
    @method('DELETE')
    @if ($variant === 'button')
        <x-btn variant="danger" type="submit">{{ $label }}</x-btn>
    @else
        <button type="submit" class="text-red-600 dark:text-red-400 hover:underline text-xs">{{ $label }}</button>
    @endif
</form>
