<select {{ $attributes->merge(['class' => 'mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm']) }}>
    {{ $slot }}
</select>
