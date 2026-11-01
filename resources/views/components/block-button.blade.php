{{-- "Block" link. Expects $type ('post'|'comment') and $item. Hidden on your own content. --}}
@unless ($item['mine'])
    <form method="POST" action="{{ route('block.store', [$type, $item['id']]) }}" class="cm-block-form"
          onsubmit="return confirm('Block this person? You will no longer see their posts or comments.');">
        @csrf
        <button type="submit" class="cm-block-btn" aria-label="Block this {{ $type }}'s author">Block</button>
    </form>
@endunless
