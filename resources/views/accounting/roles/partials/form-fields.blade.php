@php $role = $role ?? null; @endphp
<div class="grid grid-cols-1 gap-4">
    <div>
        <x-accounting::label for="name" value="Role Name" />
        <x-accounting::input id="name" type="text" name="name" class="mt-1 block w-full" :value="old('name', optional($role)->name)" required />
    </div>
    <div>
        <x-accounting::label for="permissions" value="Permissions" />
        <div class="mt-2 grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach($permissions as $group => $groupPerms)
            <div class="bg-gray-50 rounded-lg p-3">
                <div class="flex items-center gap-2 mb-2">
                    <input type="checkbox" class="group-check rounded border-gray-300" data-group="{{ $group }}" id="group_{{ $group }}"
                        {{ (isset($role) && $role->permissions->whereIn('name', $groupPerms->pluck('name'))->count() === $groupPerms->count()) ? 'checked' : '' }}>
                    <label for="group_{{ $group }}" class="text-xs font-semibold uppercase tracking-wider text-gray-600 cursor-pointer">{{ $group }}</label>
                </div>
                @foreach($groupPerms as $perm)
                <div class="flex items-center gap-2 ml-4 mt-1">
                    <input type="checkbox" name="permissions[]" value="{{ $perm->name }}"
                        id="perm_{{ str_replace(['.', '-'], '_', $perm->name) }}"
                        class="perm-check rounded border-gray-300" data-group="{{ $group }}"
                        {{ (isset($role) && $role->permissions->contains('name', $perm->name)) || in_array($perm->name, old('permissions', [])) ? 'checked' : '' }}>
                    <label for="perm_{{ str_replace(['.', '-'], '_', $perm->name) }}" class="text-sm text-gray-700 cursor-pointer">{{ $perm->name }}</label>
                </div>
                @endforeach
            </div>
            @endforeach
        </div>
    </div>
</div>
@push('scripts')
<script>
document.querySelectorAll('.group-check').forEach(function(groupCheck) {
    groupCheck.addEventListener('change', function() {
        const group = this.dataset.group;
        document.querySelectorAll('.perm-check[data-group="' + group + '"]').forEach(function(perm) {
            perm.checked = groupCheck.checked;
        });
    });
});
document.querySelectorAll('.perm-check').forEach(function(permCheck) {
    permCheck.addEventListener('change', function() {
        const group = this.dataset.group;
        const perms = document.querySelectorAll('.perm-check[data-group="' + group + '"]');
        const allChecked = Array.from(perms).every(p => p.checked);
        document.querySelector('.group-check[data-group="' + group + '"]').checked = allChecked;
    });
});
</script>
@endpush
