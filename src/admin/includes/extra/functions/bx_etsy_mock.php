<?php
/**
 * Mock helper for Etsy Manager.
 */

if (!function_exists('bx_etsy_mock_enabled')) {
    function bx_etsy_mock_enabled() {
        return MODULE_BX_ETSY_MANAGER_MOCK_MODE === 'True';
    }
}

if (!function_exists('bx_etsy_get_mock_scenario')) {
    function bx_etsy_get_mock_scenario() {
        return (string)MODULE_BX_ETSY_MANAGER_MOCK_SCENARIO;
    }
}

if (!function_exists('bx_etsy_mock_response')) {
    function bx_etsy_mock_response($method, $path, $payload = null) {
        $scenario = bx_etsy_get_mock_scenario();
        $base_dir = dirname(__FILE__) . '/bx_etsy_fixtures/' . basename($scenario);
        
        $json_file = 'receipts.json'; // fallback
        
        if (strpos($path, '/receipts') !== false) {
            if (strpos($path, 'was_shipped=false') !== false) {
                $json_file = 'unshipped.json';
            } else {
                $json_file = 'receipts.json';
            }
        } elseif (strpos($path, '/reviews') !== false) {
            $json_file = 'reviews.json';
        } elseif (strpos($path, '/personalization') !== false) {
            $json_file = 'personalization.json';
        }
        
        $fixture_path = $base_dir . '/' . $json_file;
        
        if (file_exists($fixture_path)) {
            $content = file_get_contents($fixture_path);
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                if ($scenario === 'hohes_volumen') {
                    $today_start_ts = strtotime(date('Y-m-d 00:00:00'));
                    if ($today_start_ts === false) {
                        $today_start_ts = time();
                    }

                    if ($json_file === 'receipts.json' && isset($decoded['results']) && is_array($decoded['results'])) {
                        foreach ($decoded['results'] as $idx => &$receipt) {
                            if (!is_array($receipt)) {
                                continue;
                            }

                            // Stellt sicher, dass "Heute"-KPIs im Mock-Szenario stabil testbar sind.
                            $offset_seconds = (($idx % 48) * 900) + 3600;
                            $receipt['create_timestamp'] = (int)$today_start_ts + $offset_seconds;
                        }
                        unset($receipt);
                    }

                    if ($json_file === 'reviews.json' && isset($decoded['results']) && is_array($decoded['results'])) {
                        foreach ($decoded['results'] as $idx => &$review) {
                            if (!is_array($review)) {
                                continue;
                            }

                            $offset_seconds = (($idx % 24) * 1200) + 1800;
                            $review['create_timestamp'] = (int)$today_start_ts + $offset_seconds;
                        }
                        unset($review);
                    }
                }

                if (isset($decoded['http_code'])) {
                    $error_message = $decoded['error'] ?? 'Mocked API Error';
                    return array(
                        'success' => false,
                        'data' => $decoded,
                        'error' => $error_message
                    );
                }
                
                return array(
                    'success' => true,
                    'data' => $decoded,
                    'error' => ''
                );
            }
        }
        
        return array(
            'success' => false,
            'data' => array(),
            'error' => 'Mock Fixture nicht gefunden: ' . basename($scenario) . '/' . $json_file
        );
    }
}

if (!function_exists('bx_etsy_mock_scenario_select')) {
    function bx_etsy_mock_scenario_select($value, $constant) {
        $base_dir = dirname(__FILE__) . '/bx_etsy_fixtures';
        $options = array();
        
        if (is_dir($base_dir)) {
            $dirs = scandir($base_dir);
            foreach ($dirs as $dir) {
                if ($dir !== '.' && $dir !== '..' && is_dir($base_dir . '/' . $dir)) {
                    $options[] = array('id' => $dir, 'text' => $dir);
                }
            }
        }
        
        if (empty($options)) {
            $options[] = array('id' => 'leerer_shop', 'text' => 'leerer_shop');
        }
        
        return xtc_draw_pull_down_menu('configuration[' . $constant . ']', $options, $value);
    }
}
