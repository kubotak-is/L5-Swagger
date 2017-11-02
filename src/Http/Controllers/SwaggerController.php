<?php

namespace L5Swagger\Http\Controllers;

use L5Swagger\Generator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Routing\Controller as BaseController;

class SwaggerController extends BaseController
{
    /** @var Filesystem  */
    private $file;
    
    /** @var Request  */
    private $request;
    
    /** @var ResponseFactory  */
    private $response;
    
    /**
     * SwaggerController constructor.
     * @param Filesystem      $filesystem
     * @param Request         $request
     * @param ResponseFactory $responseFactory
     */
    public function __construct(Filesystem $filesystem, Request $request, ResponseFactory $responseFactory)
    {
        $this->file     = $filesystem;
        $this->request  = $request;
        $this->response = $responseFactory;
    }
    
    /**
     * Dump api-docs.json content endpoint.
     *
     * @param string $jsonFile
     *
     * @return Response
     */
    public function docs($jsonFile = null)
    {
        $filePath = config('l5-swagger.paths.docs').'/'.
            (! is_null($jsonFile) ? $jsonFile : config('l5-swagger.paths.docs_json', 'api-docs.json'));

        if (! $this->file->exists($filePath)) {
            abort(404, 'Cannot find '.$filePath);
        }

        $content = $this->file->get($filePath);

        return $this->response->make($content, 200, [
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Display Swagger API page.
     *
     * @return Response
     */
    public function api()
    {
        if (config('l5-swagger.generate_always')) {
            Generator::generateDocs();
        }

        if (config('l5-swagger.proxy')) {
            $proxy = $this->request->server('REMOTE_ADDR');
            $this->request->setTrustedProxies([$proxy]);
        }

        // Need the / at the end to avoid CORS errors on Homestead systems.
        $response = $this->response->make(
            view('l5-swagger::index', [
                'secure'             => $this->request->secure(),
                'urlToDocs'          => route('l5-swagger.docs', config('l5-swagger.paths.docs_json', 'api-docs.json')),
                'operationsSorter'   => config('l5-swagger.operations_sort'),
                'configUrl'          => config('l5-swagger.additional_config_url'),
                'validatorUrl'       => config('l5-swagger.validator_url'),
            ]),
            200
        );

        return $response;
    }

    /**
     * Display Oauth2 callback pages.
     *
     * @return string
     */
    public function oauth2Callback()
    {
        return $this->file->get(swagger_ui_dist_path('oauth2-redirect.html'));
    }
}
