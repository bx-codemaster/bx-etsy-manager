# Etsy API Mock-Modus Implementierung

Dieses Dokument beschreibt die technische Umsetzung des Mock-Modus für das Etsy-Modul. Ziel ist es, API-Antworten und OAuth-Verbindungen basierend auf lokalen Fixtures zu simulieren, ohne echte HTTP-Requests zu senden.

## User Review Required

> [!IMPORTANT]
> Bitte prüfen Sie die geplanten Änderungen. Gibt es weitere Stellen außer den Dashboard KPIs und der Personalisierung, die von diesem Mock-Modus betroffen sein sollen? Die vorgeschlagene Architektur deckt automatisch alle Funktionen ab, die über `bx_etsy_api_request()` laufen.

## Proposed Changes

### Konfiguration & Module Setup

#### [MODIFY] admin/includes/modules/system/bx_etsymanager.php
- **Änderung:** Hinzufügen von zwei neuen Konfigurationsfeldern bei der Modulinstallation (`install()` Methode):
  - `MODULE_BX_ETSY_MANAGER_MOCK_MODE`: True/False Schalter (Standard: False).
  - `MODULE_BX_ETSY_MANAGER_MOCK_SCENARIO`: Dropdown-Menü, das durch eine Funktion gefüllt wird (liest dynamisch die Verzeichnisse der Fixtures aus).
- **Änderung:** Erweiterung des Arrays in der Methode `keys()`, damit die neuen Felder bei Deinstallation korrekt entfernt werden.

### Neue Kernlogik für Mocking

#### [NEW] admin/includes/extra/functions/bx_etsy_mock.php
- **Inhalt:** Zentrale Funktionen für den Mock-Modus.
  - `bx_etsy_mock_enabled()`: Prüft, ob `MODULE_BX_ETSY_MANAGER_MOCK_MODE` auf `True` steht.
  - `bx_etsy_get_mock_scenario()`: Gibt das aktuell gewählte Szenario aus `MODULE_BX_ETSY_MANAGER_MOCK_SCENARIO` zurück.
  - `bx_etsy_mock_response($method, $path, $payload)`: Verarbeitet Anfragen, lädt die passende `.json` Fixture basierend auf der URL und dem ausgewählten Szenario und simuliert ggf. API-Fehler (z.B. HTTP 429).
  - `bx_etsy_mock_scenario_select(...)`: Hilfsfunktion, um die Szenario-Ordner für das Modul-Dropdown auszulesen.

#### [NEW] admin/includes/extra/functions/bx_etsy_fixtures/
- **Inhalt:** Verzeichnisstruktur für Testszenarien.
  - `leerer_shop/receipts.json`, `leerer_shop/unshipped.json`
  - `hohes_volumen/receipts.json`, `hohes_volumen/unshipped.json`
  - `api_fehler/receipts.json` (enthält z.B. `{"http_code": 429, "error": "Rate limit exceeded"}`)

### API Interception & SDK-Fallback Bypass

#### [MODIFY] admin/includes/extra/functions/bx_etsy_general.php
- **Änderung in `bx_etsy_api_request()`:** 
  Direkt am Anfang der Funktion wird geprüft:
  ```php
  if (function_exists('bx_etsy_mock_enabled') && bx_etsy_mock_enabled()) {
      return bx_etsy_mock_response($method, $path, $payload);
  }
  ```
- **Änderung in `bx_etsy_get_listing_personalization()` & `bx_etsy_update_listing_personalization()`:**
  Erweiterung der IF-Bedingung um `&& (!function_exists('bx_etsy_mock_enabled') || !bx_etsy_mock_enabled())`, damit im Mock-Modus der SDK-Pfad umgangen wird und stattdessen der API-Fallback greift (der wiederum von `bx_etsy_api_request()` gemockt wird).

### OAuth & Token Simulation

#### [MODIFY] admin/includes/extra/functions/bx_etsy_oauth.php
- **Änderung in `bx_etsy_get_valid_token()`:**
  Wenn der Mock-Modus aktiv ist, wird die Datenbankabfrage übersprungen und stattdessen ein syntetisches, ewig gültiges Token zurückgegeben:
  ```php
  if (function_exists('bx_etsy_mock_enabled') && bx_etsy_mock_enabled()) {
      return array(
          'access_token' => 'mock_token_123',
          'refresh_token' => 'mock_refresh_123',
          'expires_at' => date('Y-m-d H:i:s', time() + 3600 * 24 * 365),
      );
  }
  ```

### Admin UI Anpassungen

#### [MODIFY] admin/bx_etsymanager.php
- **Änderung:** Anzeige eines auffälligen Warn-Banners, wenn der Mock-Modus aktiv ist.
  Einbindung eines HTML-Blocks direkt unterhalb der Seitenüberschrift:
  ```html
  <div style="background-color: #ffcc00; color: #000; padding: 10px; text-align: center; font-weight: bold; border: 2px solid #cc0000; margin-bottom: 15px;">
    ⚠️ MOCK-MODUS AKTIV &ndash; Szenario: [Aktuelles Szenario]
  </div>
  ```

## Verification Plan

### Automated Tests
- Keine spezifischen automatisierten Tests vorgesehen; Überprüfung erfolgt durch Aufruf der Modul-UI im Browser.

### Manual Verification
1. Modulkonfiguration aufrufen, die Felder `MODULE_BX_ETSY_MANAGER_MOCK_MODE` und `-SCENARIO` überprüfen und aktivieren.
2. Das Admin-Dashboard `admin/bx_etsymanager.php` aufrufen. Der Warn-Banner sollte sichtbar sein.
3. Überprüfung, dass die KPIs (Umsatz, Bestellungen etc.) den Daten aus den JSON-Fixtures des gewählten Szenarios entsprechen.
4. Umschalten des Szenarios auf z.B. "api_fehler" und Überprüfung, ob das System die passenden Fehlermeldungen (z.B. "Verbindung aktiv, Abruf derzeit nicht möglich") anzeigt.
5. Die Personalisierungs-Funktion testen, um sicherzustellen, dass die SDK umgangen wird und Fixture-Daten ausgeben werden.
