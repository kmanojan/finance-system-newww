@props([
    'name' => 'payment_method',
    'id' => null,
    'selected' => 'Normal',
    'required' => true,
    'class' => 'form-control',
    'style' => '',
    'onchange' => null
])

@php
    $elementId = $id ?? 'pm_selector_' . uniqid();
    $modes = [
        'Normal' => ['icon' => 'receipt-outline', 'label' => 'Normal'],
        'Bank Transfer' => ['icon' => 'swap-horizontal-outline', 'label' => 'Bank Transfer'],
        'Petty Cash' => ['icon' => 'wallet-outline', 'label' => 'Petty Cash'],
        'Credit Card' => ['icon' => 'card-outline', 'label' => 'Credit Card'],
        'Cash' => ['icon' => 'cash-outline', 'label' => 'Cash'],
    ];
@endphp

<div class="payment-mode-selector-wrapper" style="position: relative; width: 100%;">
    <select 
        name="{{ $name }}" 
        id="{{ $elementId }}" 
        class="{{ $class }}" 
        style="{{ $style }}"
        @if($required) required @endif
        @if($onchange) onchange="{{ $onchange }}" @endif
    >
        @foreach($modes as $value => $info)
            <option value="{{ $value }}" {{ ($selected ?? 'Normal') == $value ? 'selected' : '' }}>
                {{ $info['label'] }}
            </option>
        @endforeach
    </select>
</div>
