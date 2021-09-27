<?php

if (!defined('PHASTPRESS_VERSION')) {
    exit;
}

add_action('admin_notices', 'phastpress_php_version_notice');

function phastpress_php_version_notice() {
    ?>
    <div class="error notice">
        <p>
            <b>Sorry, PhastPress won't work here.</b>
            PhastPress requires at least PHP version 7.3.
            Get in touch with your hosting provider for an upgrade to the latest PHP version.
        </p>
    </div>
    <?php
}
