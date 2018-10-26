<?php

namespace Parallax\LaravelJsViews;

use Illuminate\Support\Facades\View;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Tightenco\Ziggy\RoutePayload;

class JsCreator
{
    private $request;
    private $view;
    private $viewPath;
    private $viewDir;
    private $viewName;
    private $viewProps;
    private $viewFactory;

    /**
     * Create a new JS composer.
     *
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->viewDir = resource_path('views');
    }

    /**
     *
     * @param  View  $view
     * @return void
     */
    public function create(\Illuminate\View\View $view)
    {
        $this->view = $view;
        $this->viewPath = $view->getPath();

        if (! $this->shouldHandle()) return;

        $this->viewName = str_replace($this->viewDir . '/', '', $this->viewPath);
        $this->viewName = preg_replace('/\.(js|vue)$/', '', $this->viewName);

        $this->viewFactory = $this->view->getFactory();
        $this->viewProps = Arr::except(
            array_merge(
                (array) $this->viewFactory->getShared(),
                $this->view->getData()
            ),
            config('js-views.exclude_props', ['__env', 'app'])
        );

        if ($this->request->ajax()) {
            $this->createJsonResponse();
            return;
        }

        $sections = [];
        $globals = [
            'Laravel' => [
                '___page' => $this->viewName,
                '___props' => $this->viewProps
            ]
        ];

        // start Ziggy
        $url = url('/');
        $parsedUrl = parse_url($url);

        $globals['Ziggy'] = [
            'namedRoutes' => RoutePayload::compile(app()->router),
            'baseUrl' => $url . '/',
            'baseProtocol' => array_key_exists('scheme', $parsedUrl) ? $parsedUrl['scheme'] : 'http',
            'baseDomain' => array_key_exists('host', $parsedUrl) ? $parsedUrl['host'] : '',
            'basePort' => array_key_exists('port', $parsedUrl) ? $parsedUrl['port'] : false,
            'defaultParameters' => method_exists(app('url'), 'getDefaultParameters') ? app('url')->getDefaultParameters() : []
        ];
        // end Ziggy

        $scripts = <<<HTML
            <laravel-js-views-scripts>
                <script>{$this->stringifyGlobals($globals)}</script>
            </laravel-js-views-scripts>
HTML;

        try {
            $process = [
                'env' => [
                    'VUE_ENV' => 'server',
                    'NODE_ENV' => 'production'
                ]
            ];
            $renderer = new Renderer(
                file_get_contents(public_path('js/server/main.js')),
                [
                    'process' => $process,
                    'this.global' => array_merge($globals, ['process' => $process])
                ]
            );

            $sections = json_decode($renderer->render(), true);
        } catch (\Throwable $e) {
            $sections['html'] = config('js-views.fallback', '<div id="app"></div>');
        }

        // TODO: check there’s a `html` section defined
        $sections['html'] .= $scripts;

        $layoutManifest = json_decode(file_get_contents(public_path('layout-manifest.json')), true);
        $this->view->setPath(View::getFinder()->find($layoutManifest[$this->viewName]));

        foreach ($sections as $section => $value) {
            $this->viewFactory->startSection($section);
            echo $value;
            $this->viewFactory->stopSection();
        }
    }

    private function shouldHandle()
    {
        $ext = pathinfo($this->viewPath, PATHINFO_EXTENSION);
        return $ext === 'js' || $ext === 'vue';
    }

    private function createJsonResponse()
    {
        $this->view->setPath(__DIR__ . '/templates/json.blade.php');
        $this->view->with(
            'data',
            json_encode([
                'view' => $this->viewName,
                'props' => $this->viewProps
            ])
        );
    }

    private function stringifyGlobals($globals, $var = 'window')
    {
        return implode('', array_map(function ($k, $v) use ($var) {
            return $var . '["' . $k . '"]=' . json_encode($v) . ';';
        }, array_keys($globals), $globals));
    }
}
