/**
 * Shows a warning when the selected memory date is earlier than the
 * newest existing memory, so the user knows it won't appear at the
 * top of the feed.
 */
const input = document.getElementById('memory_date')
if (input) {
    const latestDate = input.dataset.latestMemoryDate
    const warning = document.getElementById('memory-date-warning')

    if (latestDate && warning) {
        function check () {
            warning.hidden = !input.value || input.value >= latestDate
        }

        input.addEventListener('change', check)
        check()
    }
}
