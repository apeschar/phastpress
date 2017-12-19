<div class="phastpress-settings">

    <div class="phastpress-settings-header">
        <div class="phastpress-settings-form-title">
            <? _e('Retriever map', 'phastpress');?>
        </div>
    </div>

    <div class="phastpress-settings-description">
        <p>
            Description for retriever map here
        </p>
    </div>

    <div class="phastpress-settings-form">
        <div class="phastpress-row">
            <label>
                <?php _e('Host', 'phastpress');?>
                <input type="text" value="phastpress.test" class="regular-text">
            </label>
            <label>
                <?php _e('Direcotry', 'phastpress');?>
                <input type="text" value="/var/www/phastpress" class="regular-text">
            </label>
            <button class="button"><?php _e('Remove', 'phastpress');?></button>
        </div>
        <div class="phastpress-row">
            <label>
                <?php _e('Host', 'phastpress');?>
                <input type="text" value="phastpress.test" class="regular-text">
            </label>
            <label>
                <?php _e('Direcotry', 'phastpress');?>
                <input type="text" value="/var/www/phastpress" class="regular-text">
            </label>
            <button class="button"><?php _e('Remove', 'phastpress');?></button>
        </div>
        <div class="tablenav">
            <div class="alignright actions">
                <button class="button"><?php _e('Add', 'phastpress');?></button>
            </div>
        </div>
    </div>

</div>

<div class="phastpress-settings">

    <div class="phastpress-settings-header">
        <div class="phastpress-settings-form-title">
            <?php _e('Cache', 'phastpress');?>
        </div>
    </div>

    <div class="phastpress-settings-description">
        <p>Description for cache here</p>
    </div>

    <div class="phastpress-settings-form form-wrap">

        <div class="form-field">
            <label for="cache-root"><?php _e('Cache root', 'phastpress');?></label>
            <input type="text" value="/tmp/phast-cache" id="cache-root">
            <p>Explanations for cache root</p>
        </div>

        <fieldset class="phastpress-settings-group">
            <legend><?php _e('Garbage collection', 'phastpress');?></legend>
            <div>
                Explanations about garbage collection
            </div>
            <div class="form-field">
                <label for="max-items"><?php _e('Maximum collected items', 'phastpress');?></label>
                <input type="number" min="0" value="100" id="max-items">
                <p>Explanations about max items</p>
            </div>
            <div class="form-field">
                <label for="probability"><?php _e('Probability', 'phastpress');?></label>
                <input type="number" min="0" max="1" step="0.05" value="0.1" id="probability">
                <p>Explanations for probability</p>
            </div>
            <div class="form-field">
                <label for="max-age"><?php _e('Maximum age', 'phastpress');?></label>
                <input type="number" min="0" step="3600" value="31536" id="max-age">
                <p>Explanations for max. age</p>
            </div>
        </fieldset>

    </div>
</div>

<div class="phastpress-settings">

    <div class="phastpress-settings-header">
        <div class="phastpress-settings-form-title">
            <?php _e('HTML Filters', 'phastpress');?>
        </div>
    </div>

    <div class="phastpress-settings-description">
        <p>Description for html filters here</p>
    </div>

    <div class="phastpress-settings-form form-wrap">

        <div class="form-field">
            <label for="max-doc-size"><?php _e('Maximum document size to apply', 'phastpress');?></label>
            <input type="number" value="1073741824" step="1048576" id="max-doc-size">
            <p>Explanations for max. doc. size</p>
        </div>

    </div>
</div>

<div class="phastpress-settings">

    <div class="phastpress-settings-header">
        <div class="phastpress-settings-form-title">
            <?php _e('Image Filters', 'phastpress');?>
        </div>
    </div>

    <div class="phastpress-settings-description">
        <p>Description for image filters here</p>
    </div>

    <div class="phastpress-settings-form form-wrap">

        <label>
            <?php _e('Host pattern', 'phastpress');?>
            <input type="text" value="~^https?://phastpress\.test/~" class="regular-text">
            <button class="button"><?php _e('Remove', 'phastpress');?></button>
        </label>

        <label>
            <?php _e('Host pattern', 'phastpress');?>
            <input type="text" value="~^https?://ajax\.googleapis\.com/ajax/libs/jqueryui/~" class="regular-text">
            <button class="button"><?php _e('Remove', 'phastpress');?></button>
        </label>

        <div class="tablenav">
            <div class="alignright actions">
                <button class="button"><?php _e('Add', 'phastpress');?></button>
            </div>
        </div>

    </div>
</div>

<div class="phastpress-settings">

    <div class="phastpress-settings-header">
        <div class="phastpress-settings-form-title">
            <?php _e('Logging', 'phastpress');?>
        </div>
    </div>

    <div class="phastpress-settings-description">
        <p>Description for logging here</p>
    </div>

    <fieldset class="phastpress-settings-group">
        <legend><?php _e('PHP Logger', 'phastpress');?></legend>
        <div>
            Explanations about PHP logger
        </div>
        <div class="phastpress-row">
            <label>
                <input type="checkbox" checked>
                <?php _e('Enabled', 'phastpress');?>
            </label>
        </div>
        <fieldset class="phastpress-settings-group">
            <legend><?php _e('Log levels', 'phastpress');?></legend>
            <label>
                <input type="checkbox" value="1">
                <?php _e('Log', 'phastpress');?>
            </label>
            <label>
                <input type="checkbox" value="2">
                <?php _e('Info', 'phastpress');?>
            </label>
            <label>
                <input type="checkbox" value="4">
                <?php _e('Warn', 'phastpress');?>
            </label>
        </fieldset>
    </fieldset>

    <fieldset class="phastpress-settings-group">
        <legend><?php _e('Diagnostics Logger', 'phastpress');?></legend>
        <div>
            Explanations about Diagnostics logger
        </div>
        <div class="phastpress-row">
            <label>
                <input type="checkbox" checked disabled>
                <?php _e('Enabled', 'phastpress');?>
                <small><?php _e('(The logger\'s enabled state is tied to a switch)', 'phastpress');?></small>
            </label>
        </div>
        <fieldset class="phastpress-settings-group">
            <legend><?php _e('Log levels', 'phastpress');?></legend>
            <label>
                <input type="checkbox" value="1">
                <?php _e('Log', 'phastpress');?>
            </label>
            <label>
                <input type="checkbox" value="2">
                <?php _e('Info', 'phastpress');?>
            </label>
            <label>
                <input type="checkbox" value="4">
                <?php _e('Warn', 'phastpress');?>
            </label>
        </fieldset>
        <div class="form-field">
            <label for="log-root"><?php _e('Log root', 'phastpress');?></label>
            <input type="text" id="log-root" value="/tmp/phast-log-root">
        </div>
    </fieldset>

</div>
