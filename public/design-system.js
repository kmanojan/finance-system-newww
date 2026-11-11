/* Shared accessible dialog behavior, including legacy page-specific open functions. */
(() => {
    const focusable = 'button:not([disabled]),a[href],input:not([type="hidden"]):not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex="0"]';
    let current = null, returnFocus = null;
    function syncDialogs() {
        const dialogs = [...document.querySelectorAll('.modal-backdrop.active,.modal-backdrop.show')];
        const next = dialogs.filter(el => getComputedStyle(el).display !== 'none').at(-1) || null;
        document.body.classList.toggle('dialog-open', !!next);
        if (next === current) return;
        if (!next) { returnFocus?.focus(); current = null; return; }
        if (!current) returnFocus = document.activeElement;
        current = next;
        const card = next.querySelector('.modal-card') || next;
        card.setAttribute('role','dialog'); card.setAttribute('aria-modal','true'); card.setAttribute('tabindex','-1');
        const title = card.querySelector('.modal-title,h2,h3');
        if (title) { title.id ||= `${next.id}-title`; card.setAttribute('aria-labelledby',title.id); }
        card.querySelectorAll('.btn-close').forEach(el => el.setAttribute('aria-label','Close dialog'));
        requestAnimationFrame(() => (card.querySelector('[autofocus]:not([disabled])') || card.querySelector(focusable) || card).focus());
    }
    document.addEventListener('keydown', e => {
        if (!current || e.key !== 'Tab') return;
        const items = [...current.querySelectorAll(focusable)].filter(el => el.getClientRects().length);
        if (!items.length) { e.preventDefault(); return; }
        if (e.shiftKey && (document.activeElement === items[0] || !current.contains(document.activeElement))) { e.preventDefault(); items.at(-1).focus(); }
        else if (!e.shiftKey && (document.activeElement === items.at(-1) || !current.contains(document.activeElement))) { e.preventDefault(); items[0].focus(); }
    });
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.modal-backdrop').forEach(el => new MutationObserver(syncDialogs).observe(el,{attributes:true,attributeFilter:['class','style']}));
        document.querySelectorAll('.nav-item.active').forEach(el => el.setAttribute('aria-current','page'));
        document.querySelectorAll('button[title],a[title]').forEach(el => { if(!el.getAttribute('aria-label')) el.setAttribute('aria-label',el.title); });
        document.querySelectorAll('label.form-label').forEach((label,index) => {
            if (label.htmlFor) return;
            const field = label.parentElement.querySelector('input:not([type="hidden"]),select,textarea');
            if (field) { field.id ||= `field-${index}`; label.htmlFor = field.id; }
        });
        const labelTables = () => {
            document.querySelectorAll('table').forEach(table => {
            const headings = [...table.querySelectorAll('thead tr:last-child th')].map(th=>th.textContent.trim());
            table.querySelectorAll('tbody tr').forEach(row=>[...row.children].forEach((cell,index)=>{
                if (!cell.dataset.label && cell.colSpan === 1 && headings[index]) cell.dataset.label = headings[index];
            }));
        });
        };
        labelTables();
        let labelFrame = null;
        const main = document.querySelector('main');
        if(main) new MutationObserver(() => {
            if(labelFrame) return;
            labelFrame = requestAnimationFrame(() => {labelTables();labelFrame=null;});
        }).observe(main,{childList:true,subtree:true});
        const filters = document.querySelector('.transaction-filters');
        if (filters && matchMedia('(max-width:640px)').matches && !location.search) filters.open = false;
        // Orphan legacy search boxes filter the rows currently displayed; server-backed forms retain their own behavior.
        document.querySelectorAll('input[placeholder*="Search"]').forEach(input => {
            if (input.form || input.name || input.oninput) return;
            input.setAttribute('aria-label','Filter displayed rows');
            input.placeholder='Filter displayed rows';
            input.addEventListener('input',()=>{
                const query=input.value.trim().toLocaleLowerCase();
                document.querySelectorAll('.main-content table tbody tr').forEach(row=>row.hidden=!row.textContent.toLocaleLowerCase().includes(query));
            });
        });
        document.querySelectorAll('form select[name="type"]').forEach(type => {
            const category = type.form.querySelector('select[name="category_id"]');
            if(!category?.querySelector('option[data-type]')) return;
            const sync = () => {
                [...category.options].forEach(option=>{option.hidden=!!option.dataset.type && option.dataset.type!==type.value;option.disabled=option.hidden;});
                if(category.selectedOptions[0]?.disabled) category.value=[...category.options].find(option=>!option.disabled)?.value || '';
            };
            type.addEventListener('change',sync);sync();
        });
        const role = document.body.dataset.workspaceRole;
        const canWrite = path => {
            if (path === '/logout' || path.startsWith('/profile')) return true;
            if (['admin','super_admin'].includes(role)) return true;
            if (!['manager','accountant'].includes(role)) return false;
            if (path.startsWith('/master/') || path.startsWith('/settings/')) return false;
            if ((path.includes('/approvals/') || /\/(approve|reject)$/.test(path)) && role !== 'manager') return false;
            return true;
        };
        if (role) {
            document.querySelectorAll('form').forEach(form => {
                if (form.method.toLowerCase() !== 'post' || canWrite(new URL(form.action,location.href).pathname)) return;
                form.querySelectorAll('input,select,textarea,button').forEach(control => { control.disabled=true;control.title='Your role cannot make this change.'; });
            });
            document.querySelectorAll('[onclick*="openModal"]').forEach(trigger => {
                const id = trigger.getAttribute('onclick').match(/openModal\(['"]([^'"]+)/)?.[1];
                const form = id && document.getElementById(id)?.querySelector('form[method="POST"],form[method="post"]');
                if (form && !canWrite(new URL(form.action,location.href).pathname)) {trigger.disabled=true;trigger.setAttribute('aria-disabled','true');trigger.title='Your role cannot make this change.';}
            });
        }
        syncDialogs();
    });
})();
