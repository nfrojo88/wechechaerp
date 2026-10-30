@props([
    'label'       => null,
    'name'        => null,
    'type'        => 'text', // text, number, tel, email, date, select, textarea
    'value'       => null,
    'required'    => false,
    'placeholder' => null,
    'hint'        => null,
    'options'     => [], // Key-value or array for select
    'inputmode'   => null,
])

@php
    $fieldId = 'field_' . $name . '_' . \Illuminate\Support\Str::random(4);
    $val = old($name, $value);
    
    // Auto-detect inputmode
    $computedInputmode = $inputmode;
    if (!$computedInputmode) {
        if ($type === 'number') $computedInputmode = 'decimal';
        elseif ($type === 'tel') $computedInputmode = 'tel';
        elseif ($type === 'email') $computedInputmode = 'email';
    }
@endphp

<div {{ $attributes->merge(['class' => 'wc-form-field mb-3']) }}>
    <label for="{{ $fieldId }}" class="form-label d-flex justify-content-between align-items-center mb-1 fw-semibold text-dark" style="font-size: var(--wc-text-xs); text-transform: uppercase; letter-spacing: 0.04em;">
        <span>
            {{ $label }}
            @if($required)
                <span class="text-danger ms-0.5">*</span>
            @endif
        </span>
    </label>

    @if($type === 'textarea')
        <textarea name="{{ $name }}" 
                  id="{{ $fieldId }}" 
                  class="form-control @error($name) is-invalid @enderror"
                  style="border-radius: var(--wc-radius-sm); border-color: var(--wc-border); min-height: 88px; font-size: var(--wc-text-base);"
                  placeholder="{{ $placeholder }}"
                  {{ $required ? 'required' : '' }}>{{ $val }}</textarea>

    @elseif($type === 'select')
        <select name="{{ $name }}" 
                id="{{ $fieldId }}" 
                class="form-select @error($name) is-invalid @enderror"
                style="height: var(--wc-touch-target); border-radius: var(--wc-radius-sm); border-color: var(--wc-border); font-size: var(--wc-text-base);"
                {{ $required ? 'required' : '' }}>
            @if($placeholder)
                <option value="">{{ $placeholder }}</option>
            @endif
            @foreach($options as $optKey => $optVal)
                <option value="{{ $optKey }}" {{ (string)$val === (string)$optKey ? 'selected' : '' }}>
                    {{ $optVal }}
                </option>
            @endforeach
            {{ $slot }}
        </select>

    @else
        <input type="{{ $type }}" 
               name="{{ $name }}" 
               id="{{ $fieldId }}" 
               value="{{ $val }}"
               @if($computedInputmode) inputmode="{{ $computedInputmode }}" @endif
               class="form-control @error($name) is-invalid @enderror"
               style="height: var(--wc-touch-target); border-radius: var(--wc-radius-sm); border-color: var(--wc-border); font-size: var(--wc-text-base);"
               placeholder="{{ $placeholder }}"
               {{ $required ? 'required' : '' }}>
    @endif

    @error($name)
        <div class="text-danger small mt-1 d-flex align-items-center gap-1" style="font-size: var(--wc-text-xs);">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span>{{ $message }}</span>
        </div>
    @enderror

    @if($hint && !$errors->has($name))
        <div class="text-muted small mt-1" style="font-size: var(--wc-text-xs);">
            {{ $hint }}
        </div>
    @endif
</div>
