<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (TestCase.php)
 */

namespace Xibo\Tests;

use Monolog\Handler\PHPConsoleHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Slim\Environment;
use Slim\Helper\Set;
use Slim\Log;
use Slim\Slim;
use There4\Slim\Test\WebTestCase;
use Xibo\Helper\AccessibleMonologWriter;
use Xibo\Helper\Config;
use Xibo\Helper\Sanitize;
use Xibo\Middleware\ApiView;
use Xibo\Middleware\Storage;

class LocalWebTestCase extends WebTestCase
{
    /**
     * @var Set
     */
    protected $container;

    public function getApp()
    {
        return $this->app;
    }

    /**
     * Get non-app container
     * @return Set
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Gets the Slim instance configured
     * @return Slim
     * @throws \Xibo\Exception\NotFoundException
     */
    public function getSlimInstance()
    {
        // Mock and Environment for use before the test is called
        Environment::mock([
            'REQUEST_METHOD' => 'GET',
            'PATH_INFO'      => '/',
            'SERVER_NAME'    => 'local.dev'
        ]);

        // Create a logger
        $logger = new AccessibleMonologWriter(array(
            'name' => 'PHPUNIT',
            'handlers' => array(
                new StreamHandler('test.log')
            ),
            'processors' => array(
                new \Xibo\Helper\LogProcessor(),
                new \Monolog\Processor\UidProcessor(7)
            )
        ), false);

        $app = new \RKA\Slim(array(
            'mode' => 'phpunit',
            'debug' => false,
            'log.writer' => $logger
        ));
        $app->setName('default');
        $app->setName('test');

        // Config
        Config::Load($app->container, PROJECT_ROOT . '/web/settings.php');

        $app->add(new TestAuthMiddleware());
        $app->add(new \Xibo\Middleware\State());
        $app->add(new \Xibo\Middleware\Storage());

        $app->view(new ApiView());

        // Configure the Slim error handler
        $app->error(function (\Exception $e) use ($app) {
            $app->getLog()->emergency($e->getMessage());
            throw $e;
        });

        // All routes
        require PROJECT_ROOT . '/lib/routes.php';
        require PROJECT_ROOT . '/lib/routes-web.php';

        // Create a container for non-app calls to Factories
        $this->container = new Set();
        Storage::setStorage($this->container);

        // Register the sanitizer
        $this->container->singleton('sanitizerService', function($container) {
            return new Sanitize($container);
        });

        // Create a logger for this container
        $this->container->singleton('log', function ($c) use ($logger) {
            $log = new \Slim\Log($logger);
            $log->setEnabled(true);
            $log->setLevel(Log::DEBUG);
            $env = $c['environment'];
            $env['slim.log'] = $log;

            return $log;
        });

        return $app;
    }
}