@props(['name', 'label' => null])

<ion-icon
    name="{{ $name }}"
    {{ $attributes->class('app-icon') }}
    @if ($label) aria-label="{{ $label }}" @else aria-hidden="true" @endif
></ion-icon>
