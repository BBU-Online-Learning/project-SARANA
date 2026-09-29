@extends('layouts.app')
@section('title', 'Search')
@section('content')
<div class="page-container my-3">
    <h1 class="h3">Search</h1>
    <form method="GET" action="{{ route('search.index') }}" role="search" class="d-flex gap-2 mb-3" data-workspace-nav-form>
        <label for="global-search" class="visually-hidden">Search your classes and conversations</label>
        <input id="global-search" name="q" type="search" class="form-control" value="{{ $term }}" minlength="2" maxlength="100" placeholder="Search classes, people, learning and conversations" autocomplete="off" required>
        <button class="btn btn-primary" type="submit">Search</button>
    </form>
    @error('q')<p class="text-danger">{{ $message }}</p>@enderror
    @if ($results !== null)
        @php($categories = ['classes' => 'Classes', 'people' => 'People', 'quizzes' => 'Quizzes', 'coursework' => 'Coursework', 'meetings' => 'Class meetings', 'announcements' => 'Announcements', 'chat' => 'Chat and class messages'])
        <p class="text-muted">Showing {{ $limit }} results per page in each category for “{{ $term }}”.</p>
        <nav class="search-category-nav" aria-label="Search result categories">
            @foreach ($categories as $key => $heading)
                <a href="#search-section-{{ $key }}">{{ $heading }} <span class="text-muted">{{ $results[$key]->total() }}</span></a>
            @endforeach
        </nav>
        @foreach ($categories as $key => $heading)
            <section id="search-section-{{ $key }}" class="search-result-section mb-4" aria-labelledby="search-{{ $key }}">
                <h2 id="search-{{ $key }}" class="h5">{{ $heading }} <span class="text-muted">({{ $results[$key]->total() }})</span></h2>
                @forelse ($results[$key] as $result)
                    <article class="search-result-card mb-2">
                        <a href="{{ $result['url'] }}">{{ $result['title'] }}</a>
                        <p class="text-muted small mb-0">{{ $result['detail'] }}</p>
                    </article>
                @empty
                    <p class="text-muted">No matching results available to you.</p>
                @endforelse
                @if ($results[$key]->hasPages())
                    <nav class="d-flex flex-wrap align-items-center gap-2 mt-2" aria-label="{{ $heading }} results pages">
                        @if ($results[$key]->onFirstPage())
                            <span class="btn btn-sm btn-outline-secondary disabled" aria-disabled="true">Previous</span>
                        @else
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('search.index', ['q' => $term, 'category' => $key, 'page' => $results[$key]->currentPage() - 1]) }}" data-workspace-nav>Previous</a>
                        @endif
                        <span class="small text-muted">Page {{ $results[$key]->currentPage() }} of {{ $results[$key]->lastPage() }}</span>
                        @if ($results[$key]->hasMorePages())
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('search.index', ['q' => $term, 'category' => $key, 'page' => $results[$key]->currentPage() + 1]) }}" data-workspace-nav>Next</a>
                        @else
                            <span class="btn btn-sm btn-outline-secondary disabled" aria-disabled="true">Next</span>
                        @endif
                    </nav>
                @endif
            </section>
        @endforeach
    @else
        <p class="text-muted">Search only shows content you can currently access.</p>
    @endif
</div>
@endsection
