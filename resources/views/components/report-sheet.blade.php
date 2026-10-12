{{-- Report bottom sheet (design screen 6). One per page; community.js fills in the target. --}}
<div class="cm-sheet" id="report-sheet" hidden>
    <div class="cm-sheet__backdrop" data-report-close></div>
    <form method="POST" action="" class="cm-sheet__panel" id="report-form" role="dialog" aria-modal="true" aria-labelledby="report-title">
        @csrf
        <h2 class="cm-sheet__title" id="report-title">Report this post</h2>

        <fieldset class="cm-sheet__reasons cm-fieldset">
            <legend class="cm-visually-hidden">Reason</legend>
            @foreach (\App\Models\Report::CATEGORIES as $category)
                <label class="cm-sheet__reason">
                    <input type="radio" name="category" value="{{ $category }}" required>
                    <span>{{ $category }}</span>
                </label>
            @endforeach
        </fieldset>

        <input class="input" type="text" name="details" maxlength="255" placeholder="Add details (optional)" autocomplete="off">

        <button type="submit" class="btn btn--primary btn--block">Send report</button>
        <button type="button" class="link-btn cm-sheet__cancel" data-report-close>Cancel</button>
        <p class="text-small text-muted cm-sheet__note">Others can't see that you reported.</p>
    </form>
</div>
