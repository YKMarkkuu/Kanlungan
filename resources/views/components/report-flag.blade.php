{{-- Flag button. Opens the report bottom sheet. Expects $type ('post'|'comment') and $item (the post/comment array). --}}
@php($showLabel = $showLabel ?? false)
@php($buttonClass = $showLabel ? 'cm-menu-item' : 'cm-icon-btn cm-action-btn cm-push')
@if ($item['mine'])
    <button type="button" class="{{ $buttonClass }}" disabled aria-label="Report" title="You can't report your own {{ $type }}">@include('components.icon', ['name' => 'flag']) @if ($showLabel)<span>Report</span>@endif</button>
@elseif ($item['report'])
    <button type="button" class="{{ $buttonClass }}" disabled aria-label="Already reported" title="Already reported">@include('components.icon', ['name' => 'flag']) @if ($showLabel)<span>Report</span>@endif</button>
@else
    <button type="button" class="{{ $buttonClass }}" data-report-open data-report-action="{{ route('report.store', [$type, $item['id']]) }}" data-report-kind="{{ $type }}" aria-label="Report this {{ $type }}" title="Report this {{ $type }}">@include('components.icon', ['name' => 'flag']) @if ($showLabel)<span>Report</span>@endif</button>
@endif
