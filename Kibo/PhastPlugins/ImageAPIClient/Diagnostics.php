<?php


namespace Kibo\PhastPlugins\ImageAPIClient;

use Kibo\Phast\Diagnostics\Diagnostics as DiagnosticsInterface;
use Kibo\Phast\Environment\Package;
use Kibo\Phast\Filters\Image\ImageFilter;
use Kibo\Phast\Filters\Image\ImageImplementations\DummyImage;

class Diagnostics implements  DiagnosticsInterface {

    public function diagnose(array $config) {
        $package = Package::fromPackageClass(get_class($this));
        /** @var ImageFilter $filter */
        $filter = $package->getFactory()->make($config);
        $url = plugin_dir_url(__DIR__ . '/../../../phastpress.php') . '/Kibo/PhastPlugins/ImageAPIClient/kibo-logo.png';
        $filter->transformImage(new DummyImage(), ['src' => $url]);
    }


}
