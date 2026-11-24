// The web search (SearchBlock): a date chosen in the picker of the browser selects the search of a day.
(() => {
    document.addEventListener('change', (event) => {
        const picker = event.target.closest?.('[data-search-day]');
        const day = picker?.closest('.search-when')?.querySelector('input[name="when"][value="day"]');
        day && (day.checked = true);
    });
})();
