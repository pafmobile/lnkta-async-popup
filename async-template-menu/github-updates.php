<?php

if (!defined('ABSPATH')) {
    exit;
}

add_filter('update_plugins_github.com', static function ($update, $plugin_data, $plugin_file) {
    $repository = 'pafmobile/lnkta-async-popup';
    $repository_url = 'https://github.com/' . $repository;

    if ($plugin_file !== plugin_basename(__DIR__ . '/async-template-menu.php')
        || ($plugin_data['UpdateURI'] ?? '') !== $repository_url) {
        return $update;
    }

    $response = wp_safe_remote_get('https://api.github.com/repos/' . $repository . '/releases/latest', [
        'timeout' => 5,
        'headers' => ['Accept' => 'application/vnd.github+json'],
    ]);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return $update;
    }

    $release = json_decode(wp_remote_retrieve_body($response), true);
    $tag = $release['tag_name'] ?? '';
    if (!is_string($tag) || !preg_match('/^v([0-9]+\.[0-9]+\.[0-9]+)$/D', $tag, $version)
        || !empty($release['draft']) || !empty($release['prerelease'])) {
        return $update;
    }

    return [
        'id' => $repository_url,
        'slug' => 'async-template-menu',
        'version' => $version[1],
        'url' => $repository_url . '/releases/tag/' . $tag,
        'package' => 'https://raw.githubusercontent.com/' . $repository . '/' . $tag . '/dist/async-template-menu.zip',
        'requires_php' => '7.4',
    ];
}, 10, 3);
