<?php

namespace Parallax\LaravelJsViews;

use Illuminate\Support\Arr;
use Illuminate\Filesystem\Filesystem;

class PreactViewsPreset extends ViewsPreset
{
    /**
     * Update the given package array.
     *
     * @param  array  $packages
     * @return array
     */
    protected static function updatePackageArray(array $packages)
    {
        return [
            'babel-preset-preact' => '^1.1.0',
            'preact' => '^8.3.0',
            'preact-render-to-string' => '^3.8.0',
            'babel-plugin-syntax-dynamic-import' => '^6.18.0',
            'clean-webpack-plugin' => '^0.1.19',
        ] + Arr::except($packages, ['vue']);
    }

    /**
     * Update the Webpack configuration.
     *
     * @return void
     */
    protected static function updateWebpackConfiguration()
    {
        copy(__DIR__.'/js/preact/mix-stub.js', base_path('webpack.mix.js'));
    }

    /**
     * Create the example view.
     *
     * @return void
     */
    protected static function updateWelcomeView()
    {
        (new Filesystem)->delete(resource_path('views/welcome.blade.php'));
        copy(__DIR__.'/js/preact/page-stub.js', resource_path('views/welcome.js'));
    }
}
