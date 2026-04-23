<?php

function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function get_post($key, $default = '') {
    return isset($_POST[$key]) ? sanitize($_POST[$key]) : $default;
}

function write_csv($file, $row) {
    $fp = fopen($file, 'a');

    if (!$fp) {
        return false;
    }

    // lock file so multiple submissions don’t collide
    if (flock($fp, LOCK_EX)) {
        fputcsv($fp, $row);
        flock($fp, LOCK_UN);
    } else {
        fclose($fp);
        return false;
    }

    fclose($fp);
    return true;
}

function ensure_csv_header($file, $header) {
    if (!file_exists($file)) {
        write_csv($file, $header);
    }
}
