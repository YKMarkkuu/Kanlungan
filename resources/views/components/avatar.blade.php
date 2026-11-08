@props(['avatarPath' => null, 'name' => null])

<span {{ $attributes->class(['cm-avatar']) }} aria-hidden="true">
    @if ($avatarPath)
        <img src="{{ str_starts_with($avatarPath, 'avatars/') ? \Illuminate\Support\Facades\Storage::disk('supabase')->url($avatarPath) : asset($avatarPath) }}" alt="{{ $name ? $name.' profile picture' : '' }}" loading="lazy">
    @else
        @include('components.icon', ['name' => 'user'])
    @endif
</span>
