<?php

declare(strict_types=1);

namespace MsgPhp\EavBundle\DependencyInjection;

use MsgPhp\Domain\Infra\DependencyInjection\{ExtensionHelper, FeatureDetection};
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension as BaseExtension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 *
 * @internal
 */
final class Extension extends BaseExtension implements PrependExtensionInterface, CompilerPassInterface
{
    public const ALIAS = 'msgphp_eav';

    public function getAlias(): string
    {
        return self::ALIAS;
    }

    public function getConfiguration(array $config, ContainerBuilder $container): ConfigurationInterface
    {
        return new Configuration();
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__).'/Resources/config'));
        $config = $this->processConfiguration($this->getConfiguration($configs, $container), $configs);

        ExtensionHelper::configureDomain($container, $config['class_mapping'], Configuration::AGGREGATE_ROOTS, Configuration::IDENTITY_MAPPING);

        // message infra
        $loader->load('message.php');
        ExtensionHelper::finalizeCommandHandlers($container, $config['class_mapping'], $config['commands'], array_map(function (string $file): string {
            return Configuration::getPackageNs().'Event\\'.basename($file, '.php');
        }, glob(Configuration::getPackageGlob().'/Event/*Event.php', \GLOB_BRACE)));

        // persistence infra
        if (FeatureDetection::isDoctrineOrmAvailable($container)) {
            $this->loadDoctrineOrm($config, $loader, $container);
        }
    }

    public function prepend(ContainerBuilder $container): void
    {
        $config = $this->processConfiguration($this->getConfiguration($configs = $container->getExtensionConfig($this->getAlias()), $container), $configs);

        if (FeatureDetection::isDoctrineOrmAvailable($container)) {
            ExtensionHelper::configureDoctrineOrm(
                $container,
                $config['class_mapping'],
                $config['id_type_mapping'],
                Configuration::DOCTRINE_TYPE_MAPPING,
                self::getDoctrineMappingFiles($config, $container)
            );
        }
    }

    public function process(ContainerBuilder $container): void
    {
    }

    private static function getDoctrineMappingFiles(array $config, ContainerBuilder $container): array
    {
        return glob(Configuration::getPackageGlob().'/Infra/Doctrine/Resources/dist-mapping/*.orm.xml', \GLOB_BRACE);
    }

    private function loadDoctrineOrm(array $config, LoaderInterface $loader, ContainerBuilder $container): void
    {
        $loader->load('doctrine.php');

        ExtensionHelper::finalizeDoctrineOrmRepositories($container, $config['class_mapping'], Configuration::DOCTRINE_REPOSITORY_MAPPING);
    }
}
