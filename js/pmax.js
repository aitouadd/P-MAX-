(function () {
    function isPmaxMessage(text) {
        if (!text) return false;
        var normalized = String(text).toLowerCase();
        return normalized.indexOf('blocage p-max') !== -1
            || normalized.indexOf('p-max block') !== -1
            || normalized.indexOf('depassement du plafond p-max') !== -1
            || normalized.indexOf('p-max') !== -1;
    }

    function applyPmaxStyle() {
        var notifications = document.querySelectorAll('.jnotify-container .jnotify-notification');
        notifications.forEach(function (node) {
            if (isPmaxMessage(node.textContent)) {
                node.classList.add('pmax-active');
            }
        });

        var inlineErrors = document.querySelectorAll('div.error, div.warning');
        inlineErrors.forEach(function (node) {
            if (isPmaxMessage(node.textContent)) {
                node.classList.add('pmax-inline');
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyPmaxStyle);
    } else {
        applyPmaxStyle();
    }

    var observer = new MutationObserver(function () {
        applyPmaxStyle();
    });

    observer.observe(document.documentElement, { childList: true, subtree: true });
})();
