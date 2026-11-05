<li class="cm-ledger__row">
    <span class="cm-ledger__icon cm-ledger__icon--{{ $row['tone'] }}" aria-hidden="true">@include('components.icon', ['name' => $row['icon']])</span>
    <span class="cm-ledger__text">
        <span class="cm-ledger__label">{{ $row['label'] }}</span>
        <span class="cm-ledger__time">{{ $row['time'] }}</span>
    </span>
    <strong class="cm-ledger__amount cm-ledger__amount--{{ $row['tone'] }}" aria-label="{{ $row['spoken'] }}">{{ $row['amount_text'] }}</strong>
</li>
