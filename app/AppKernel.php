<?php

use AppBundle\Common\ExtensionManager;
use Biz\User\CurrentUser;
use Symfony\Component\HttpKernel\Kernel;
use Topxia\Service\Common\ServiceKernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Config\Loader\LoaderInterface;
use Codeages\Biz\Framework\Provider\MonologServiceProvider;
use Codeages\Biz\Framework\Provider\DoctrineServiceProvider;
use Codeages\PluginBundle\System\PluginConfigurationManager;
use Codeages\PluginBundle\System\PluginableHttpKernelInterface;


class AppKernel extends Kernel implements PluginableHttpKernelInterface
{
    protected $plugins = array();

    /**
     * @var Request
     */
    protected $request;

    protected $extensionManger;

    private $isServiceKernelInit = false;

    protected $pluginConfigurationManager;

    public function __construct($environment, $debug)
    {
        parent::__construct($environment, $debug);
        date_default_timezone_set('Asia/Shanghai');
        $this->extensionManger            = ExtensionManager::init($this);
        $this->pluginConfigurationManager = new PluginConfigurationManager($this->getRootDir());
    }

    public function boot()
    {
        if (true === $this->booted) {
            return;
        }

        if ($this->loadClassCache) {
            $this->doLoadClassCache($this->loadClassCache[0], $this->loadClassCache[1]);
        }

        // init bundles
        $this->initializeBundles();

        // init container
        $this->initializeContainer();

        $this->initializeBiz();
        $this->initializeServiceKernel();

        foreach ($this->getBundles() as $bundle) {
            $bundle->setContainer($this->container);
            $bundle->boot();
        }

        $this->booted = true;
    }

    public function registerBundles()
    {
        $bundles = array(
            new Codeages\PluginBundle\FrameworkBundle(),
            new Symfony\Bundle\SecurityBundle\SecurityBundle(),
            new Symfony\Bundle\TwigBundle\TwigBundle(),
            new Symfony\Bundle\MonologBundle\MonologBundle(),
            new Symfony\Bundle\SwiftmailerBundle\SwiftmailerBundle(),
            new Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle(),
            new Endroid\Bundle\QrCodeBundle\EndroidQrCodeBundle(),
            new Topxia\WebBundle\TopxiaWebBundle(),
            new Topxia\AdminBundle\TopxiaAdminBundle(),
            new Topxia\MobileBundle\TopxiaMobileBundle(),
            new Topxia\MobileBundleV2\TopxiaMobileBundleV2(),
            new Bazinga\Bundle\JsTranslationBundle\BazingaJsTranslationBundle(),
            new OAuth2\ServerBundle\OAuth2ServerBundle(),
            new Codeages\PluginBundle\CodeagesPluginBundle(),
            new AppBundle\AppBundle()
        );

        $bundles = array_merge($bundles, $this->pluginConfigurationManager->getInstalledPluginBundles());

        $bundles[] = new Custom\WebBundle\CustomWebBundle();
        $bundles[] = new Custom\AdminBundle\CustomAdminBundle();

        if (in_array($this->getEnvironment(), array('dev', 'test'))) {
            // $bundles[] = new Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle();
            $bundles[] = new Symfony\Bundle\WebProfilerBundle\WebProfilerBundle();
            $bundles[] = new Sensio\Bundle\DistributionBundle\SensioDistributionBundle();
            $bundles[] = new Sensio\Bundle\GeneratorBundle\SensioGeneratorBundle();
        }

        return $bundles;
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(__DIR__.'/config/config_'.$this->getEnvironment().'.yml');
    }

    public function getPlugins()
    {
        return $this->pluginConfigurationManager->getInstalledPlugins();
    }

    public function getPluginConfigurationManager()
    {
        return $this->pluginConfigurationManager;
    }

    public function setRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }

    protected function initializeBiz()
    {
        $biz                            = $this->getContainer()->get('biz');
        $biz['migration.directories'][] = dirname(__DIR__).'/migrations';
        $biz['env'] = array(
            'base_url' => $this->request->getSchemeAndHttpHost() . $this->request->getBasePath()
        );
        $biz->register(new DoctrineServiceProvider());
        $biz->register(new MonologServiceProvider());
        $biz->register(new \Biz\DefaultServiceProvider());

        $collector = $this->getContainer()->get('biz.service_provider.collector');
        foreach ($collector->all() as $provider) {
            $biz->register($provider);
        }

        $biz->register(new Codeages\Biz\RateLimiter\RateLimiterServiceProvider());
        $biz->boot();
    }

    protected function initializeServiceKernel()
    {
        if (!$this->isServiceKernelInit) {
            $container = $this->getContainer();
            $biz       = $container->get('biz');

            $serviceKernel = ServiceKernel::create($this->getEnvironment(), $this->isDebug());

            $currentUser = new CurrentUser();
            $currentUser->fromArray(array(
                'id'        => 0,
                'nickname'  => '游客',
                'email'  => 'test@qq.com',
                'currentIp' => $this->request->getClientIp() ? : '127.0.0.1',
                'roles'     => array()
            ));

            $biz['user'] = $currentUser;
            $serviceKernel
                ->setBiz($biz)
                ->setCurrentUser($currentUser)
                ->setEnvVariable(array(
                    'host'          => $this->request->getHttpHost(),
                    'schemeAndHost' => $this->request->getSchemeAndHttpHost(),
                    'basePath'      => $this->request->getBasePath(),
                    'baseUrl'       => $this->request->getSchemeAndHttpHost().$this->request->getBasePath()))
                ->setTranslatorEnabled(true)
                ->setTranslator($container->get('translator'))
                ->setParameterBag($container->getParameterBag())
                ->registerModuleDirectory(dirname(__DIR__).'/plugins');

            $this->isServiceKernelInit = true;
        }
    }

    public function getCacheDir()
    {
        $theme = $this->pluginConfigurationManager->getActiveThemeName();
        $theme = empty($theme) ? '' : ucfirst(str_replace('-', '_', $theme));
        return $this->rootDir.'/cache/'.$this->environment.'/'.$theme;
    }
}
