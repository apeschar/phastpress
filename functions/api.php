<?php

function phastpress_optimize_snippet($html) {
    return phastpress_get_plugin_sdk()->getPhastAPI()->applyFiltersForSnippets((string) $html);
}
