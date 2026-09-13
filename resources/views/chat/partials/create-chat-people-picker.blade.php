<div class="create-chat-picker" data-people-picker="{{ $picker }}">
    <label class="visually-hidden" for="{{ $picker }}-people-search">Search people</label>
    <div class="create-chat-search">
        <i class="ti ti-search" aria-hidden="true"></i>
        <input type="search" id="{{ $picker }}-people-search" placeholder="Search people..." autocomplete="off"
            data-people-search="{{ $picker }}" aria-controls="{{ $picker }}-people-results">
        <button type="button" data-clear-people-search="{{ $picker }}" aria-label="Clear people search" hidden>×</button>
    </div>
    <div class="create-chat-role-filters" role="group" aria-label="Filter people by role">
        @foreach (['all' => 'All', 'student' => 'Students', 'teacher' => 'Teachers', 'admin' => 'Admins'] as $value => $label)
            <button type="button" class="{{ $value === 'all' ? 'active' : '' }}" data-role-filter="{{ $value }}"
                data-picker="{{ $picker }}" aria-pressed="{{ $value === 'all' ? 'true' : 'false' }}">{{ $label }}</button>
        @endforeach
    </div>
    <div class="create-chat-results" id="{{ $picker }}-people-results" data-people-results="{{ $picker }}"
        aria-live="polite" aria-label="People"></div>
</div>
