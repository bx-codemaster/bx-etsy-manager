<?php 
  defined('_VALID_XTC') or die('Direct Access to this location is not allowed.');

  if (basename($_SERVER['PHP_SELF']) == 'bx_etsymanager.php') {
?>
<style>
  /* BX Etsy Manager Admin Styles */
  #headboard {
    display: flex; 
    flex-direction: row; 
    justify-content: flex-start;
    width: 100%;
    align-items: center; 
    background: #AF417E; 
    color: #ffffff; 
    border-radius: 4px; 
    margin-bottom: 10px; 
    padding: 4px 0 2px 0;
    line-height: 30px;
  }

  #headboard .main {
    margin: 5px 10px;
  }

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

  .boxRight .contentTable {
    border: 1px solid #ccc;
  }

  .boxRight .contentTable:nth-child(even) {
    margin-bottom: 5px;
    border-top: none;
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

  /* Future: fixed message stack (animated via JS) */
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

</style>
<?php } ?>