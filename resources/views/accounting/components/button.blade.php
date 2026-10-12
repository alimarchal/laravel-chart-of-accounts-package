<button {{ $attributes->merge(['type' => 'submit', 'class' => 'acc-btn acc-btn--primary']) }}>
    {{ $slot }}
</button>
