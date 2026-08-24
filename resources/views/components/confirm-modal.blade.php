<div class="modal-backdrop" id="globalConfirmModal">
    <div class="modal-card" style="max-width: 440px; text-align: center; padding: 1.75rem 1.5rem;">
        <div id="globalConfirmIconWrapper" style="width: 56px; height: 56px; border-radius: 50%; background: rgba(239, 68, 68, 0.15); color: var(--danger); display: inline-flex; align-items: center; justify-content: center; font-size: 1.75rem; margin: 0 auto 1rem;">
            <ion-icon id="globalConfirmIcon" name="alert-circle-outline"></ion-icon>
        </div>
        <h3 id="globalConfirmTitle" style="font-size: 1.15rem; font-weight: 700; color: var(--text-heading); margin-bottom: 0.5rem;">Are you sure?</h3>
        <p id="globalConfirmMessage" style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.5; margin-bottom: 1.5rem;">This action cannot be undone.</p>
        <div style="display: flex; gap: 0.75rem; justify-content: center;">
            <button type="button" class="btn btn-outline" onclick="closeModal('globalConfirmModal')" style="flex: 1;">Cancel</button>
            <button type="button" id="globalConfirmBtn" class="btn" style="flex: 1; background: var(--danger); color: white; border: none; font-weight: 600;">Confirm</button>
        </div>
    </div>
</div>
