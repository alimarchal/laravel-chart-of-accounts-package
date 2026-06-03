@php $user = $user ?? null; @endphp
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-accounting::label for="name" value="Name" />
        <x-accounting::input id="name" type="text" name="name" class="mt-1 block w-full" :value="old('name', optional($user)->name)" required />
    </div>
    <div>
        <x-accounting::label for="email" value="Email" />
        <x-accounting::input id="email" type="email" name="email" class="mt-1 block w-full" :value="old('email', optional($user)->email)" required />
    </div>
    <div>
        <x-accounting::label for="password" value="{{ $user ? 'New Password (leave blank to keep current)' : 'Password' }}" />
        <x-accounting::input id="password" type="password" name="password" class="mt-1 block w-full" :required="!$user" />
    </div>
    <div>
        <x-accounting::label for="password_confirmation" value="Confirm Password" />
        <x-accounting::input id="password_confirmation" type="password" name="password_confirmation" class="mt-1 block w-full" :required="!$user" />
    </div>
    <div class="md:col-span-2">
        <x-accounting::label for="roles" value="Roles" />
        <select id="roles" name="roles[]" multiple class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            @foreach($roles as $role)
                <option value="{{ $role->name }}" {{ (isset($user) && $user->hasRole($role->name)) || in_array($role->name, old('roles', [])) ? 'selected' : '' }}>{{ $role->name }}</option>
            @endforeach
        </select>
    </div>
</div>
