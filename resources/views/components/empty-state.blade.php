@props(['icon' => 'home', 'title', 'message'])

<div {{ $attributes->class(['cm-empty-state']) }} role="status">
    @include('components.icon', ['name' => $icon])
    <h2>{{ $title }}</h2>
    <p>{{ $message }}</p>
</div>
