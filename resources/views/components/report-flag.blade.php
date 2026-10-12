{{-- Flag button. Opens the report bottom sheet. Expects $type ('post'|'comment') and $item (the post/comment array). --}}
@if ($item['mine'])
    <button type="button" class="cm-icon-btn cm-push" disabled aria-label="Report" title="You can't report your own {{ $type }}">@include('components.icon', ['name' => 'flag'])</button>
@elseif ($item['report'])
    <button type="button" class="cm-icon-btn cm-push" disabled aria-label="Already reported" title="Already reported">@include('components.icon', ['name' => 'flag'])</button>
@else
    <button type="button" class="cm-icon-btn cm-push" data-report-open data-report-action="{{ route('report.store', [$type, $item['id']]) }}" data-report-kind="{{ $type }}" aria-label="Report this {{ $type }}">@include('components.icon', ['name' => 'flag'])</button>
@endif
