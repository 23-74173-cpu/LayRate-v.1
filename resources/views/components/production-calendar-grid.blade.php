@props(['weekdays', 'label'])

<div class="w-full min-w-0 overflow-x-auto -mx-1 px-1">
    <table {{ $attributes->merge(['class' => 'production-calendar']) }} aria-label="{{ $label }}">
        <thead>
            <tr>
                @foreach($weekdays as $weekday)
                    <th scope="col" class="text-center">
                        <span class="hidden sm:inline">{{ $weekday }}</span>
                        <span class="sm:hidden">{{ substr($weekday, 0, 1) }}</span>
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>{{ $slot }}</tbody>
    </table>
</div>
