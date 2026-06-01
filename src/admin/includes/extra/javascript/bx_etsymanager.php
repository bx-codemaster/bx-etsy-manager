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
            } else if (Notification.permission !== 'denied') {
                Notification.requestPermission().then(function(permission) {
                    if (permission === 'granted') {
                        new Notification(title, { body: message });
                    }
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
                    const response = JSON.parse(xhr.responseText);
                    
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
                    btn.innerHTML = '❌ Fehler beim Verarbeiten';
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

    // Auto-Initialisierung für Countdown-Container aus dem Admin-Markup
    var autoContainers = document.querySelectorAll('#bx-etsy-token-countdown[data-expires-timestamp], .bx-etsy-token-countdown[data-expires-timestamp]');
    for (var i = 0; i < autoContainers.length; i++) {
        var autoContainer = autoContainers[i];
        var expiresRaw = autoContainer.getAttribute('data-expires-timestamp');
        var expiresTimestamp = parseInt(expiresRaw, 10);

        if (!isNaN(expiresTimestamp) && expiresTimestamp > 0) {
            if (!autoContainer.id) {
                autoContainer.id = 'bx-etsy-token-countdown-' + i;
            }
            initTokenCountdown(expiresTimestamp, autoContainer.id);
        }
    }
    
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

});
</script>

<?php
 }
?>