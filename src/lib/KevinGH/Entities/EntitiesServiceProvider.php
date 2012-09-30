<?php

/* This file is part of Entities.
 *
 * (c) 2012 Kevin Herrera
 *
 * For the full copyright and license information, please
 * view the LICENSE file that was distributed with this
 * source code.
 */

namespace KevinGH\Entities;

use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use InvalidArgumentException;
use Pimple;
use RuntimeException;
use Silex\Application;
use Silex\Provider\DoctrineServiceProvider;
use Silex\ServiceProviderInterface;
use Silex\SilexEvents;

/**
 * Doctrine ORM provider.
 *
 * @author Kevin Herrera <kherrera@ebscohost.com>
 */
class EntitiesServiceProvider implements ServiceProviderInterface
{
    /** {@inheritDoc} */
    public function boot(Application $app)
    {
    }

    /**
     * Creates the cache instances for the entity managers.
     *
     * @param Application $app The application.
     *
     * @return Pimple The cache instance manager.
     */
    public function createCache(Application $app)
    {
        $cache = new Pimple;

        foreach ($app['ems.options'] as $name => $options) {
            $cache[$name] = $cache->share(
                function () use ($cache, $options) {
                    $class = 'Doctrine\Common\Cache\\' . $options['caching_driver'];

                    switch ($options['caching_driver']) {
                        case 'ApcCache':
                        case 'ArrayCache':
                        case 'XcacheCache':
                            return new $class;
                        case 'MemcacheCache':
                            $cache = new $class;

                            if ((false === isset($app['memcache']))
                                    && (false === isset($options['memcache']))) {
                                throw new RuntimeException (
                                    'No Memcache client available as a service.'
                                );
                            }

                            $cache->setMemcache(
                                isset($options['memcache'])
                                    ? $options['memcache']
                                    : $app['memcache']
                            );

                            return $cache;
                        case 'MemcachedCache':
                            $cache = new $class;

                            if ((false === isset($app['memcached']))
                                    && (false === isset($options['memcached']))) {
                                throw new RuntimeException (
                                    'No Memcached client available as a service.'
                                );
                            }

                            $cache->setMemcached(
                                isset($options['memcached'])
                                    ? $options['memcached']
                                    : $app['memcached']
                            );

                            return $cache;
                        default:
                            throw new InvalidArgumentException (
                                "Unsupported cache class: $class"
                            );
                    }
                }
            );
        }

        return $cache;
    }

    /**
     * Creates the configuration instances for the entity managers.
     *
     * @param Application $app The application.
     *
     * @return Pimple The config instance manager.
     */
    public function createConfig(Application $app)
    {
        $config = new Pimple;

        foreach ($app['ems.options'] as $name => $options) {
            $config[$name] = $config->share(
                function () use ($app, $name, $options) {
                    $config = new Configuration;

                    $config->setAutoGenerateProxyClasses(
                        $options['proxy_auto_generate']
                    );

                    $config->setMetadataCacheImpl($app['ems.cache'][$name]);
                    $config->setMetadataDriverImpl($app['ems.mapping'][$name]);
                    $config->setQueryCacheImpl($app['ems.cache'][$name]);
                    $config->setProxyDir($options['proxy_dir']);
                    $config->setProxyNamespace($options['proxy_namespace']);

                    return $config;
                }
            );
        }

        return $config;
    }

    /**
     * Creates the entity manager instances.
     *
     * @param Application $app The application.
     *
     * @return Pimple The entity manager instance manager.
     */
    public function createEntityManager(Application $app)
    {
        $ems = new Pimple;

        foreach ($app['ems.options'] as $name => $options) {
            $ems[$name] = $ems->share(
                function () use ($app, $name, $options) {
                    if (false === isset($options['db'])) {
                        $options['db'] = $name;
                    }

                    if (isset($app['dbs'][$options['db']])) {
                        $em = EntityManager::create(
                            $app['dbs'][$options['db']],
                            $app['ems.config'][$name],
                            $app['dbs.event_manager'][$options['db']]
                        );
                    } else {
                        $em = EntityManager::create(
                            $app['db'],
                            $app['ems.config'][$name],
                            $app['db.event_manager']
                        );
                    }

                    if ($options['flush_on_terminate']) {
                        $app['dispatcher']->addListener(
                            SilexEvents::FINISH,
                            function () use ($em) {
                                $em->flush();
                                // @codeCoverageIgnoreStart
                            }
                            // @codeCoverageIgnoreEnd
                        );
                    }

                    return $em;
                }
            );
        }

        return $ems;
    }

    /**
     * Creates the mapping instances for the entity managers.
     *
     * @param Application $app The application.
     *
     * @return Pimple The mapping instance manager.
     */
    public function createMapping(Application $app)
    {
        $mapping = new Pimple;

        foreach ($app['ems.options'] as $name => $options) {
            $mapping[$name] = $mapping->share(
                function () use ($app, $options) {
                    $class = 'Doctrine\ORM\Mapping\Driver\\'
                           . $options['mapping_driver'];

                    switch ($options['mapping_driver'])
                    {
                        case 'AnnotationDriver':
                            $config = new Configuration;

                            return $config->newDefaultAnnotationDriver(
                                $options['mapping_paths']
                            );
                        case 'XmlDriver':
                            return new $class (
                                $options['mapping_paths']
                            );
                        case 'YamlDriver':
                            $driver = new $class (
                                $options['mapping_paths'],
                                '.yml'
                            );

                            return $driver;
                        default:
                            throw new InvalidArgumentException (
                                "Unsupported mapping class: $class"
                            );
                    }
                }
            );
        }

        return $mapping;
    }

    /** {@inheritDoc} */
    public function register(Application $app)
    {
        if (false === isset($app['dbs'])) {
            $app->register(new DoctrineServiceProvider);
        }

        $app['ems.default'] = 'default';
        $app['ems.options'] = array();

        $app['ems.default_options'] = array(
            'flush_on_terminate' => false,
            'caching_driver' => (! function_exists('apc_store') || $app['debug'])
                                ? 'ArrayCache'
                                : 'ApcCache',
            'mapping_driver' => 'AnnotationDriver',
            'proxy_auto_generate' => $app['debug']
        );

        $provider = $this;

        $app['em'] = $app->share(function () use ($app, $provider) {
            return $app['ems'][$app['ems.default']];
        });

        $app['ems'] = $app->share(function () use ($app, $provider) {
            $app['ems.init_options']();

            return $provider->createEntityManager($app);
        });

        $app['ems.cache'] = $app->share(function () use ($app, $provider) {
            $app['ems.init_options']();

            return $provider->createCache($app);
        });

        $app['ems.config'] = $app->share(function () use ($app, $provider) {
            $app['ems.init_options']();

            return $provider->createConfig($app);
        });

        $app['ems.init_options'] = $app->protect(
            function () use ($app) {
                static $initialized = false;

                if ($initialized) {
                    return;
                }

                $initialized = true;

                if (empty($app['ems.options'])
                    && (false === empty($app['em.options']))) {
                    $app['ems.options'] = array(
                        'default' => $app['em.options']
                    );
                }

                $list = $app['ems.options'];

                foreach ($list as $name => $options) {
                    $list[$name] = array_merge(
                        $app['ems.default_options'],
                        $options
                    );
                }

                $app['ems.options'] = $list;
            }
        );

        $app['ems.mapping'] = $app->share(function () use ($app, $provider) {
            $app['ems.init_options']();

            return $provider->createMapping($app);
        });
    }
}
