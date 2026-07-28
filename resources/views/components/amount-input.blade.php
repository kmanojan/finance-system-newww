@props(['name', 'id' => null, 'value' => '', 'required' => false, 'readonly' => false, 'placeholder' => '0.00', 'class' => 'form-control', 'style' => ''])

<div class="amount-input-wrapper" style="position: relative; flex-grow: 1;">
    <input 
        type="text" 
        {{ $id ? "id={$id}" : '' }}
        class="{{ $class }} amount-display-input" 
        style="{{ $style }}"
        placeholder="{{ $placeholder }}" 
        value="{{ $value ? number_format((float)$value, 2, '.', ',') : '' }}"
        {{ $required ? 'required' : '' }}
        {{ $readonly ? 'readonly' : '' }}
        oninput="formatAmountInput(this)"
        onblur="formatAmountBlur(this)"
    >
    <input 
        type="hidden" 
        name="{{ $name }}" 
        class="amount-hidden" 
        value="{{ $value }}"
    >
</div>

@once
<script>
    function formatAmountInput(input) {
        // Remove non-digit and non-decimal characters
        let val = input.value.replace(/[^0-9.]/g, '');
        
        // Ensure only one decimal point
        const parts = val.split('.');
        if (parts.length > 2) {
            parts.pop();
            val = parts.join('.');
        }
        
        // Limit decimal places to 2 while typing
        if (parts.length === 2 && parts[1].length > 2) {
            parts[1] = parts[1].substring(0, 2);
            val = parts.join('.');
        }

        // Update hidden input with raw numerical value
        const hiddenInput = input.parentElement.querySelector('.amount-hidden');
        hiddenInput.value = val;
        hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));

        // Format for display (if integer part exists)
        if (parts[0].length > 0) {
            parts[0] = parseInt(parts[0], 10).toLocaleString('en-US');
            input.value = parts.join('.');
        } else {
            input.value = val;
        }
    }

    function formatAmountBlur(input) {
        let val = input.parentElement.querySelector('.amount-hidden').value;
        if (val && !isNaN(val)) {
            input.value = parseFloat(val).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            input.parentElement.querySelector('.amount-hidden').value = parseFloat(val).toFixed(2);
        } else {
            input.value = '';
            input.parentElement.querySelector('.amount-hidden').value = '';
        }
    }
</script>
@endonce
