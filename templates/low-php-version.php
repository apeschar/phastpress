<div class="wrap">

    <h1><?php _e('PhastPress', 'phastpress');?></h1>
    <hr class="wp-header-end">

    <div class="phastpress-settings-problem phastpress-settings-error">
        <?php echo sprintf(
                __(
                    'The version of PHP your server is running is lower than that required by PhastPress!<br>' .
                    'You will not be able to run PhastPress unless you upgrade to PHP 5.6 or higher.<br>' .
                    'Required PHP version: >= 5.6. Your PHP version: %s.',
                    'phastpress'
                ),
                PHP_VERSION
        ); ?>
    </div>

</div>
