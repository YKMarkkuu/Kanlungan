@props(['avatarPath' => null, 'name' => null])

<span {{ $attributes->class(['cm-avatar']) }} aria-hidden="true">
    @if ($avatarPath)
        <img src="{{ asset($avatarPath) }}" alt="{{ $name ? $name.' profile picture' : '' }}" loading="lazy">
    @else
        @include('components.icon', ['name' => 'user'])
    @endif
</span>
