{{-- Block action. Expects $type ('post'|'comment') and $item. Hidden on your own content. --}}
@unless ($item['mine'])
    <form method="POST" action="{{ route('block.store', [$type, $item['id']]) }}" class="cm-block-form"
          data-confirm-submit data-confirm-title="Block this person?"
          data-confirm-message="You will no longer be able to interact with each other. Their posts will still appear in the feed to protect anonymity.">
        @csrf
        <button type="submit" class="cm-block-btn {{ isset($menuItem) && $menuItem ? 'cm-menu-item' : '' }}" aria-label="Block this {{ $type }}'s author" title="Block this {{ $type }}'s author">
            @include('components.icon', ['name' => 'shield'])
            <span>Block</span>
        </button>
    </form>
@endunless
