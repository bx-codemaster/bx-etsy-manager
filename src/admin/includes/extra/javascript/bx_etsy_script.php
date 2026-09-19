<?php
/* ----------------------------------------------------------------------------------------------
   $Id: admin/includes/extra/javascript/bx_etsymanager.php 1000 2026-02-03 19:00:00Z benax $

   BX Etsy Manager - JavaScript für Token-Countdown Timer und AJAX-Refresh
   
   Dieses JavaScript-Modul stellt eine Live-Countdown-Anzeige für das Etsy OAuth 2.0 Access Token
   bereit und ermöglicht die manuelle Token-Erneuerung via AJAX ohne Seitenneuladung.
   
   Funktionen:
   - Echtzeit-Countdown bis zum Token-Ablauf (MM:SS Format)
   - Farbcodierte Anzeige basierend auf verbleibender Zeit (Grün → Orange → Rot)
   - Automatische Anzeige eines Refresh-Buttons bei < 10 Minuten
   - AJAX-basierte Token-Erneuerung über Modified's AJAX-API
   - Browser-Benachrichtigungen bei kritischen Schwellenwerten (5 Minuten)
   - Visuelles Feedback: Blink-Animation bei < 1 Minute oder Ablauf
   
   Integration:
   - Wird automatisch in bx_etsymanager.php inkludiert (auto-include System)
   - Nutzt Modified's zentrale AJAX-API: ajax.php?ext=bx_etsymanager&method=refresh_token
   - Initialisierung: window.bxEtsyInitTokenCountdown(expiresTimestamp, containerId)
   
   modified eCommerce Shopsoftware
   http://www.modified-shop.org

   Copyright (c) 2009 - 2013 [www.modified-shop.org]
   ----------------------------------------------------------------------------------------------
   Released under the GNU General Public License
   ----------------------------------------------------------------------------------------------*/

defined('_VALID_XTC') or die('Direct Access to this location is not allowed.');

 if (defined('MODULE_BX_ETSY_MANAGER_STATUS') && MODULE_BX_ETSY_MANAGER_STATUS == 'True' && basename($_SERVER['PHP_SELF']) == 'bx_etsymanager.php') {
?>
<!-- 
/** ==============================================================================
 * BX ETSY MANAGER - JavaScript für Token-Countdown Timer
 * ==============================================================================
 */
-->
<script>
/**
 * BX Etsy Manager - Token Expiration Countdown Timer
 * Zeigt die verbleibende Zeit bis zum Token-Ablauf in Echtzeit an
 */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    
    /**
     * Initialisiert den Countdown-Timer für das Etsy Access Token
     * 
     * @param {number} expiresTimestamp Unix-Timestamp wann das Token abläuft
     * @param {string} containerId HTML-Element-ID für die Zeitanzeige
     */
    function initTokenCountdown(expiresTimestamp, containerId) {
        const container = document.getElementById(containerId);
        if (!container) {
            return;
        }
        
        let lastMinutes = null;
        
        /**
         * Formatiert Sekunden in MM:SS Format
         */
        function formatTime(totalSeconds) {
            const minutes = Math.floor(totalSeconds / 60);
            const seconds = totalSeconds % 60;
            return minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
        }
        
        /**
         * Bestimmt die Farbe basierend auf verbleibenden Minuten
         */
        function getColor(minutes) {
            if (minutes < 5) return '#dc3545'; // Rot
            if (minutes < 10) return '#fd7e14'; // Orange
            return '#28a745'; // Grün
        }
        
        /**
         * Aktualisiert die Timer-Anzeige
         */
        function updateTimer() {
            const now = Math.floor(Date.now() / 1000);
            const remaining = expiresTimestamp - now;
            
            if (remaining <= 0) {
                // Token abgelaufen - REFRESH-BUTTON ANZEIGEN!
                container.innerHTML = '<strong>Status:</strong> <span style="color: #dc3545; font-weight: bold; animation: blink 1s infinite;">⚠️ Token abgelaufen!</span>';
                
                // Refresh-Button prominent anzeigen
                if (!document.getElementById('bx-etsy-refresh-btn')) {
                    const btnContainer = document.createElement('div');
                    btnContainer.id = 'bx-etsy-refresh-btn-container';
                    btnContainer.style.marginTop = '10px';
                    btnContainer.style.textAlign = 'center';
                    btnContainer.innerHTML = '<button id="bx-etsy-refresh-btn" class="button" style="font-size: 13px; padding: 8px 15px; background: #dc3545; color: #fff; font-weight: bold; animation: blink 2s infinite;">🔄 Token JETZT erneuern!</button>';
                    container.parentElement.appendChild(btnContainer);
                    
                    document.getElementById('bx-etsy-refresh-btn').addEventListener('click', function() {
                        refreshToken();
                    });
                }
                
                return; // Timer stoppen
            }
            
            const minutes    = Math.floor(remaining / 60);
            const color      = getColor(minutes);
            const timeString = formatTime(remaining);
            
            // Blinken bei < 1 Minute
            const blinkStyle = (minutes < 1) ? 'animation: blink 1s infinite;' : '';
            
            // Icon basierend auf Zeit
            let icon = '⏱️';
            if (minutes < 5) icon = '⚠️';
            else if (minutes < 10) icon = '⏰';
            
            container.innerHTML = '<strong>Verbleibend:</strong> <span style="color: ' + color + '; font-weight: bold; font-size: 16px; ' + blinkStyle + '">' + icon + ' ' + timeString + ' Min.</span>';
            
            // Refresh-Button anzeigen bei < 10 Minuten
            if (minutes < 10 && !document.getElementById('bx-etsy-refresh-btn')) {
                const btnContainer = document.createElement('div');
                btnContainer.id = 'bx-etsy-refresh-btn-container';
                btnContainer.style.marginTop = '10px';
                btnContainer.style.textAlign = 'center';
                btnContainer.innerHTML = '<button id="bx-etsy-refresh-btn" class="button" style="font-size: 12px; padding: 5px 10px;">🔄 Token jetzt erneuern</button>';
                container.parentElement.appendChild(btnContainer);
                
                document.getElementById('bx-etsy-refresh-btn').addEventListener('click', function() {
                    refreshToken();
                });
            }
            
            // Browser-Benachrichtigung bei 5 Minuten (einmalig)
            if (minutes === 5 && lastMinutes !== 5) {
                showNotification('Etsy Token läuft bald ab', 'Das Access Token läuft in 5 Minuten ab.');
            }
            
            lastMinutes = minutes;
            
            // Nächstes Update in 1 Sekunde
            setTimeout(updateTimer, 1000);
        }
        
        /**
         * Zeigt Browser-Benachrichtigung (falls erlaubt)
         */
        function showNotification(title, message) {
            if (!('Notification' in window)) return;
            
            if (Notification.permission === 'granted') {
                new Notification(title, {
                    body: message,
                    icon: 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y="75" font-size="75">⏰</text></svg>'
                });
            }
        }
        
        // Timer starten
        updateTimer();
    }
    
    /**
     * Erneuert das Etsy Access Token via AJAX
     */
    function refreshToken() {
        const btn = document.getElementById('bx-etsy-refresh-btn');
        if (!btn) return;
        
        // Button deaktivieren und Loading-State anzeigen
        btn.disabled = true;
        btn.innerHTML = '⏳ Erneuere Token...';
        btn.style.opacity = '0.6';
        
        // AJAX-Request über Modified's AJAX-API
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '<?php echo DIR_WS_CATALOG; ?>ajax.php?ext=bx_etsymanager&method=refresh_token', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    const raw = (xhr.responseText || '').trim();
                    if (!raw) {
                        throw new Error('Leere Server-Antwort');
                    }

                    const response = JSON.parse(raw);
                    
                    if (response.success) {
                        // Erfolg
                        btn.innerHTML = '✅ Erfolgreich erneuert!';
                        btn.style.backgroundColor = '#28a745';
                        btn.style.color = '#fff';
                        
                        // Nach 2 Sekunden Seite neu laden
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
                        
                    } else {
                        // Fehler
                        btn.innerHTML = '❌ Fehler: ' + (response.error || 'Unbekannter Fehler');
                        btn.style.backgroundColor = '#dc3545';
                        btn.style.color = '#fff';
                        btn.disabled = false;
                        btn.style.opacity = '1';
                        
                        // Nach 5 Sekunden Button zurücksetzen
                        setTimeout(function() {
                            btn.innerHTML = '🔄 Token jetzt erneuern';
                            btn.style.backgroundColor = '';
                            btn.style.color = '';
                        }, 5000);
                    }
                } catch (e) {
                    console.error('BX Etsy: Parse Error', e);
                    btn.innerHTML = '❌ Ungültige Server-Antwort';
                    btn.style.backgroundColor = '#dc3545';
                    btn.style.color = '#fff';
                    btn.disabled = false;
                    btn.style.opacity = '1';
                }
            } else {
                // HTTP-Fehler
                btn.innerHTML = '❌ Server-Fehler (' + xhr.status + ')';
                btn.style.backgroundColor = '#dc3545';
                btn.style.color = '#fff';
                btn.disabled = false;
                btn.style.opacity = '1';
            }
        };
        
        xhr.onerror = function() {
            btn.innerHTML = '❌ Verbindungsfehler';
            btn.style.backgroundColor = '#dc3545';
            btn.style.color = '#fff';
            btn.disabled = false;
            btn.style.opacity = '1';
        };
        
        xhr.send('refresh=1');
    }
    
    /**
     * CSS-Animation für Blinken definieren
     */
    if (!document.getElementById('bx-etsy-timer-styles')) {
        const style = document.createElement('style');
        style.id = 'bx-etsy-timer-styles';
        style.textContent = `
            @keyframes blink {
                0%, 50% { opacity: 1; }
                51%, 100% { opacity: 0.3; }
            }
        `;
        document.head.appendChild(style);
    }
    
    // Global verfügbar machen
    window.bxEtsyInitTokenCountdown = initTokenCountdown;

    function initTokenCountdownContainers(scopeRoot) {
        var root = scopeRoot || document;
        var autoContainers = root.querySelectorAll('#bx-etsy-token-countdown[data-expires-timestamp], .bx-etsy-token-countdown[data-expires-timestamp]');

        for (var i = 0; i < autoContainers.length; i++) {
            var autoContainer = autoContainers[i];
            if (autoContainer.getAttribute('data-countdown-init') === '1') {
                continue;
            }

            var expiresRaw = autoContainer.getAttribute('data-expires-timestamp');
            var expiresTimestamp = parseInt(expiresRaw, 10);

            if (!isNaN(expiresTimestamp) && expiresTimestamp > 0) {
                if (!autoContainer.id) {
                    autoContainer.id = 'bx-etsy-token-countdown-' + i;
                }

                autoContainer.setAttribute('data-countdown-init', '1');
                initTokenCountdown(expiresTimestamp, autoContainer.id);
            }
        }
    }

    // Auto-Initialisierung für Countdown-Container aus dem initialen Admin-Markup
    initTokenCountdownContainers(document);
    
    // Fixed MessageStack anzeigen (falls vorhanden)
    var fixedStack = document.querySelector('.fixed_messageStack');
    if (fixedStack) {
        if (typeof window.jQuery !== 'undefined' && typeof window.jQuery.fn !== 'undefined') {
            window.jQuery(fixedStack).stop(true, true).slideDown('slow', function() {
                setTimeout(function() {
                    window.jQuery(fixedStack).slideUp('slow');
                }, 3000);
            });
        } else {
            fixedStack.style.display = 'block';
            setTimeout(function() {
                fixedStack.style.display = 'none';
            }, 3000);
        }
    }

    // Tab-Navigation mit Speicherung im LocalStorage + AJAX-Lazy-Loading
    const tabsRoot = document.querySelector('.etsy-tabs');
    const tabs     = document.querySelectorAll('.etsy-tabs .tab-nav a');
    const contents = document.querySelectorAll('.etsy-tabs .tab-content > div');
    const rightContents = document.querySelectorAll('.bx-sidebar > [id^="tab-"][id$="-right"]');

    const STORAGE_KEY = 'bxEtsyManagerActiveTab';
    const EXPIRATION_MS = 1000 * 60 * 60; // 1 Stunde
    const dashboardLoadMode = tabsRoot ? (tabsRoot.getAttribute('data-dashboard-load-mode') || 'eager') : 'eager';
    const LAZY_TABS = ['orders', 'listings', 'support'];
    if (dashboardLoadMode !== 'eager') {
        LAZY_TABS.unshift('dashboard');
    }
    const AJAX_URL = '<?php echo DIR_WS_CATALOG; ?>ajax.php?ext=bx_etsymanager&method=load_tab&type=html';
    const ORDER_DETAILS_URL = '<?php echo DIR_WS_CATALOG; ?>ajax.php?ext=bx_etsymanager&method=load_order_details&type=html';
    const ORDER_EDIT_URL = '<?php echo xtc_href_link(FILENAME_ETSY_MANAGER, "action=edit&etsy_order_id="); ?>';
    const ORDER_ICON_ACTIVE_URL   = '<?php echo DIR_WS_IMAGES . "icon_arrow_right.gif"; ?>';
    const ORDER_ICON_INACTIVE_URL = '<?php echo DIR_WS_IMAGES . "icon_arrow_grey.gif"; ?>';

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderOrderDetailsError(message) {
        const rightEl = document.querySelector('#tab-orders-right');
        if (!rightEl) {
            return;
        }

        rightEl.innerHTML = '' +
            '<table class="contentTable">' +
                '<tbody>' +
                    '<tr class="infoBoxHeading">' +
                        '<td class="infoBoxHeading"><div class="infoBoxHeadingTitle"><strong>Bestelldetails</strong></div></td>' +
                    '</tr>' +
                '</tbody>' +
            '</table>' +
            '<table class="contentTable">' +
                '<tbody>' +
                    '<tr class="infoBoxContent"><td class="infoBoxContent" style="color: #dc3545;"><strong>Fehler:</strong><br>' + escapeHtml(message || 'Unbekannter Fehler') + '</td></tr>' +
                '</tbody>' +
            '</table>';
    }

    function loadOrderDetails(orderId) {
        const rightEl = document.querySelector('#tab-orders-right');
        if (!rightEl) {
            return;
        }

        if (!orderId) {
            renderOrderDetailsError('Es wurde keine Etsy-Bestellung übergeben.');
            return;
        }

        rightEl.innerHTML = '' +
            '<table class="contentTable">' +
                '<tbody>' +
                    '<tr class="infoBoxHeading">' +
                        '<td class="infoBoxHeading"><div class="infoBoxHeadingTitle"><strong>Bestelldetails</strong></div></td>' +
                    '</tr>' +
                '</tbody>' +
            '</table>' +
            '<table class="contentTable">' +
                '<tbody>' +
                    '<tr class="infoBoxContent"><td class="infoBoxContent">⏳ Lade Bestelldetails…</td></tr>' +
                '</tbody>' +
            '</table>';

        const xhr = new XMLHttpRequest();
        xhr.open('GET', ORDER_DETAILS_URL + '&order_id=' + encodeURIComponent(orderId), true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.onload = function () {
            if (xhr.status !== 200) {
                renderOrderDetailsError('Server-Fehler (' + xhr.status + ')');
                return;
            }

            try {
                const response = JSON.parse((xhr.responseText || '').trim());
                if (!response.success) {
                    renderOrderDetailsError(response.error || 'Fehler beim Laden der Bestelldetails.');
                    return;
                }

                rightEl.innerHTML = response.html || '';
            } catch (e) {
                renderOrderDetailsError('Ungültige Server-Antwort.');
            }
        };

        xhr.onerror = function () {
            renderOrderDetailsError('Verbindungsfehler beim Laden der Bestelldetails.');
        };

        xhr.send();
    }

    function activateOrderRow(rowEl) {
        if (!rowEl) {
            return;
        }

        const ordersContainer = document.querySelector('#tab-orders');
        if (!ordersContainer) {
            return;
        }

        const allRows = ordersContainer.querySelectorAll('tr.bx-etsy-order-row');
        allRows.forEach(function (row) {
            row.classList.remove('bx-orders-selected');
            row.removeAttribute('aria-selected');

            const icon = row.querySelector('.bx-etsy-order-action-icon');
            if (icon) {
                icon.src = ORDER_ICON_INACTIVE_URL;
            }
        });

        rowEl.classList.add('bx-orders-selected');
        rowEl.setAttribute('aria-selected', 'true');

        const activeIcon = rowEl.querySelector('.bx-etsy-order-action-icon');
        if (activeIcon) {
            activeIcon.src = ORDER_ICON_ACTIVE_URL;
        }
        loadOrderDetails(rowEl.getAttribute('data-order-id') || '');
    }

    function initOrdersSelection() {
        const ordersContainer = document.querySelector('#tab-orders');
        const rightEl = document.querySelector('#tab-orders-right');

        if (!ordersContainer || !rightEl) {
            return;
        }

        const firstRow = ordersContainer.querySelector('tr.bx-etsy-order-row');
        if (!firstRow) {
            rightEl.innerHTML = '' +
                '<div class="bx-headboard bx-headboard--secondary">Bestellungen</div>' +
                '<article class="bx-panel">' +
                '  <p>Keine Bestellungen vorhanden.</p>' +
                '</article>';
            return;
        }

        activateOrderRow(firstRow);
    }

    /**
     * Lädt den Inhalt eines Tabs per AJAX nach, falls noch nicht geschehen.
     * Markiert den Container per data-loaded="1", damit ein zweiter Aufruf
     * (z.B. erneuter Klick) keinen wiederholten Request auslöst.
     */
    function loadTabContent(tabName, extraParams, onDone) {
        const contentEl = document.querySelector('#tab-' + tabName);
        const rightEl   = document.querySelector('#tab-' + tabName + '-right');
        
        if (!contentEl) {
            if (onDone) onDone();
            return;
        }

        if (contentEl.getAttribute('data-loading') === '1') {
            if (onDone) onDone();
            return;
        }

        contentEl.setAttribute('data-loading', '1');

        function done() {
            contentEl.removeAttribute('data-loading');
            if (onDone) onDone();
        }

        let url = AJAX_URL + '&tab=' + encodeURIComponent(tabName);
        if (extraParams) {
            url += '&' + extraParams;
        }

        const xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.onload = function () {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse((xhr.responseText || '').trim());
                    if (response.success) {
                        contentEl.innerHTML = response.content || '';
                        if (rightEl) {
                            rightEl.innerHTML = response.right || '';
                        }
                        contentEl.setAttribute('data-loaded', '1');

                        if (tabName === 'orders') {
                            initOrdersSelection();
                        }

                        // Countdown-Container werden bei AJAX-Inhalten neu ins DOM gesetzt
                        // und müssen danach explizit initialisiert werden.
                        initTokenCountdownContainers(contentEl);
                        if (rightEl) {
                            initTokenCountdownContainers(rightEl);
                        }
                    } else {
                        contentEl.innerHTML = '<div class="bx-etsy-tab-error">❌ ' + (response.error || 'Fehler beim Laden.') + '</div>';
                    }
                } catch (e) {
                    contentEl.innerHTML = '<div class="bx-etsy-tab-error">❌ Ungültige Server-Antwort.</div>';
                }
                done();
            } else {
                contentEl.innerHTML = '<div class="bx-etsy-tab-error">❌ Server-Fehler (' + xhr.status + ')</div>';
                done();
            }
        };

        xhr.onerror = function () {
            contentEl.innerHTML = '<div class="bx-etsy-tab-error">❌ Verbindungsfehler beim Laden des Tabs.</div>';
            done();
        };

        xhr.send();
    }

    // Funktion zum Aktivieren eines Tabs
    function activateTab(tabId) {
        const tabName = tabId.replace('#tab-', '');

        // Navigation
        tabs.forEach(t => t.classList.remove('active'));
        const activeTab = document.querySelector(`.etsy-tabs .tab-nav a[href="${tabId}"]`);
        if (activeTab) activeTab.classList.add('active');

        // Inhalte
        contents.forEach(c => c.classList.remove('active'));
        const target = document.querySelector(tabId);
        if (target) target.classList.add('active');

        // Rechte Sidebar analog zum aktiven Tab umschalten
        rightContents.forEach(c => c.classList.remove('active'));
        const rightTarget = document.querySelector(tabId + '-right');
        if (rightTarget) rightTarget.classList.add('active');

        // Bei lazy-tabs: Inhalt nachladen, falls noch nicht geschehen
        if (LAZY_TABS.indexOf(tabName) !== -1 && target && target.getAttribute('data-loaded') !== '1') {
            loadTabContent(tabName, null, null);
        } else if (tabName === 'orders') {
            initOrdersSelection();
        }
    }

    // Klick-Handler
    tabs.forEach(tab => {
        tab.addEventListener('click', function (e) {
        e.preventDefault();
        const tabId = this.getAttribute('href');
        activateTab(tabId);

        // Tab + Timestamp speichern
        const data = {
            tabId: tabId,
            timestamp: Date.now()
        };
        localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
        });
    });

    if (dashboardLoadMode === 'lazy_preload') {
        const dashboardContent = document.querySelector('#tab-dashboard');
        if (dashboardContent && dashboardContent.getAttribute('data-loaded') !== '1') {
            loadTabContent('dashboard', null, null);
        }
    }

    // Innerhalb des Bestellungen-Tabs: Sortier-/Seiten-Links per AJAX statt Seiten-Reload
    // abfangen (Event-Delegation, da der Tab-Inhalt per AJAX nachgeladen/ersetzt wird).
    const ordersContainer = document.querySelector('#tab-orders');
    if (ordersContainer) {
        ordersContainer.addEventListener('change', function (e) {
            const pageSelect = e.target.closest('#bx-etsy-page-select');
            if (!pageSelect) {
                return;
            }

            const form = pageSelect.closest('form#pages');
            if (!form) {
                return;
            }

            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
                return;
            }

            form.submit();
        });

        ordersContainer.addEventListener('submit', function (e) {
            const form = e.target.closest('form#pages, form#bx-etsy-orders-per-page-form');
            if (!form) {
                return;
            }

            e.preventDefault();

            const params = new URLSearchParams(new FormData(form)).toString();

            ordersContainer.setAttribute('data-loaded', '0');
            ordersContainer.innerHTML = '<div class="bx-etsy-tab-loading">⏳ Lade Bestellungen…</div>';
            loadTabContent('orders', params, null);
        });

        ordersContainer.addEventListener('click', function (e) {
            const orderRow = e.target.closest('tr.bx-etsy-order-row');
            if (orderRow && ordersContainer.contains(orderRow) && !e.target.closest('a[href]')) {
                const orderId = orderRow.getAttribute('data-order-id') || '';

                if (orderRow.classList.contains('bx-orders-selected')) {
                    // Zeile ist bereits markiert -> zweiter Klick öffnet die Bearbeitung
                    if (orderId) {
                        window.location.href = ORDER_EDIT_URL + encodeURIComponent(orderId);
                    }
                    return;
                }

                activateOrderRow(orderRow);
                return;
            }

            const link = e.target.closest('a[href]');
            if (!link) return;

            const href = link.getAttribute('href');
            if (!href || (href.indexOf('page=') === -1 && href.indexOf('sorting=') === -1)) {
                return;
            }

            e.preventDefault();

            const queryStart = href.indexOf('?');
            const params = queryStart !== -1 ? href.substring(queryStart + 1) : '';

            ordersContainer.setAttribute('data-loaded', '0');
            ordersContainer.innerHTML = '<div class="bx-etsy-tab-loading">⏳ Lade Bestellungen…</div>';
            loadTabContent('orders', params, null);
        });
    }

    // Letzten Tab beim Laden wiederherstellen (nur wenn noch gültig)
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored) {
        try {
            const data = JSON.parse(stored);
            if (Date.now() - data.timestamp < EXPIRATION_MS) {
                // noch gültig
                activateTab(data.tabId);
            } else {
                // abgelaufen -> löschen und ersten Tab aktivieren
                localStorage.removeItem(STORAGE_KEY);
                if (tabs.length > 0) {
                activateTab(tabs[0].getAttribute('href'));
                }
            }
        } catch (e) {
        // falls JSON ungültig -> reset
            localStorage.removeItem(STORAGE_KEY);
            if (tabs.length > 0) {
                activateTab(tabs[0].getAttribute('href'));
            }
        }
    } else if (tabs.length > 0) {
        // Standard: Ersten aktivieren
        activateTab(tabs[0].getAttribute('href'));
    }


});
</script>

<?php
 }
?>