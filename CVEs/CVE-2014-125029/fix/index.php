<?php
use Quartet\Silex\Provider\PaginationServiceProvider;
use Silex\Application;
use Silex\Provider\DoctrineServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Symfony\Component\HttpFoundation\Request;

require_once __DIR__ . '/../vendor/autoload.php';

$app = new Application();
$app->register(new TwigServiceProvider());
$app['twig.path'] = array(__DIR__);
$app->register(new PaginationServiceProvider());

// just for demo.
$app['knp_paginator.path'] = __DIR__ . '/../vendor/knplabs/knp-paginator-bundle';

// sample configuration.
$app['knp_paginator.options'] = array(
    'template' => array(
        'pagination' => '@quartet_silex_pagination/pagination-bootstrap3.html.twig',
    ),
    'page_range' => 6,
);

// sample data.
$app->register(new DoctrineServiceProvider(), [
    'db.options' => [
        'driver' => 'pdo_sqlite',
        'path' => __DIR__ . '/sample.db',
    ],
]);

$app->get('/', function (Request $request) use ($app) {

    $page = $request->get('page', 1);
    $limit = $request->get('limit', 10);
    $sort = $app['db']->quoteIdentifier($request->get('sort', 'id'));
    $direction = $request->get('direction') === 'desc' ? 'DESC' : 'ASC';

    $sql = "select * from sample order by {$sort} {$direction}";
    $array = $app['db']->fetchAll($sql);

    $pagination = $app['knp_paginator']->paginate($array, $page, $limit);

    return $app['twig']->render('index.html.twig', array(
        'pagination' => $pagination,
    ));
})
;

$app->get('/raw', function () use ($app) {
    $code = file_get_contents(__DIR__ . '/index.html.twig');
    return '<pre>' . htmlspecialchars($code) . '</pre>';
})
;

$app->run();
