(() => {
    'use strict';

    const workspace = document.querySelector('[data-reminder-workspace]');
    if (!workspace) return;

    const calendar = workspace.querySelector('[data-reminder-calendar]');
    const agenda = workspace.querySelector('[data-reminder-agenda]');
    const announcement = workspace.querySelector('[data-reminder-announcement]');
    const filterDate = workspace.querySelector('[data-reminder-filter-date]');
    const createHeader = workspace.querySelector('[data-reminder-header-create]');
    const createModal = document.getElementById('createReminderModal');
    const dueDate = document.getElementById('reminder-due-date');
    const days = [...(calendar?.querySelectorAll('[data-reminder-day]') ?? [])];
    const agendaTemplates = new Map([...workspace.querySelectorAll('template[data-reminder-agenda-for]')]
        .map(template => [template.dataset.reminderAgendaFor, template]));
    const selectedDay = () => calendar?.querySelector('[aria-current="date"]');

    function setTabStop(day) {
        for (const cell of days) cell.tabIndex = cell === day ? 0 : -1;
    }

    function selectDay(day, moveToAgenda = false) {
        const date = day.dataset.reminderDay;
        const template = agendaTemplates.get(date);
        if (!template || !agenda) return;

        selectedDay()?.removeAttribute('aria-current');
        day.setAttribute('aria-current', 'date');
        setTabStop(day);
        agenda.replaceChildren(template.content.cloneNode(true));

        filterDate.value = date;
        if (createHeader) createHeader.dataset.reminderCreate = date;
        const url = new URL(window.location.href);
        url.searchParams.set('month', date.slice(0, 7));
        url.searchParams.set('date', date);
        history.replaceState(history.state, '', url);

        const heading = agenda.querySelector('[data-agenda-heading]');
        announcement.textContent = day.getAttribute('aria-label');
        if (moveToAgenda) {
            heading.focus({preventScroll: true});
            agenda.scrollIntoView({
                block: 'nearest',
                behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth',
            });
        }
    }

    if (calendar) {
        setTabStop(selectedDay());
        calendar.addEventListener('click', event => {
            const day = event.target.closest('[data-reminder-day]');
            if (!day || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            selectDay(day, event.detail > 0 && matchMedia('(max-width: 740px)').matches);
        });
        calendar.addEventListener('keydown', event => {
            const day = event.target.closest('[data-reminder-day]');
            if (!day || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return;
            const index = days.indexOf(day);
            // Weekdays and blank cells occupy grid slots but are outside the focus order.
            const offset = [...calendar.children].indexOf(day) - 7;
            const targetIndex = {
                ArrowLeft: index - 1, ArrowRight: index + 1,
                ArrowUp: index - 7, ArrowDown: index + 7,
                Home: index - offset % 7, End: index + 6 - offset % 7,
            }[event.key];
            if (event.key === ' ') {
                event.preventDefault();
                selectDay(day);
            } else if (targetIndex !== undefined) {
                event.preventDefault();
                const target = days[Math.max(0, Math.min(days.length - 1, targetIndex))];
                setTabStop(target);
                target.focus();
            }
        });
        calendar.addEventListener('focusout', event => {
            if (!calendar.contains(event.relatedTarget)) setTabStop(selectedDay());
        });
    }

    workspace.addEventListener('click', event => {
        const create = event.target.closest('[data-reminder-create]');
        if (create && dueDate && !create.disabled) {
            dueDate.value = create.dataset.reminderCreate;
            openModal('createReminderModal');
        }
    });

    document.addEventListener('click', event => {
        workspace.querySelectorAll('.rw-actions[open]').forEach(menu => {
            if (!menu.contains(event.target)) menu.open = false;
        });
    });
    workspace.addEventListener('keydown', event => {
        const menu = event.target.closest('.rw-actions[open]');
        if (event.key === 'Escape' && menu) {
            menu.open = false;
            menu.querySelector('summary').focus();
        }
    });
    workspace.addEventListener('submit', event => {
        if (!event.target.matches('[data-reminder-delete]')) return;
        event.preventDefault();
        const form = event.target;
        confirmAction({
            title: 'Delete reminder?',
            message: 'This removes the custom reminder from the list and calendar.',
            confirmText: 'Delete reminder',
            onConfirm: () => form.submit(),
        });
    });

    if (createModal?.hasAttribute('data-reminder-reopen')) {
        openModal('createReminderModal');
    }
})();
