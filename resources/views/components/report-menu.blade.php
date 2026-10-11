{{-- Flag button + violation picker. Expects $type ('post'|'comment') and $item (the post/comment array). --}}
@if (! $item['mine'] && ! $item['report'])
    <details class="cm-report-menu cm-push">
        <summary class="cm-icon-btn" aria-label="Report">@include('components.icon', ['name' => 'flag'])</summary>
        <form method="POST" action="{{ route('report.store', [$type, $item['id']]) }}" class="card cm-report-menu__panel">
            @csrf
            <strong class="text-small">What's wrong with this {{ $type }}?</strong>
            @foreach (\App\Models\Report::CATEGORIES as $category)
                <label class="cm-report-menu__option">
                    <input type="radio" name="category" value="{{ $category }}" required>
                    <span>{{ $category }}</span>
                </label>
            @endforeach
            <button type="submit" class="btn btn--primary">Report</button>
        </form>
    </details>
@endif
