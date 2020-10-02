<?php

/**
 * KaikMedia AuthModule
 *
 * @package    KaikmediaAuthModule
 * @author     Kaik <contact@kaikmedia.com>
 * @copyright  KaikMedia
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @link       https://github.com/Kaik/KaikmediaAuthModule.git
 */

namespace Kaikmedia\AuthModule\Listener;

use Kaikmedia\AuthModule\Constant;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Zikula\ExtensionsModule\Api\ApiInterface\VariableApiInterface;
use Zikula\ThemeModule\Engine\Asset;
use Zikula\ThemeModule\Engine\AssetBag;

/**
 * Class AddGlobalConfigListener
 */
class AddGlobalConfigListener implements EventSubscriberInterface
{
    /**
     * @var VariableApiInterface
     */
    private $variableApi;

    /**
     * @var \Twig_Environment
     */
    private $twig;

    /**
     * @var AssetBag
     */
    private $jsAssetBag;

    /**
     * @var Asset
     */
    private $assetHelper;

    /**
     * @var AssetBag
     */
    private $headers;

    /**
     * JSConfig constructor.
     * @param VariableApiInterface $variableApi
     * @param \Twig_Environment $twig
     * @param AssetBag $jsAssetBag
     * @param Asset $assetHelper
     * @param AssetBag $headers
     */
    public function __construct(
        VariableApiInterface $variableApi,
        \Twig_Environment $twig,
        AssetBag $jsAssetBag,
        Asset $assetHelper,
        AssetBag $headers
    ) {
        $this->variableApi = $variableApi;
        $this->twig = $twig;
        $this->jsAssetBag = $jsAssetBag;
        $this->assetHelper = $assetHelper;
        $this->headers = $headers;
    }

    /**
     * Generate a configuration for javascript and add script to headers.
     */
    public function addFacebookJSConfig(FilterResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        if (!$this->variableApi->get(Constant::MODNAME, 'enabled')) {
            return;
        }

        $config = $this->variableApi->get(Constant::MODNAME, 'global', []);

        $config = array_map('htmlspecialchars', $config);
        $content = $this->twig->render('@KaikmediaAuthModule/Admin/JSConfig.html.twig', [
            'config' => $config
        ]);
        $this->headers->add([$content => 1]);
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::RESPONSE => [
                ['addFacebookJSConfig', -1]
            ]
        ];
    }
}
