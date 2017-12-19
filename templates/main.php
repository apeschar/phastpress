<div class="wrap">
    <h1><?php _e('PhastPress', 'phastpress');?></h1>
    <hr class="wp-header-end">

    <h2 class="nav-tab-wrapper wp-clearfix">
        <a href="javasctipt:;" class="nav-tab">
            <?php _e('Diagnostics', 'phastpress');?>
        </a>
        <a href="javascript:;" class="nav-tab nav-tab-active">
            <?php _e('HTML Filters', 'phastpress');?>
        </a>
        <a href="javascript:;" class="nav-tab">
            <?php _e('Image Filters', 'phastpress');?>
        </a>
        <a href="javascript:;" class="nav-tab">
            <?php _e('Advanced', 'phastpress');?>
        </a>
    </h2>

    <div id="phastpress-diagnostics-tab" style="display: none">
        <?php require __DIR__ . '/diagnostics.php';?>
    </div>

    <div id="phastpress-html-filters-tab">
        <?php require __DIR__ . '/filters.php';?>
    </div>

    <div class="phastpress-settings-controls tablenav">
        <div class="alignright actions">
            <button class="button"><?php _e('Use Defaults', 'phastpress');?></button>
            <button class="button" disabled><?php _e('Revert changes', 'phastpress');?></button>
            <button class="button-primary" disabled><?php _e('Save', 'phastpress');?></button>
        </div>
    </div>

</div>
