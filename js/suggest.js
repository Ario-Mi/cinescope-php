// ==========================================
// CineScope - AJAX Suggest (einfach)
// ==========================================
(function () {
    const input = document.getElementById('suchInput');
    const feld = document.getElementById('suchFeld');
    const box = document.getElementById('suggestBox');

    if (!input || !feld || !box) {
        return;
    }

    let timer = null;
    let activeRequest = null;
    let requestVersion = 0;

    function cancelRequest() {
        requestVersion += 1;
        if (activeRequest) {
            activeRequest.abort();
            activeRequest = null;
        }
    }

    function clearBox() {
        box.innerHTML = '';
        box.style.display = 'none';
    }

    function showList(items) {
        if (!Array.isArray(items) || items.length === 0) {
            clearBox();
            return;
        }

        box.innerHTML = '';

        items.forEach(function (item) {
            const id = Number(item.id);
            if (!Number.isSafeInteger(id) || id < 1) {
                return;
            }
            const link = document.createElement('a');
            link.className = 'suggest-item';
            link.textContent = item.title || '';
            link.href = 'film.php?id=' + encodeURIComponent(id);
            box.appendChild(link);
        });

        box.style.display = 'block';
    }

    input.addEventListener('input', function () {
        const q = input.value.trim();
        cancelRequest();
        clearBox();

        if (timer) {
            clearTimeout(timer);
        }

        if (q.length < 2) {
            clearBox();
            return;
        }

        // Kurze Verzoegerung, damit nicht bei jedem Zeichen sofort Request startet
        timer = setTimeout(function () {
            const suchFeld = feld.value || 'title';
            const xmlhttp = new XMLHttpRequest();
            const version = requestVersion;
            activeRequest = xmlhttp;

            xmlhttp.onreadystatechange = function () {
                if (this.readyState === 4 && this.status === 200 && version === requestVersion) {
                    try {
                        showList(JSON.parse(this.responseText));
                    } catch (e) {
                        clearBox();
                    }
                }
            };

            xmlhttp.open('GET', 'search_suggest.php?q=' + encodeURIComponent(q) + '&such_feld=' + encodeURIComponent(suchFeld), true);
            xmlhttp.send();
        }, 180);
    });

    feld.addEventListener('change', function () {
        cancelRequest();
        clearTimeout(timer);
        clearBox();
        if (input.value.trim().length >= 2) {
            input.dispatchEvent(new Event('input'));
        }
    });

    // Klick ausserhalb -> Suggest ausblenden
    document.addEventListener('click', function (ev) {
        if (!box.contains(ev.target) && ev.target !== input) {
            clearBox();
        }
    });
})();
