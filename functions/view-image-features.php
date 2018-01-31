<?php

return [
    'Resizer'     => [
        'name' => __('Resize and compress', 'phastpress'),
    ],
    'WEBPEncoder' => [
        'name' => sprintf(
            __('Convert to <a href="%s" target="_blank">WebP</a>', 'phastpress'),
            'https://developers.google.com/speed/webp/'
        )
    ],
    'PNGQuantCompression' => [
        'name' => sprintf(
            __('Optimize PNG using <a href="%s" target="_blank">pngquant</a>', 'phastpress'),
            'https://pngquant.org/'
        )
    ],
    'JPEGTransEnhancer'   => [
        'name' => sprintf(
            __('Optimize JPEG using <a href="%s" target="_blank">jpegtran</a>', 'phastpress'),
            'https://en.wikipedia.org/wiki/Libjpeg#jpegtran'
        )
    ]
];
