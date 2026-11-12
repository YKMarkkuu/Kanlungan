/* Kanlungan staff dashboard: one job only. Ask "are you sure?" before the actions that cannot be undone
   with one click (ban, remove). Buttons opt in with data-confirm="...". */
document.addEventListener('submit', function (event) {
    var button = event.submitter;

    if (button && button.dataset.confirm && !window.confirm(button.dataset.confirm)) {
        event.preventDefault();
    }
});
