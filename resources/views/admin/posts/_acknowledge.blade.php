{{-- Reviewers confirm open checklist warnings before approving into publish/schedule, publishing or scheduling. --}}
@if ($show)
    <label class="flex items-start gap-2 text-sm text-fg">
        <input type="checkbox" id="{{ $id }}" name="acknowledge_warnings" value="1" class="mt-0.5 size-4 accent-brand">
        {{ trans_choice('I reviewed the :count open checklist warning|I reviewed the :count open checklist warnings', $warnings, ['count' => $warnings]) }}
    </label>
@endif
