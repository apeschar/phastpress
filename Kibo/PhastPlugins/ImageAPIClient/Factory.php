<?php


namespace Kibo\PhastPlugins\ImageAPIClient;


use Kibo\Phast\Filters\Image\ImageFilterFactory;

class Factory implements ImageFilterFactory {

    public function make(array $config) {
        return new Filter($config['images']['filters'][Filter::class]);
    }


}
