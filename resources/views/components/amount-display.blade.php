@props(['amount', 'currency' => null, 'class' => 'font-medium'])

<span {{ $attributes->merge(['class' => $class]) }}>
    {{ $currency ? $currency . ' ' : '' }}{{ number_format((float)$amount, 2, '.', ',') }}
</span>
