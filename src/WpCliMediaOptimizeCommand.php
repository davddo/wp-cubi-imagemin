<?php

namespace Globalis\WP\Cubi\ImageMin;

class WpCliMediaOptimizeCommand extends \WP_CLI_Command
{

    /**
     * Optimize image in one or more directories.
     *
     * ## OPTIONS
     *
     * <directories>...
     * : One or more media directories to optimize.
     *
     * [--jpeg_level=<jpeg_level>]
     * : Level of JPEG compression.
     *
     */
    public function __invoke($args, $assoc_args = [])
    {

        $assoc_args = wp_parse_args($assoc_args, ['jpeg_level' => ImageMin::DEFAULT_JPEG_LEVEL]);

        $jpeg_level = $assoc_args['jpeg_level'];
        if (!is_numeric($jpeg_level) || intval($jpeg_level) <= 0 || intval($jpeg_level) > 100) {
            \WP_CLI::error(sprintf('Invalid JPEG compression level "%s". Value must be an integer between 1 and 100.', $jpeg_level));
        } else {
            $jpeg_level = intval($jpeg_level);
        }

        $files = [];

        foreach ($args as $directory) {
            if (!is_dir($directory)) {
                \WP_CLI::warning(sprintf('"%s" is not a valid directory, it will be skipped.', $directory));
            } else {
                $files = array_merge($files, self::listImagesRecursively($directory));
            }
        }

        $files = array_unique($files);
        $count = count($files);

        if ($count < 1) {
            \WP_CLI::warning('No image found. Nothing was done.');
            return;
        }

        \WP_CLI::log(sprintf('Found %1$d %2$s to optimize.', $count, _n('image', 'images', $count)));
        \WP_CLI::confirm('Do you want to run ?');

        $optimizer = ImageMin::getOptimizer($jpeg_level);

        foreach ($files as $index => $path) {
            \WP_CLI::log(sprintf("%s Optimize image: %s", sprintf("[%s/%s]", self::formatProgress($index + 1, $count), self::formatProgress($count, $count)), $path));
            $optimizer->optimize($path);
        }

        \WP_CLI::success(sprintf('Optimized %s %s.', $count, _n('image', 'images', $count)));
    }

    protected static function listImagesRecursively($root_path)
    {
        if (is_dir($root_path)) {
            $files = [];
            foreach (scandir($root_path) as $path) {
                if (!in_array($path, ['.', '..'])) {
                    $path  = untrailingslashit($root_path) . DIRECTORY_SEPARATOR . $path;
                    $files = array_merge($files, self::listImagesRecursively($path));
                }
            }
            return $files;
        } elseif (is_file($root_path) && ImageMin::isImagePath($root_path)) {
            return [$root_path];
        } else {
            return [];
        }
    }

    protected static function formatProgress($index, $total)
    {
        static $digits;
        if (!isset($digits)) {
            $digits = strlen((string) $total);
        }
        return str_pad($index, $digits, '0', STR_PAD_LEFT);
    }
}
