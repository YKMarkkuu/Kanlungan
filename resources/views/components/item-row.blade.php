{{-- One Marketplace item row. $i is the row index ("__i__" in the JS template). --}}
@php($label = $label ?? '')
@php($price = $price ?? '')
<div class="cm-item-slot" data-item-row>
    <div class="cm-item-slot__inner">
        <div class="cm-item-row">
            <div class="cm-item-row__name" data-item-name>
                <label class="cm-visually-hidden" for="items-{{ $i }}-label">Item name</label>
                <input class="input" type="text" id="items-{{ $i }}-label" name="items[{{ $i }}][label]"
                       maxlength="40" placeholder="Item name" autocomplete="off" value="{{ $label }}" disabled>
            </div>

            <div class="cm-item-row__price">
                <label class="cm-visually-hidden" for="items-{{ $i }}-price">Price in pesos</label>
                <div class="cm-price-input">
                    <span class="cm-price-input__prefix" aria-hidden="true">₱</span>
                    <input class="input" type="text" id="items-{{ $i }}-price" name="items[{{ $i }}][price]"
                           inputmode="numeric" pattern="[0-9]*" maxlength="7" placeholder="Price"
                           autocomplete="off" value="{{ $price }}" data-item-price disabled>
                </div>
            </div>

            <div class="cm-item-row__remove">
                <button type="button" class="cm-icon-btn" data-item-remove aria-label="Remove item" disabled>
                    @include('components.icon', ['name' => 'close'])
                </button>
            </div>
        </div>
    </div>
</div>
