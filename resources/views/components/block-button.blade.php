{{-- Block action. Expects $type ('post'|'comment') and $item. Hidden on your own content. --}}
@unless ($item['mine'])
    <form method="POST" action="{{ route('block.store', [$type, $item['id']]) }}" class="cm-block-form"
          onsubmit="return confirm('Block this person? You can still see their posts and comments, but you cannot interact with them.');">
        @csrf
        <button type="submit" class="cm-block-btn {{ isset($menuItem) && $menuItem ? 'cm-menu-item' : '' }}" aria-label="Block this {{ $type }}'s author" title="Block this {{ $type }}'s author">
            @include('components.icon', ['name' => 'shield'])
            <span>Block</span>
        </button>
    </form>
@endunless
