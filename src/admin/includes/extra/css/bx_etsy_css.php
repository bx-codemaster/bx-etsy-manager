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

<?php } ?>