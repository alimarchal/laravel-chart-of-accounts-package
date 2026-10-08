<div class="space-y-2">
    <label class="text-sm block md:w-1/4"><span class="text-gray-700">Grade (when nobody is picked below)</span><select name="salary_grade_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="">Every active employee</option>@foreach ($grades as $grade)<option value="{{ $grade->id }}">{{ $grade->code }} {{ $grade->name }}</option>@endforeach</select></label>
    <details class="rounded-md border p-3 text-sm" x-data>
        <summary class="cursor-pointer">Pick employees</summary>
        <div class="mt-2 flex gap-2"><button type="button" class="px-2 py-1 border border-gray-300 rounded text-xs" @click="$el.closest('details').querySelectorAll('input[type=checkbox]').forEach(box => box.checked = true)">All</button><button type="button" class="px-2 py-1 border border-gray-300 rounded text-xs" @click="$el.closest('details').querySelectorAll('input[type=checkbox]').forEach(box => box.checked = false)">None</button></div>
        <div class="mt-2 grid max-h-56 gap-1 overflow-y-auto sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($employees as $employee)<label class="flex items-center gap-2"><input type="checkbox" name="employee_ids[]" value="{{ is_array($employee) ? $employee['id'] : $employee->id }}">{{ is_array($employee) ? $employee['code'].' '.$employee['name'] : $employee->code.' '.$employee->name }}</label>@endforeach
        </div>
    </details>
</div>
