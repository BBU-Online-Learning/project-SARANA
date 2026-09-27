<form method="GET" action="{{ route('classes.index') }}" class="class-filters classes-modern-filters" role="search" aria-label="Find classes">
    <div class="flex-grow-1 classes-search-field">
        <label for="class-search" class="form-label">Search classes</label>
        <div><i class="ti ti-search" aria-hidden="true"></i><input id="class-search" type="search" name="search" value="{{ $search }}" maxlength="100" class="form-control" placeholder="Class name or description"></div>
        @error('search') <p class="text-danger small">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="class-status" class="form-label">Status</label>
        <select id="class-status" name="status" class="form-select">
            @foreach(['all' => 'All classes', 'active' => 'Active classes', 'archived' => 'Archived classes'] as $value => $label)
                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <button class="btn btn-primary" type="submit">Apply filters</button>
    @if($search !== '' || $status !== 'all')
        <a class="btn btn-outline-secondary" href="{{ route('classes.index') }}">Clear filters</a>
    @endif
</form>
