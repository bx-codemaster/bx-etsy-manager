# BX Etsy Manager - Produktmapping Roadmap

Stand: 2026-07-15
Status: Entwurf zur Umsetzung im Modul (ohne CORE-Aenderungen)

## Zielbild

Das Produktmapping Etsy <-> Shop muss auch bei mehreren tausend Produkten schnell, sicher und nutzerfreundlich funktionieren.

Leitprinzipien:
- Shop-Produkt bleibt fuehrend, Etsy-Listing wird zugeordnet.
- Keine Voll-Listen im UI, stattdessen Suche, Filter, Pagination, Bulk-Aktionen.
- Mapping hybrid: automatisch vorschlagen, manuell bestaetigen.
- Lange Prozesse laufen asynchron im Hintergrund.
- Keine CORE-Dateien anfassen, nur Modul-Dateien/Hook-Ansatz.

## Problemstellung

Bei Dutzenden Produkten ist manuelles Mapping machbar. Bei Tausenden fuehrt derselbe Ansatz zu:
- langen Ladezeiten,
- unuebersichtlichen Tabellen,
- hoher Fehlerrate,
- schlechter User Experience.

Daher braucht es ein skalierbares Datenmodell, eine performante Datenabfrage und ein UX-Konzept mit Fokus auf Arbeitsablaeufe.

## Architektur-Entscheidung

Empfohlen wird eine modulinterne Mapping-Schicht mit eigener Tabelle und klaren Statuswerten.

### Mapping-Status
- `unmapped`: kein Match vorhanden
- `suggested`: Systemvorschlag liegt vor
- `mapped`: bestaetigtes Mapping
- `conflict`: widerspruechliche Zuordnung
- `archived`: historisch/inaktiv

### Match-Quelle
- `manual`
- `sku_exact`
- `ean_exact`
- `title_similarity`
- `hybrid_rule`

## Datenmodell (V1)

Vorschlag fuer eine zentrale Modul-Tabelle, z. B. `TABLE_BX_ETSY_PRODUCT_MAP`:

- `id` (PK)
- `shop_product_id` (int, not null)
- `etsy_listing_id` (bigint, not null)
- `mapping_status` (varchar)
- `confidence_score` (decimal(5,2), default 0)
- `match_source` (varchar)
- `is_manual_override` (tinyint(1), default 0)
- `sync_state` (varchar)  // `ok`, `pending`, `error`
- `sync_error` (text)
- `last_sync_at` (datetime)
- `created_at` (datetime)
- `updated_at` (datetime)
- `updated_by_admin_id` (int)

Empfohlene Indizes:
- (`shop_product_id`)
- (`etsy_listing_id`)
- (`mapping_status`)
- (`sync_state`)
- (`last_sync_at`)
- unique optional auf (`shop_product_id`, `etsy_listing_id`)

## Matching-Strategie (skalierbar)

### Stufe A: harte Treffer
- SKU exakt
- EAN/GTIN exakt

Regel:
- Hoher Confidence-Score, kann automatisiert auf `mapped` gehen (optional per Feature-Flag).

### Stufe B: weiche Treffer
- Titel-Aehnlichkeit
- Preisband
- Variantenmuster/Attributnaehe

Regel:
- Status `suggested`, Nutzer bestaetigt manuell.

### Stufe C: Konfliktbehandlung
- Mehrere Kandidaten mit aehnlich hohem Score
- Bereits bestehendes Mapping kollidiert mit neuem Treffer

Regel:
- Nie still ueberschreiben.
- Immer `conflict` setzen und in Konflikt-Inbox anzeigen.

## UX-Konzept fuer Tab Produkte

Inspiration aus Kategorienverwaltung: links filtern/strukturieren, rechts arbeiten.

### Layout
1. Linke Spalte:
- Kategorien-Baum (lazy load)
- Zaehler je Kategorie (`unmapped`, `suggested`, `conflict`)

2. Hauptbereich:
- Produktliste serverseitig paginiert
- schnelle Suchzeile (Titel, SKU, Etsy-ID)
- Filterchips (`Nur Konflikte`, `Nur ungemappt`, `Nur Vorschlaege > 80`)

3. Detailpanel rechts:
- Kandidaten aus Etsy mit Score
- Aktionen: `Map`, `Unmap`, `Als korrekt bestaetigen`, `Konflikt loesen`

### Bulk-Aktionen
- auf sichtbare Auswahl
- optional auf gefilterte Ergebnismenge
- Aktionen mit Undo-Fenster und Ergebnisprotokoll

### UX-Leitlinien
- unter 300 ms fuer Standardfilter (ohne externe API)
- kein Blockieren bei langen Jobs
- klare Statuskommunikation (`pending`, `ok`, `error`)
- reduzierte Klickpfade fuer wiederkehrende Aufgaben

## Performance-Konzept

- Strikte Server-Side Pagination
- Keyset/Cursor-Pagination statt grosser OFFSET-Werte (ab hohen Datenmengen)
- Delta-Sync statt Full-Rescan
- Caching von Aggregaten/Zaehlern pro Kategorie
- Queue/Background Jobs fuer:
  - Import Etsy-Listings
  - Re-Matching
  - Re-Sync geaenderter Datensaetze

## API- und Job-Schnitt (V1 Vorschlag)

Admin-Endpoints (modulintern):
- `action=products_list` (Filter, Sortierung, Pagination)
- `action=mapping_suggest` (fuer ein Produkt/Batch)
- `action=mapping_apply` (Map/Unmap/Confirm)
- `action=mapping_conflicts` (Inbox)
- `action=mapping_bulk` (Batch-Aktionen)
- `action=job_status` (Fortschritt laufender Tasks)

Job-Typen:
- `initial_index`
- `delta_sync`
- `rematch`
- `conflict_recheck`

## Umsetzungsplan (Roadmap)

### Phase A - Fundament
- Mapping-Tabelle + Install-/Update-Migration
- Repository/Service fuer CRUD und Listenabfragen
- Tab-Produkte Grundansicht mit serverseitiger Pagination
- Basisfilter (`status`, Suche, Kategorie)

Abnahmekriterien:
- Listenansicht reagiert stabil bei 10k+ Datensaetzen
- Keine CORE-Datei angepasst

### Phase B - Vorschlagslogik
- Harte Treffer (SKU/EAN)
- Confidence-Score und `suggested`
- UI fuer Vorschlag bestaetigen/ablehnen

Abnahmekriterien:
- Auto-Vorschlaege reproduzierbar
- Konflikte werden korrekt erkannt

### Phase C - Konflikt- und Bulk-Workflow
- Konflikt-Inbox
- Bulk-Aktionen inkl. Undo
- Protokollierung pro Aktion

Abnahmekriterien:
- Massenbearbeitung ohne UI-Blockade
- klare Ergebnisreports

### Phase D - Performance & Feinschliff
- Keyset-Pagination
- Kategorie-Zaehler-Cache
- Background-Jobs + Fortschritt
- UX-Polish (Ladezustaende, Fehlerdialoge, Tastaturfluss)

Abnahmekriterien:
- hervorragende Bedienbarkeit bei grossen Datenmengen
- stabile Laufzeit unter Last

## QA-Strategie

- Unit-Tests fuer Match-Regeln
- Integrations-Tests fuer Statuswechsel
- Lasttests mit 1k, 10k, 50k Mapping-Datensaetzen
- UI-Tests fuer Filter, Pagination, Bulk-Aktionen
- Regressionstests fuer Dashboard/andere Tabs

## Risiken und Gegenmassnahmen

1. Risiko: falsche Auto-Matches
- Gegenmassnahme: hoher Schwellenwert, sonst `suggested`

2. Risiko: langsame Listenabfragen
- Gegenmassnahme: Indizes, Keyset, Caching

3. Risiko: unklare Konflikte
- Gegenmassnahme: dedizierte Konflikt-Inbox und Explain-Text je Regel

4. Risiko: API-Limits Etsy
- Gegenmassnahme: Delta-Sync, Queue, Backoff, Retry

## Entscheidungsbedarf (offen)

- Schwellwerte fuer Auto-Mapping (`confidence_score`)
- Prioritaet der Match-Regeln (SKU vs. EAN bei Widerspruch)
- Verhalten bei manuellem Override und spaeteren Delta-Aenderungen
- Umfang von Bulk-Aktionen in V1
- Welche Kennzahlen im Tab-Produkte zuerst sichtbar sein muessen

## Kurzfazit

Das Mapping sollte nicht als grosse statische Tabelle umgesetzt werden, sondern als workflow-orientiertes System mit Vorschlaegen, Konfliktmanagement und asynchronen Prozessen. So bleibt die User Experience auch bei mehreren tausend Produkten schnell und kontrollierbar.
