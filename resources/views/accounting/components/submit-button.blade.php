<div class="acc-filters-actions">
    <button type="submit" class="acc-btn acc-btn--primary">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15z"/></svg>
        {{ __('Apply filters') }}
    </button>
    <a href="{{ request()->url() }}" class="acc-btn">{{ __('Reset') }}</a>
</div>
