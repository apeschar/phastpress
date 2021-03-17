<?php
namespace Kibo\PhastPlugins\PhastPress;

use RuntimeException;
use WP_Error;

class Updater {
    const LATEST_RELEASE_URL = 'https://api.github.com/repos/apeschar/phastpress/releases/latest';

    const GITHUB_TOKEN = '4706aa95ad7c4936f16874f3e209b58b5f0df7f5';

    const VERSION_PATTERN = '~^\d+(\.\d+){1,2}$~';

    const META_LINE_PATTERN = '~^\s*(?<name>.+?):\s+(?<value>.+?)\s*$~';

    public static function setup() {
        new static();
    }

    private function __construct() {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check']);
        add_filter('install_plugins_pre_plugin-information', [$this, 'information'], 0, 0);
    }

    public function check($result) {
        try {
            return $this->doCheck($result);
        } catch (UpdaterException $e) {
            error_log(sprintf(
                'Caught %s while checking for update: (%d) %s',
                get_class($e),
                $e->getCode(),
                $e->getMessage()
            ));
            return $result;
        }
    }

    private function doCheck($result) {
        try {
            $data = $this->getLatestRelease();
        } catch (UpdaterException $e) {
            $data = $this->getLatestRelease(true);
        }

        if (!preg_match(self::VERSION_PATTERN, $data['tag_name'])) {
            throw new UpdaterException(
                "Tag on latest release does not look like a version number: {$data['tag_name']}"
            );
        }

        if (!version_compare(PHASTPRESS_VERSION, $data['tag_name'], '<')) {
            return $result;
        }

        $filename = "phastpress-{$data['tag_name']}.zip";

        $result->response[$this->getPluginFilename()] = (object) [
            'slug' => 'phastpress',
            'version' => PHASTPRESS_VERSION,
            'new_version' => $data['tag_name'],
            'last_updated' => date('Y-m-d', strtotime($data['published_at'])),
            'package' => $this->getAssetUrl($data, $filename),
            'author' => $this->getMeta($data, 'Author'),
            'requires' => $this->getMeta($data, 'Requires at least'),
            'tested' => $this->getMeta($data, 'Tested up to'),
            'requires_php' => $this->getMeta($data, 'Requires PHP'),
            'homepage' => 'https://github.com/apeschar/phastpress#readme',
            'icons' => [
                'default' => "https://cdn.jsdelivr.net/gh/apeschar/phastpress@{$data['tag_name']}/logo.png",
            ],
        ];

        return $result;
    }

    private function getLatestRelease($useToken = false) {
        $response = wp_remote_get(
            self::LATEST_RELEASE_URL,
            [
                'headers' => $useToken ? ['Authorization' => 'token ' . self::GITHUB_TOKEN] : [],
            ]
        );

        if ($response instanceof WP_Error) {
            throw new UpdaterException(sprintf(
                'Could not download latest release info from GitHub: (%s) %s',
                $response->get_error_code(),
                $response->get_error_message()
            ));
        }

        if ($response['response']['code'] < 200 || $response['response']['code'] > 299) {
            throw new UpdaterException(sprintf(
                'Got HTTP %d from GitHub releases API',
                $response['response']['code']
            ));
        }

        $data = json_decode($response['body'], true);

        if (!is_array($data)) {
            throw new UpdaterException('Did not receive expected JSON object from GitHub releases API');
        }

        return $data;
    }

    private function getPluginFilename() {
        return plugin_basename(PHASTPRESS_PLUGIN_FILE);
    }

    private function getAssetUrl(array $data, $filename) {
        foreach ($data['assets'] as $asset) {
            if ($asset['name'] === $filename) {
                return $asset['browser_download_url'];
            }
        }
        throw new UpdaterException("No asset named {$filename} on GitHub release");
    }

    private function getMeta(array $data, $name) {
        foreach (explode("\n", $data['body']) as $line) {
            if (preg_match(self::META_LINE_PATTERN, $line, $match)
                && !strcasecmp($match['name'], $name)
                && $match['value'] != ''
            ) {
                return $match['value'];
            }
        }
        return null;
    }

    public function information() {
        if (empty($_REQUEST['plugin']) || $_REQUEST['plugin'] !== 'phastpress') {
            return;
        } ?>
        <!doctype html>
        <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: sans-serif;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        </style>
        <p>Read more about PhastPress on <a href="https://github.com/apeschar/phastpress#readme" target="_blank" rel="noopener">GitHub</a>.</p>
        <?php

        exit;
    }
}

class UpdaterException extends RuntimeException {
}
