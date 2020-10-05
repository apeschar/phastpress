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
            Like WordPress, PhastPress requires at least PHP version 5.6.20.
            Get in touch with your hosting provider for an upgrade.
        </p>
    </div>
    <?php
}
