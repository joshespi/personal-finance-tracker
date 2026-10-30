@props(['title', 'crumbs' => []])

{{-- Breadcrumb line + page title for the layout's header slot. $crumbs is a list of [label, url] pairs. --}}
<div>
    @if ($crumbs)
        <p class="text-sm text-gray-500 dark:text-gray-400">
            @foreach ($crumbs as [$label, $url])
                @if (! $loop->first) &rsaquo; @endif
                <a href="{{ $url }}" class="hover:underline">{{ $label }}</a>
            @endforeach
        </p>
    @endif
    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ $title }}</h2>
</div>
