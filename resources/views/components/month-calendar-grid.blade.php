@props(['weekdays', 'label', 'class' => ''])

<div class="overflow-x-auto -mx-1 px-1 {{ $class }}">
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
