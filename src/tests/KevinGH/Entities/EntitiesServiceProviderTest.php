<?php

namespace KevinGH\Entities;

use Doctrine\ORM\ORMException;
use Memcache;
use Memcached;
use Mock\EntityManager;
use Silex\Application;
use Silex\Provider\DoctrineServiceProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class EntitiesServiceProviderTest extends InternalTestCase
{
    private $app;
    private $provider;

    protected function setUp()
    {
        $this->app = new Application;
        $this->app['dbs.options'] = array(
            'one' => array(
                'driver' => 'pdo_sqlite',
                'dbname' => 'test1',
                'memory' => true
            ),
            'two' => array(
                'driver' => 'pdo_sqlite',
                'dbname' => 'test1',
                'memory' => true
            ),
            'three' => array(
                'driver' => 'pdo_sqlite',
                'dbname' => 'test1',
                'memory' => true
            )
        );

        $this->provider = new EntitiesServiceProvider();
    }

    public function testBoot()
    {
        $this->assertNull($this->provider->boot($this->app));
    }

    public function testCache()
    {
        $this->provider->register($this->app);

        $options = array(
            'array' => array(
                'caching_driver' => 'ArrayCache',
                'proxy_dir' => '',
                'proxy_namespace' => '',
                'mapping_paths' => ''
            )
        );

        if (extension_loaded('apc')) {
            $options['apc'] = array(
                'caching_driver' => 'ApcCache',
                'proxy_dir' => '',
                'proxy_namespace' => '',
                'mapping_paths' => ''
            );
        }

        if (extension_loaded('memcache')) {
            $this->app['memcache'] = new Memcache();
            $options['memcache'] = array(
                'caching_driver' => 'MemcacheCache',
                'proxy_dir' => '',
                'proxy_namespace' => '',
                'mapping_paths' => ''
            );
        }

        if (extension_loaded('memcached')) {
            $this->app['memcached'] = new Memcached();
            $options['memcached'] = array(
                'caching_driver' => 'MemcachedCache',
                'proxy_dir' => '',
                'proxy_namespace' => '',
                'mapping_paths' => ''
            );
        }

        $this->app['ems.options'] = $options;

        $cache = $this->provider->createCache($this->app);

        $this->assertInstanceOf('Pimple', $cache);
        $this->assertInstanceOf(
            'Doctrine\Common\Cache\ArrayCache',
            $cache['array']
        );

        if (extension_loaded('apc')) {
            $this->assertInstanceOf(
                'Doctrine\Common\Cache\ApcCache',
                $cache['apc']
            );
        }

        if (extension_loaded('memcache')) {
            $this->assertInstanceOf(
                'Doctrine\Common\Cache\MemcacheCache',
                $cache['memcache']
            );

            $this->assertSame(
                $this->app['memcache'],
                $cache['memcache']->getMemcache()
            );
        }

        if (extension_loaded('memcached')) {
            $this->assertInstanceOf(
                'Doctrine\Common\Cache\MemcachedCache',
                $cache['memcached']
            );

            $this->assertSame(
                $this->app['memcached'],
                $cache['memcached']->getMemcached()
            );
        }
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage No Memcache client available as a service.
     */
    public function testMissingMemcache()
    {
        $this->provider->register($this->app);

        $this->app['ems.options'] = array(
            'default' => array(
                'caching_driver' => 'MemcacheCache',
                'proxy_dir' => '',
                'proxy_namespace' => '',
                'mapping_paths' => ''
            )
        );

        $cache = $this->provider->createCache($this->app);

        $cache['default'];
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage No Memcached client available as a service.
     */
    public function testMissingMemcached()
    {
        $this->provider->register($this->app);

        $this->app['ems.options'] = array(
            'default' => array(
                'caching_driver' => 'MemcachedCache',
                'proxy_dir' => '',
                'proxy_namespace' => '',
                'mapping_paths' => ''
            )
        );

        $cache = $this->provider->createCache($this->app);

        $cache['default'];
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Unsupported cache class: Doctrine\Common\Cache\BadCache
     */
    public function testInvalidCacheDriver()
    {
        $this->provider->register($this->app);

        $this->app['ems.options'] = array(
            'default' => array(
                'caching_driver' => 'BadCache',
                'proxy_dir' => '',
                'proxy_namespace' => '',
                'mapping_paths' => ''
            )
        );

        $cache = $this->provider->createCache($this->app);

        $cache['default'];
    }

    public function testMapping()
    {
        $this->provider->register($this->app);

        $this->app['ems.options'] = array(
            'annotation' => array(
                'proxy_dir' => '',
                'proxy_namespace' => '',
                'mapping_driver' => 'AnnotationDriver',
                'mapping_paths' => '/path/annotation'
            ),
            'xml' => array(
                'proxy_dir' => '',
                'proxy_namespace' => '',
                'mapping_driver' => 'XmlDriver',
                'mapping_paths' => '/path/xml'
            ),
            'yaml' => array(
                'proxy_dir' => '',
                'proxy_namespace' => '',
                'mapping_driver' => 'YamlDriver',
                'mapping_paths' => '/path/yaml'
            )
        );

        $mapping = $this->app['ems.mapping'];

        $this->assertInstanceOf('Doctrine\ORM\Mapping\Driver\AnnotationDriver', $mapping['annotation']);
        $this->assertInstanceOf('Doctrine\ORM\Mapping\Driver\XmlDriver', $mapping['xml']);
        $this->assertInstanceOf('Doctrine\ORM\Mapping\Driver\YamlDriver', $mapping['yaml']);
        $this->assertEquals(array('/path/annotation'), $mapping['annotation']->getPaths());
        $this->assertEquals(array('/path/xml'), $mapping['xml']->getLocator()->getPaths());
        $this->assertEquals(array('/path/yaml'), $mapping['yaml']->getLocator()->getPaths());
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Unsupported mapping class: Doctrine\ORM\Mapping\Driver\BadDriver
     */
    public function testBadMappingClass()
    {
        $this->provider->register($this->app);

        $this->app['em.options'] = array(
            'proxy_dir' => '',
            'proxy_namespace' => '',
            'mapping_driver' => 'BadDriver'
        );

        $mapping = $this->app['ems.mapping'];

        $mapping['default'];
    }

    /**
     * @depends testCache
     * @depends testMapping
     */
    public function testConfig()
    {
        $this->app['ems.options'] = array(
            'one' => array(
                'caching_driver' => 'ArrayCache',
                'proxy_auto_generate' => true,
                'proxy_dir' => '',
                'proxy_namespace' => '',
                'mapping_driver' => 'AnnotationDriver',
                'mapping_paths' => ''
            ),
            'two' => array(
                'caching_driver' => 'ArrayCache',
                'proxy_auto_generate' => false,
                'proxy_dir' => '',
                'proxy_namespace' => '',
                'mapping_driver' => 'AnnotationDriver',
                'mapping_paths' => ''
            ),
            'three' => array(
                'caching_driver' => 'ArrayCache',
                'proxy_auto_generate' => true,
                'proxy_dir' => '',
                'proxy_namespace' => '',
                'mapping_driver' => 'AnnotationDriver',
                'mapping_paths' => ''
            )
        );

        $this->app['ems.cache'] = $this->provider->createCache($this->app);
        $this->app['ems.mapping'] = $this->provider->createMapping($this->app);

        $config = $this->provider->createConfig($this->app);

        $this->assertInstanceOf('Pimple', $config);
        $this->assertInstanceOf('Doctrine\ORM\Configuration', $config['one']);
        $this->assertInstanceOf('Doctrine\ORM\Configuration', $config['two']);
        $this->assertInstanceOf('Doctrine\ORM\Configuration', $config['three']);
        $this->assertTrue($config['one']->getAutoGenerateProxyClasses());
        $this->assertFalse($config['two']->getAutoGenerateProxyClasses());
        $this->assertTrue($config['three']->getAutoGenerateProxyClasses());
    }

    /**
     * @depends testConfig
     */
    public function testEntityManager()
    {
        $this->app['ems.options'] = array(
            'one' => array(
                'caching_driver' => 'ArrayCache',
                'flush_on_terminate' => false,
                'proxy_auto_generate' => true,
                'proxy_dir' => sys_get_temp_dir(),
                'proxy_namespace' => 'test',
                'mapping_driver' => 'AnnotationDriver',
                'mapping_paths' => ''
            ),
            'two' => array(
                'caching_driver' => 'ArrayCache',
                'db' => 'one',
                'flush_on_terminate' => true,
                'proxy_auto_generate' => false,
                'proxy_dir' => sys_get_temp_dir(),
                'proxy_namespace' => 'test',
                'mapping_driver' => 'AnnotationDriver',
                'mapping_paths' => ''
            ),
            'three' => array(
                'caching_driver' => 'ArrayCache',
                'flush_on_terminate' => false,
                'proxy_auto_generate' => true,
                'proxy_dir' => sys_get_temp_dir(),
                'proxy_namespace' => 'test',
                'mapping_driver' => 'AnnotationDriver',
                'mapping_paths' => ''
            )
        );

        $this->app['ems.cache'] = $this->provider->createCache($this->app);
        $this->app['ems.mapping'] = $this->provider->createMapping($this->app);
        $this->app['ems.config'] = $this->provider->createConfig($this->app);

        $this->app->register(new DoctrineServiceProvider);

        $ems = $this->provider->createEntityManager($this->app);

        $this->assertInstanceOf('Pimple', $ems);
        $this->assertInstanceOf('Doctrine\ORM\EntityManager', $ems['one']);
        $this->assertInstanceOf('Doctrine\ORM\EntityManager', $ems['two']);
        $this->assertInstanceOf('Doctrine\ORM\EntityManager', $ems['three']);
        $this->assertSame($this->app['dbs']['one'], $ems['one']->getConnection());
        $this->assertSame($this->app['dbs']['one'], $ems['two']->getConnection());
        $this->assertSame($this->app['dbs']['three'], $ems['three']->getConnection());

        $ems['two']->close();

        try {
            $this->app->terminate(Request::create('/'), new Response);
        } catch (ORMException $e) {
        }

        $this->assertTrue(isset($e));
    }

    public function testRegister()
    {
        $this->provider->register($this->app);

        $this->assertSame(array(), $this->app['ems.options']);

        $this->app['em.options'] = array(
            'proxy_dir' => sys_get_temp_dir(),
            'proxy_namespace' => 'test',
            'mapping_paths' => sys_get_temp_dir() . '/entities'
        );

        $this->assertEquals('default', $this->app['ems.default']);
        $this->assertSame(
            array(
                'flush_on_terminate' => false,
                'caching_driver' => (! function_exists('apc_store')
                    || $this->app['debug'])
                ? 'ArrayCache'
                : 'ApcCache',
                'mapping_driver' => 'AnnotationDriver',
                'proxy_auto_generate' => $this->app['debug']
            ),
            $this->app['ems.default_options']
        );

        $this->assertInstanceOf('Doctrine\ORM\EntityManager', $this->app['em']);
        $this->assertInstanceOf('Pimple', $this->app['ems']);
        $this->assertInstanceOf('Pimple', $this->app['ems.cache']);
        $this->assertInstanceOf('Pimple', $this->app['ems.config']);
        $this->assertInstanceOf('Pimple', $this->app['ems.mapping']);
        $this->assertSame($this->app['em'], $this->app['ems']['default']);
    }
}
