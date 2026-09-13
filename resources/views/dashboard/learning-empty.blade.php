<div class="learning-empty">
    <i class="ti ti-school" aria-hidden="true"></i><h3>No classes yet</h3>
    <p>{{ auth()->user()->can('manage-classes') ? 'Create your first class or join an existing class with a code.' : 'Use a code from your teacher to join your first class.' }}</p>
</div>
