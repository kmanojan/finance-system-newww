<div class="payment-modes-component">
    <h3 class="section-title" style="margin-top:1.5rem; font-size:1rem; border-bottom:1px solid var(--border); padding-bottom:0.4rem; margin-bottom: 0.75rem;">Payment Modes</h3>
    
    <div class="pm-container">
        <!-- Compact Payment Mode Row -->
        <div class="pm-row" style="display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: flex-end; margin-bottom: 0.75rem; padding-bottom: 0.75rem; border-bottom: 1px dashed var(--border); position: relative; padding-right: 2rem;">
            
            <div style="flex: 1 1 110px; min-width: 110px;">
                <label style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.2rem; display: block;">Mode</label>
                <select name="pm_mode[]" class="form-control pm-mode-select" style="padding: 0.3rem 0.5rem; font-size: 0.85rem;" required onchange="togglePmFields(this)">
                    <option value="cash">Cash</option>
                    <option value="card">Card</option>
                    <option value="cheque">Cheque</option>
                    <option value="bank_transfer">Bank Transfer</option>
                </select>
            </div>
            
            <div style="flex: 1 1 90px; min-width: 90px;">
                <label style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.2rem; display: block;">Amount</label>
                <x-amount-input name="pm_amount[]" required="true" class="form-control" style="padding: 0.3rem 0.5rem; font-size: 0.85rem;" />
            </div>

            <div class="pm-bank-col" style="display: none; flex: 1 1 110px; min-width: 110px;">
                <label style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.2rem; display: block;">Bank</label>
                <input type="text" name="pm_bank[]" class="form-control pm-bank-input" style="padding: 0.3rem 0.5rem; font-size: 0.85rem;">
            </div>
            
            <div class="pm-cheque-col" style="display: none; flex: 1 1 90px; min-width: 90px;">
                <label style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.2rem; display: block;">Cheque No</label>
                <input type="text" name="pm_cheque_no[]" class="form-control pm-cheque-input" style="padding: 0.3rem 0.5rem; font-size: 0.85rem;">
            </div>
            
            <div class="pm-date-col" style="display: none; flex: 1 1 100px; min-width: 100px;">
                <label style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.2rem; display: block;">Date</label>
                <input type="date" name="pm_cheque_date[]" class="form-control pm-date-input" style="padding: 0.3rem 0.5rem; font-size: 0.85rem;">
            </div>
            
            <div class="pm-ref-col" style="display: none; flex: 1 1 110px; min-width: 110px;">
                <label style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.2rem; display: block;">Ref / Auth No</label>
                <input type="text" name="pm_reference[]" class="form-control pm-ref-input" style="padding: 0.3rem 0.5rem; font-size: 0.85rem;">
            </div>

            <div class="pm-notes-col" style="display: none; flex: 2 1 120px; min-width: 120px;">
                <label style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.2rem; display: block;">Notes</label>
                <input type="text" name="pm_notes[]" class="form-control" style="padding: 0.3rem 0.5rem; font-size: 0.85rem;" placeholder="Optional">
            </div>

            <button type="button" style="position: absolute; right: 0; bottom: 0.9rem; background: none; border: none; color: #ef4444; font-size: 1.1rem; cursor: pointer; padding: 0.2rem;" onclick="removePmCard(this)" title="Remove Mode">
                <ion-icon name="trash-outline"></ion-icon>
            </button>
        </div>
    </div>

    <div style="display:flex; justify-content:flex-start; margin-top: 0.25rem;">
        <button type="button" class="btn btn-outline" onclick="addPmCard(this)" style="padding:0.3rem 0.8rem; font-size:0.8rem; border-radius: 4px;">
            <ion-icon name="add-outline"></ion-icon> Add Payment Mode
        </button>
    </div>
</div>

<script>
    function togglePmFields(selectElement) {
        const row = selectElement.closest('.pm-row');
        const mode = selectElement.value;
        
        // Cols
        const bankCol = row.querySelector('.pm-bank-col');
        const chequeCol = row.querySelector('.pm-cheque-col');
        const dateCol = row.querySelector('.pm-date-col');
        const refCol = row.querySelector('.pm-ref-col');
        const notesCol = row.querySelector('.pm-notes-col');
        
        // Reset everything to none
        bankCol.style.display = 'none';
        chequeCol.style.display = 'none';
        dateCol.style.display = 'none';
        refCol.style.display = 'none';
        notesCol.style.display = 'none';
        
        // Clear required attrs
        row.querySelector('.pm-bank-input').required = false;
        row.querySelector('.pm-cheque-input').required = false;
        row.querySelector('.pm-date-input').required = false;
        row.querySelector('.pm-ref-input').required = false;

        if (mode === 'cheque') {
            bankCol.style.display = 'block';
            chequeCol.style.display = 'block';
            dateCol.style.display = 'block';
            notesCol.style.display = 'block';
            
            row.querySelector('.pm-bank-input').required = true;
            row.querySelector('.pm-cheque-input').required = true;
            row.querySelector('.pm-date-input').required = true;
        } else if (mode === 'bank_transfer') {
            refCol.style.display = 'block';
            notesCol.style.display = 'block';
            
            row.querySelector('.pm-ref-input').required = true;
        } else if (mode === 'card') {
            refCol.style.display = 'block';
            notesCol.style.display = 'block';
        } else {
            notesCol.style.display = 'block';
        }
    }

    function addPmCard(buttonElement) {
        const component = buttonElement.closest('.payment-modes-component');
        const container = component.querySelector('.pm-container');
        const firstRow = container.querySelector('.pm-row');
        const newRow = firstRow.cloneNode(true);
        
        // Reset values
        newRow.querySelectorAll('input').forEach(input => input.value = '');
        newRow.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
        
        container.appendChild(newRow);
        
        // trigger toggle for default mode
        togglePmFields(newRow.querySelector('.pm-mode-select'));
    }

    function removePmCard(buttonElement) {
        const container = buttonElement.closest('.pm-container');
        const rows = container.querySelectorAll('.pm-row');
        if(rows.length > 1) {
            buttonElement.closest('.pm-row').remove();
        } else {
            alert("A payment must have at least one mode.");
        }
    }

    // Initialize first row
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.pm-mode-select').forEach(select => {
            togglePmFields(select);
        });
    });
</script>
