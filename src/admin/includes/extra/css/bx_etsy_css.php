<?php 
  defined('_VALID_XTC') or die('Direct Access to this location is not allowed.');

  if (basename($_SERVER['PHP_SELF']) == 'bx_etsymanager.php') {
?>
<style>
  /* BX Etsy Manager Admin Styles */
  .etsy-tabs .tab-nav {
    list-style: none; 
    padding: 0;
    display: flex;
    gap: 6px;
    margin:0;
  }
  .etsy-tabs .tab-nav li a {
    padding: 6px 10px;
    background: #f1f1f1;
    border: 1px solid #ccc;
    border-bottom: none;
    display: inline-block;
    border-radius: 4px 4px 0 0;
    text-decoration: none;
    color: #222;
  }
  .etsy-tabs .tab-nav li a.active {
    background: #AF417E;
    color: #fff;
    font-weight: bold;
  }
  .etsy-tabs .tab-content {
    border-top: 1px solid #ccc;
  }
  .etsy-tabs .tab-content > div {
    display: none;
    padding: 5px;
    border: 1px solid #ccc;
    background: #fff;
    border-top: none;
  }
  .etsy-tabs .tab-content > div.active {
    display: block;
  }

  .bx-sidebar > [id^="tab-"][id$="-right"] {
    display: none;
  }

  .bx-sidebar > [id^="tab-"][id$="-right"].active {
    display: block;
  }

  .bx-sidebar .contentTable {
    border: 1px solid #ccc;
  }

  .bx-sidebar .contentTable:nth-child(even) {
    margin-bottom: 5px;
    border-top: none;
  }

  .dashboard-intro {
    margin-bottom: 15px;
  }

  .dashboard-intro p {
    margin: 6px 0 0 0;
    color: #666;
  }

  .dashboard-table {
    margin-bottom: 18px;
  }

  .dashboard-cell {
    padding: 0 10px 10px 0;
  }

  .dashboard-cell-last-row {
    padding-right: 0;
  }

  .dashboard-card,
  .dashboard-panel-card,
  .dashboard-activity-card {
    background: #ffffff;
    border: 1px solid #d1d5db;
    border-radius: 6px;
  }

  .dashboard-card {
    /* Ermöglicht die flexible Ausrichtung */
    display: flex;
    flex-direction: column;
    
    /* Verteilt die Elemente gleichmäßig von oben bis unten */
    justify-content: space-between; 
    
    /* Deine Mindesthöhe */
    min-height: 94px; 
    
    /* Optional: Etwas Innenabstand, damit der Text nicht am Rand klebt */
    padding: 12px; 
    box-sizing: border-box; /* Verhindert, dass das Padding die 94px vergrößert */
  }

  .dashboard-panel-card {
    padding: 14px;
    min-height: 220px;
  }

  .dashboard-activity-card {
    padding: 14px;
    margin-bottom: 18px;
  }

  .dashboard-kpi-label,
  .dashboard-kpi-note {
    font-size: 12px;
  }
  
  .dashboard-kpi-note {
    text-align: center;
  }

  .dashboard-kpi-label {
    text-transform: uppercase;
  }

  .dashboard-kpi-value {
    font-size: 26px;
    font-weight: bold;
    margin: 10px 0;
    text-align: center;
  }

  .dashboard-card-orange {
    background: #fff7ed;
    border-color: #fdba74;
    color: #9a3412;
  }

  .dashboard-card-orange .dashboard-kpi-value {
    color: #7c2d12;
  }

  .dashboard-card-blue {
    background: #eff6ff;
    border-color: #93c5fd;
    color: #1d4ed8;
  }

  .dashboard-card-blue .dashboard-kpi-value {
    color: #1e3a8a;
  }

  .dashboard-card-green {
    background: #ecfdf5;
    border-color: #86efac;
    color: #15803d;
  }

  .dashboard-card-green .dashboard-kpi-value {
    color: #166534;
  }

  .dashboard-card-violet {
    background: #faf5ff;
    border-color: #d8b4fe;
    color: #7e22ce;
  }

  .dashboard-card-violet .dashboard-kpi-value {
    color: #581c87;
  }

  .dashboard-card-red {
    background: #fef2f2;
    border-color: #fca5a5;
    color: #dc2626;
  }

  .dashboard-card-red .dashboard-kpi-value {
    color: #991b1b;
  }

  .dashboard-card-slate {
    background: #f8fafc;
    border-color: #cbd5e1;
    color: #475569;
  }

  .dashboard-card-slate .dashboard-kpi-value {
    color: #0f172a;
  }

  .dashboard-panel-title {
    margin-bottom: 10px;
  }

  .dashboard-list-head {
    background: #f3f4f6;
  }

  .dataTableHeadingContent {
    padding-bottom: 0 !important;
  }

  .bx-orders-table {
    width: calc(100% - 0px);
    margin: 6px 0;
    border: 1px solid #d9d9d9;
    border-left: 3px solid #af417e;
    border-radius: 4px;
    border-spacing: 0;
    background: #fdfdfd;
    box-shadow: var(--bx-shadow-lg);
    color: #444444;
  }

  .bx-orders-table th,
  .bx-orders-table td {
    padding: 8px 10px;
    border-bottom: 1px solid #e7e7e7;
    text-align: left;
  }

  .bx-orders-table th {
    background: #f3f3f3;
    color: #5a5a5a;
    font-weight: bold;
    white-space: nowrap;
    padding: 0.25rem 0.5rem;
  }

  .bx-orders-table th .container {
    display: inline-flex;
    flex-direction: column;
    flex-wrap: wrap;
    justify-content: flex-start;
    align-items: center;
    align-content: flex-start;
    gap: 4px;
  }
  
  .bx-orders-table tr:last-child td {
    border-bottom: 0;
  }

  .bx-orders-table tbody tr:hover td,
  .bx-orders-table tr:not(:first-child):hover td {
    background: #fff7fb;
    cursor: pointer;
  }

  .bx-orders-table tbody tr.bx-orders-selected td,
  .bx-orders-table tr:not(:first-child).bx-orders-selected td {
    background: #ffdaec;
    cursor: pointer;
  }



  .bx-etsy-tab-loading {
    text-align: center;
    padding: 40px 10px;
    color: #888;
  }

  .bx-etsy-tab-error {
    text-align: center;
    padding: 20px 10px;
    color: #dc3545;
    font-weight: bold;
  }









  
  /* Future: Modal (Etsy device management / diagnostics) */
  #etsyModal {
    display: none; 
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.5);
  }
  #etsyModal .modal-content {
    padding: 0; 
    border: 1px solid #aaa; 
    background-color: #fff; 
    font-family: Arial, sans-serif; 
    font-size: 14px; 
    margin: auto; 
    box-shadow: 0 2px 5px rgba(0,0,0,0.25);
    width: 400px; 
    margin-top: 10%;
    border-radius: 8px;
    overflow: hidden;
  }
  #etsyModal .modal-content h3 {
    margin: 0;
    padding: 8px 15px;
    background-color: #AF417E;
    color: white;
    font-size: 16px;
    font-weight: bold;
  }

  #etsyModal .modal-content > div {
    padding: 15px;
  }

  #etsyModal .modal-content > div > div {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
  }
  #etsyModal .modal-content > div > div > label,
  #etsyModal .modal-content > div > div > span {
    width: 120px; 
    flex-shrink: 0; 
    margin-right: 10px; 
    font-weight: bold;
  }
  #etsyModal .modal-content > div > div > input {
    flex-grow: 1; 
    padding: 6px; 
    border: 1px solid #aaa;
  }
  #etsyModal #result_output {
    color: #AF417E; 
    flex-grow: 1; 
    padding: 6px; 
    border: 1px solid #aaa; 
    text-align: center;
    background-color: #f9f9f9;
  }
  #etsyModal .close {
    color: white;
    float: right;
    font-size: 20px;
    font-weight: bold;
    cursor: pointer;
  }
  #etsyModal .close:hover,
  #etsyModal .close:focus {
    color: black;
    text-decoration: none;
    cursor: pointer;
  }

  /* fixed message stack (animated via JS) */
  .fixed_messageStack {
    position: fixed;
    top: 88px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 1000;
    width: 80%;
    padding: 10px 0;
    text-align: center;
    display: none;
  }

  /* Container für die Ladeanzeige */
  .bx-etsy-tab-loading {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 40px;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      color: #323130;
      font-size: 14px;
  }

  /* Der Microsoft-Style Spinner */
  .ms-spinner {
      width: 32px;
      height: 32px;
      border: 3px solid #f3f2f1; /* Heller Hintergrund-Ring */
      border-top: 3px solid #0078d4; /* Microsoft Standard-Blau */
      border-radius: 50%;
      animation: ms-spin 0.8s cubic-bezier(0.4, 0, 0.2, 1) infinite;
      margin-bottom: 12px;
  }

  /* Flüssige Animation */
  @keyframes ms-spin {
      0% {
          transform: rotate(0deg);
      }
      100% {
          transform: rotate(360deg);
      }
  }
</style>

<style>
/**
 * BX-Admin-UI v1.0
 * Lightweight Admin Component & Grid Framework
 */

/* ==========================================================================
   1. TOKENS & VARIABLES
   ========================================================================== */
:root {
  --bx-primary: #AF417E;
  --bx-primary-hover: #d34e97;
  --bx-white: #ffffff;
  --bx-bg-input: #f8fafc;
  --bx-bg-card: #eceff3;
  --bx-text-main: #0f172a;
  --bx-text-muted: #64748b;
  --bx-border-color: #cbd5e1;
  --bx-border-accent: #c41e3a;
  --bx-radius: 6px;
  --bx-shadow-sm: 0 4px 12px rgba(15, 23, 42, 0.08);
  --bx-shadow-lg: 0 18px 30px -14px rgba(15, 23, 42, 0.25);
  --bx-gradient-primary: linear-gradient(180deg, rgb(195, 101, 152) 0%, rgb(175, 65, 126) 55%, rgb(146, 46, 102) 100%);
  --bx-gradient-secondary: linear-gradient(180deg, rgb(193, 210, 1) 0%, rgb(167, 184, 0) 55%, rgb(139, 156, 0) 100%);
}

html {
  scrollbar-gutter: stable;
}

/* ==========================================================================
   2. LAYOUT ENGINE (Robustes 2-Spalten Layout für Admin-Panels)
   ========================================================================== */

.error_panel,
.warning_panel,
.info_panel,
.success_panel {
  box-sizing: border-box;
  border-radius: var(--bx-radius);
  padding: 0.75rem 1rem;
  margin-bottom: 1rem;
}

.error_panel {
  background-color: #ffb8b8;
  border: 1px solid #fc6767;
  color: #991b1b;
}

.warning_panel {
  background-color: #fff7ed;
  border: 1px solid #fdba74;
  color: #b45309;
}

.info_panel {
  background-color: #e0f7fa;
  border: 1px solid #4dd0e1;
  color: #006064;
}
.success_panel {
  background-color: #f0fdf4;
  border: 1px solid #4ade80;
  color: #065f46;
}

.bx-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 480px);
  gap: 0.25rem;
  align-items: start;
  margin: 0.5rem 0;
  width: 100%;
  box-sizing: border-box;
}

.bx-main-content,
.bx-sidebar {
  min-width: 0;
  max-width: 100%;
  margin: 0.25rem;
}

@media (max-width: 1024px) {
  .bx-grid {
    grid-template-columns: 1fr;
  }
  .bx-sidebar {
    max-width: 100%;
    margin: 0.25rem auto;
  }
}

/* ==========================================================================
   3. COMPONENTS
   ========================================================================== */

/* Headboard / Sektions-Header */
.bx-headboard {
  display: flex; 
  flex-direction: row; 
  justify-content: flex-start;
  align-items: center;
  border-radius: var(--bx-radius);
  box-shadow: var(--bx-shadow-lg);
  margin-bottom: 0.25rem; 
  min-height: 40px;
  line-height: 30px;
  box-sizing: border-box;
  background: var(--bx-gradient-primary);
  color: var(--bx-white);
  padding: 5px 10px;
}

.bx-headboard--secondary {
  background: var(--bx-gradient-secondary);
}

/* ==========================================================================
   HEADBOARD SEARCH EXTENSION
   ========================================================================== */

/* Headboard Flex-Container anpassen */
.bx-headboard--with-search {
  display: flex;
  flex-direction: row;
  justify-content: space-between;
  align-items: center;
  padding: 4px 10px; /* Schlankes Padding, verhindert Aufblähen */
  gap: 1rem;
}

.bx-headboard-title {
  white-space: nowrap;
}

/* Suchbereich-Container */
.bx-headboard-search form {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 0.35rem;
  margin: 0;
  padding: 0;
}

/* Kompaktes Input-Feld für Header/Headboards */
.bx-input-compact {
  height: 28px;
  padding: 2px 8px;
  font-size: 0.85rem;
  border: 1px solid var(--bx-border-color);
  border-radius: var(--bx-radius);
  background-color: var(--bx-white);
  color: var(--bx-text-main);
  box-sizing: border-box;
  outline: none;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.bx-input-compact:focus-visible {
  border-color: var(--bx-white);
  box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.5);
}

/* Icon-Button ohne störende Ränder/Margins */
.bx-icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: transparent;
  border: none;
  padding: 0;
  margin: 0;
  cursor: pointer;
  line-height: 1;
  opacity: 0.9;
  transition: opacity 0.2s ease, transform 0.1s ease;
}

.bx-icon-btn:hover {
  opacity: 1;
  transform: scale(1.08);
  background-color: transparent;
}

/* Barrierefreie Klasse für Screenreader (versteckt das Label visuell) */
.visually-hidden {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

/* Container / Panels */
.bx-panel {
  box-sizing: border-box;
  background: var(--bx-white);
  border: 1px solid var(--bx-border-color);
  border-radius: var(--bx-radius);
  box-shadow: var(--bx-shadow-sm);
  padding: 1.0rem;
  margin: 0.5rem auto;
}

/* Akzentuierte Panels - Erstmal ausblenden */
.bx-panel--accent {
  border-left: 3px solid var(--bx-primary);
}

.bx-panel-title {
  font-size: 1.1rem;
  font-weight: bold;
  margin-bottom: 0.5rem;
  color: var(--bx-primary);
}

/* Formulare & Eingabefelder */
.bx-form {
  display: block;
  box-sizing: border-box;
  width: 100%;
  padding: 1.5rem;
  background: var(--bx-white);
  border: 1px solid var(--bx-border-color);
  border-radius: var(--bx-radius);
  box-shadow: var(--bx-shadow-sm);
  color: var(--bx-text-main);
}

.bx-form-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-bottom: 1.25rem;
}

.bx-form-group label {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--bx-text-main);
}

.bx-input,
.bx-textarea,
.bx-select {
  width: 100%;
  padding: 0.75rem 1rem;
  font-size: 1rem;
  border: 1.5px solid var(--bx-border-color);
  border-radius: var(--bx-radius);
  background-color: var(--bx-bg-input);
  transition: all 0.2s ease-in-out;
  box-sizing: border-box;
  color: var(--bx-text-main);
}

.bx-input:focus-visible,
.bx-textarea:focus-visible,
.bx-select:focus-visible {
  outline: none;
  border-color: var(--bx-primary);
  background-color: var(--bx-white);
  box-shadow: 0 0 0 4px rgba(175, 65, 126, 0.15);
}

.bx-input:user-invalid,
.bx-textarea:user-invalid {
  border-color: #ef4444;
  background-color: #fef2f2;
}

/* Custom Select Styling */
.bx-select-wrapper {
  position: relative;
  width: 100%;
}

.bx-select {
  padding-right: 2.5rem;
  -webkit-appearance: none;
  -moz-appearance: none;
  appearance: none;
  cursor: pointer;
}

.bx-select-wrapper::after {
  content: "";
  position: absolute;
  right: 1rem;
  top: 50%;
  transform: translateY(-50%);
  width: 0.8rem;
  height: 0.8rem;
  pointer-events: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b' stroke-width='2.5'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19.5 8.25l-7.5 7.5-7.5-7.5' /%3E%3C/svg%3E");
  background-size: contain;
  background-repeat: no-repeat;
  transition: transform 0.2s ease;
}

.bx-select-wrapper:focus-within::after {
  transform: translateY(-50%) rotate(180deg);
}

/* Buttons */
.bx-btn {
  display: inline-block;
  width: 100%;
  padding: 0.875rem;
  font-size: 1rem;
  font-weight: 600;
  text-align: center;
  color: var(--bx-white);
  background-color: var(--bx-primary);
  border: none;
  border-radius: var(--bx-radius);
  cursor: pointer;
  transition: background-color 0.2s ease;
}

.bx-btn:hover {
  background-color: var(--bx-primary-hover);
}

/* Accordion Card (<details> Modul) */
.bx-card {
  position: relative;
  border: 1px solid var(--bx-border-color);
  border-radius: var(--bx-radius);
  background: var(--bx-gradient-primary);
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.06);
  margin: 5px 0;
  overflow: hidden;
}

.bx-card-summary {
  list-style: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  color: var(--bx-white);
  font-weight: bold;
}

.bx-card-summary::-webkit-details-marker { display: none; }

.bx-card-body {
  padding: 12px;
  background: var(--bx-bg-card);
  border-left: 4px solid var(--bx-border-accent);
  color: var(--bx-text-main);
}


/* ==========================================================================
   4. ADVANCED HTML5 COMPONENTS
   ========================================================================== */

/* Native Modal Dialoge (<dialog>) */
.bx-dialog {
  border: none;
  border-radius: var(--bx-radius);
  padding: 1.5rem;
  background: var(--bx-white);
  box-shadow: var(--bx-shadow-lg);
  max-width: 500px;
  width: 90%;
}

.bx-dialog::backdrop {
  background: rgba(15, 23, 42, 0.5);
  backdrop-filter: blur(2px);
}

/* Fieldset & Legend Gruppierung */
.bx-fieldset {
  border: 1px solid var(--bx-border-color);
  border-radius: var(--bx-radius);
  padding: 1rem 1.25rem 1.25rem;
  margin-bottom: 1.25rem;
  background: var(--bx-white);
}

.bx-legend {
  font-weight: 700;
  font-size: 0.875rem;
  color: var(--bx-primary);
  padding: 0 0.5rem;
}

/* Semantischer Such-Container (<search>) */
.bx-search-bar {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

/* Progress & Meter Bars */
.bx-progress, 
.bx-meter {
  width: 100%;
  height: 0.75rem;
  border-radius: var(--bx-radius);
  overflow: hidden;
  appearance: none;
  border: none;
}

.bx-progress::-webkit-progress-bar { background-color: var(--bx-bg-input); }
.bx-progress::-webkit-progress-value { background-color: var(--bx-primary); }
.bx-progress::-moz-progress-bar { background-color: var(--bx-primary); }

/* Text Highlighting (<mark>) */
.bx-highlight {
  background-color: #fef08a;
  color: var(--bx-text-main);
  padding: 0.1rem 0.3rem;
  border-radius: 3px;
}

/* ==========================================================================
   MODERNE LISTEN (UL / LI)
   ========================================================================== */

.bx-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.bx-list li {
  position: relative;
  padding-left: 1.5rem;
  margin-bottom: 0.5rem;
  color: var(--bx-text-main);
  line-height: 1.5;
}

.bx-list li:last-child {
  margin-bottom: 0;
}

/* Custom Minimal Bullet Point */
.bx-list li::before {
  content: "";
  position: absolute;
  left: 0.35rem;
  top: 0.55em;
  width: 6px;
  height: 6px;
  background-color: var(--bx-primary);
  border-radius: 50%;
}

.bx-list li strong {
  color: var(--bx-text-main);
}

/* Optionale Card-Listen-Variante für Einstellungsübersichten */
.bx-list-cards {
  list-style: none;
  padding: 0;
  margin: 0 0 1.25rem 0;
}

.bx-list-cards li {
  background: var(--bx-white);
  border: 1px solid var(--bx-border-color);
  border-radius: var(--bx-radius);
  padding: 0.65rem 1rem;
  margin-bottom: 0.5rem;
  box-shadow: var(--bx-shadow-sm);
}
/* ==========================================================================
   DEFINITIONSLISTEN (DL / DT / DD)
   ========================================================================== */

/* Zweispaltige Formular- & Stammdatenübersicht */
.bx-dl-horizontal {
  margin: 0 0 1.5rem 0;
  padding: 0;
  background: var(--bx-white);
  border: 1px solid var(--bx-border-color);
  border-radius: var(--bx-radius);
  overflow: hidden;
}

.bx-dl-item {
  display: flex;
  border-bottom: 1px solid #f1f5f9;
}

.bx-dl-item:last-child {
  border-bottom: none;
}

.bx-dl-horizontal dt {
  flex: 0 0 30%;
  padding: 0.65rem 1rem;
  font-weight: 600;
  font-size: 0.75rem;
  color: var(--bx-text-muted);
  background-color: var(--bx-bg-input);
  border-right: 1px solid #f1f5f9;
}

.bx-dl-horizontal dd {
  flex: 1;
  padding: 0.65rem 1rem;
  margin: 0;
  color: var(--bx-text-main);
  font-size: 0.75rem;
}

/* Card-Formatierung für KPI-Widgets & Sidebar-Details */
.bx-dl-cards {
  margin: 0;
  padding: 0;
}

.bx-dl-card-item {
  background: var(--bx-white);
  border: 1px solid var(--bx-border-color);
  border-left: 4px solid var(--bx-primary);
  border-radius: var(--bx-radius);
  padding: 0.75rem 1rem;
  margin-bottom: 0.65rem;
  box-shadow: var(--bx-shadow-sm);
}

.bx-dl-cards dt {
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--bx-text-muted);
  margin-bottom: 0.25rem;
}

.bx-dl-cards dd {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--bx-text-main);
}

.bx-dl-cards .bx-dl-card-item:last-child {
  margin: 0;
}
</style>
<?php } ?>