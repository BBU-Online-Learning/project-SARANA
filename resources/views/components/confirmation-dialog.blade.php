<dialog id="app-confirm-dialog" class="app-confirm-dialog" aria-labelledby="app-confirm-title"
    aria-describedby="app-confirm-message">
    <form method="dialog" class="app-confirm-card">
        <div class="app-confirm-icon" data-confirm-icon aria-hidden="true">
            <i class="ti ti-alert-triangle"></i>
        </div>
        <div class="app-confirm-content">
            <h2 id="app-confirm-title">Confirm action</h2>
            <p id="app-confirm-message"></p>
        </div>
        <div class="app-confirm-actions">
            <button type="submit" value="cancel" class="btn btn-light" data-confirm-cancel>Cancel</button>
            <button type="submit" value="confirm" class="btn btn-danger" data-confirm-accept>Confirm</button>
        </div>
    </form>
</dialog>
