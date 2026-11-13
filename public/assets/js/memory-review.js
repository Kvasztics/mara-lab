(() => {
    const button = document.getElementById('btn_memory_review');
    const container = document.getElementById('memory-review-container');
    if (!button || !container) return;

    let requestInFlight = false;
    let reviewing = false;
    let saving = false;

    async function refresh(open = false) {
        if (requestInFlight || saving || (!open && reviewing)) return;
        requestInFlight = true;
        try {
            const response = await fetch(button.dataset.statusUrl, {cache: 'no-store'});
            if (!response.ok) throw new Error('Memory review status unavailable');
            const status = await response.json();
            button.hidden = !status.enabled;
            button.style.display = status.enabled ? '' : 'none';
            button.disabled = !status.enabled || !status.count;
            button.style.backgroundColor = button.disabled ? '' : 'var(--primary, #2563eb)';
            button.style.opacity = button.disabled ? '0.45' : '';
            if (open && !button.disabled) {
                container.innerHTML = status.html;
                window.modalOpen('modal_memory_review');
                reviewing = true;
            }
        } catch (error) {
            if (open) window.alert(button.dataset.error);
        } finally {
            requestInFlight = false;
        }
    }

    button.addEventListener('click', () => refresh(true));
    container.addEventListener('click', event => {
        if (event.target.closest('.close')) reviewing = false;
    });
    container.addEventListener('submit', async event => {
        event.preventDefault();
        if (saving) return;
        saving = true;
        try {
            const response = await fetch(event.target.action, {
                method: 'POST',
                body: new FormData(event.target, event.submitter)
            });
            if (response.status !== 204) throw new Error('Memory review failed');
            window.modalClose('modal_memory_review');
            container.replaceChildren();
            reviewing = false;
        } catch (error) {
            window.alert(button.dataset.error);
            return;
        } finally {
            saving = false;
        }
        await refresh(true);
    });
    refresh();
    window.setInterval(() => {
        const modal = container.querySelector('.modal');
        if (modal && getComputedStyle(modal).visibility === 'hidden') reviewing = false;
        if (!document.hidden) refresh();
    }, 5000);
})();
