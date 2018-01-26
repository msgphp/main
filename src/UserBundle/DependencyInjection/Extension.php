<?php

declare(strict_types=1);

namespace MsgPhp\UserBundle\DependencyInjection;

use Doctrine\ORM\Version as DoctrineOrmVersion;
use MsgPhp\Domain\Factory\EntityFactoryInterface;
use MsgPhp\Domain\Infra\DependencyInjection\Bundle\{ConfigHelper, ContainerHelper};
use MsgPhp\EavBundle\MsgPhpEavBundle;
use MsgPhp\User\{Entity, Repository, UserIdInterface};
use MsgPhp\User\Infra\{Console as ConsoleInfra, Doctrine as DoctrineInfra, Security as SecurityInfra, Validator as ValidatorInfra};
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension as BaseExtension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Validator\Validation;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
final class Extension extends BaseExtension implements PrependExtensionInterface, CompilerPassInterface
{
    public const ALIAS = 'msgphp_user';

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
        $loader = new PhpFileLoader($container, new FileLocator(dirname(__DIR__).'/Resources/config'));
        $config = $this->processConfiguration($this->getConfiguration($configs, $container), $configs);

        ConfigHelper::resolveResolveDataTypeMapping($container, $config['data_type_mapping']);
        ConfigHelper::resolveClassMapping(Configuration::DATA_TYPE_MAP, $config['data_type_mapping'], $config['class_mapping']);

        $loader->load('services.php');

        ContainerHelper::configureIdentityMap($container, $config['class_mapping'], Configuration::IDENTITY_MAP);
        ContainerHelper::configureEntityFactory($container, $config['class_mapping'], Configuration::AGGREGATE_ROOTS);
        ContainerHelper::configureDoctrineOrmMapping($container, self::getDoctrineMappingFiles($config, $container), [DoctrineInfra\EntityFieldsMapping::class]);

        $bundles = ContainerHelper::getBundles($container);

        // persistence infra
        if (class_exists(DoctrineOrmVersion::class)) {
            $this->prepareDoctrineOrm($config, $loader, $container);
        }

        // framework infra
        if (class_exists(Security::class)) {
            $loader->load('security.php');

            if (!$container->has(Repository\UserRepositoryInterface::class)) {
                $container->removeDefinition(SecurityInfra\SecurityUserProvider::class);
                $container->removeDefinition(SecurityInfra\UserParamConverter::class);
                $container->removeDefinition(SecurityInfra\UserValueResolver::class);
            }
        }

        if (class_exists(Validation::class)) {
            $loader->load('validator.php');

            if (!$container->has(Repository\UserRepositoryInterface::class)) {
                $container->removeDefinition(ValidatorInfra\ExistingUsernameValidator::class);
                $container->removeDefinition(ValidatorInfra\UniqueUsernameValidator::class);
            }
        }

        if (class_exists(ConsoleEvents::class)) {
            $loader->load('console.php');

            if (!$container->has(Repository\UsernameRepositoryInterface::class)) {
                $container->removeDefinition(ConsoleInfra\Command\SynchronizeUsernamesCommand::class);
            }
        }
    }

    public function prepend(ContainerBuilder $container): void
    {
        $config = $this->processConfiguration($this->getConfiguration($configs = $container->getExtensionConfig($this->getAlias()), $container), $configs);

        ConfigHelper::resolveResolveDataTypeMapping($container, $config['data_type_mapping']);
        ConfigHelper::resolveClassMapping(Configuration::DATA_TYPE_MAP, $config['data_type_mapping'], $config['class_mapping']);

        ContainerHelper::configureDoctrineTypes($container, $config['data_type_mapping'], $config['class_mapping'], [
            UserIdInterface::class => DoctrineInfra\Type\UserIdType::class,
        ]);
        ContainerHelper::configureDoctrineOrmTargetEntities($container, $config['class_mapping']);
    }

    public function process(ContainerBuilder $container): void
    {
        if ($container->hasDefinition('data_collector.security')) {
            $container->getDefinition('data_collector.security')
                ->setClass(SecurityInfra\DataCollector::class)
                ->setArgument('$repository', new Reference(Repository\UserRepositoryInterface::class, ContainerBuilder::NULL_ON_INVALID_REFERENCE))
                ->setArgument('$factory', new Reference(EntityFactoryInterface::class, ContainerBuilder::NULL_ON_INVALID_REFERENCE));
        }
    }

    private function prepareDoctrineOrm(array $config, LoaderInterface $loader, ContainerBuilder $container): void
    {
        $loader->load('doctrine.php');

        $classMapping = $config['class_mapping'];

        foreach ([
            DoctrineInfra\Repository\UserRepository::class => $classMapping[Entity\User::class],
            DoctrineInfra\Repository\UsernameRepository::class => $config['username_lookup'] ? Entity\Username::class : null,
            DoctrineInfra\Repository\UserAttributeValueRepository::class => $classMapping[Entity\UserAttributeValue::class] ?? null,
            DoctrineInfra\Repository\UserRoleRepository::class => $classMapping[Entity\UserRole::class] ?? null,
            DoctrineInfra\Repository\UserSecondaryEmailRepository::class => $classMapping[Entity\UserSecondaryEmail::class] ?? null,
        ] as $repository => $class) {
            if (null === $class) {
                ContainerHelper::removeDefinitionWithAliases($container, $repository);
                continue;
            }

            ($definition = $container->getDefinition($repository))
                ->setArgument('$class', $class);

            if (DoctrineInfra\Repository\UserRepository::class === $repository && null !== $config['username_field']) {
                $definition->setArgument('$fieldMapping', ['username' => $config['username_field']]);
            }

            if (DoctrineInfra\Repository\UsernameRepository::class === $repository) {
                $definition->setArgument('$targetMapping', $config['username_lookup']);
            }
        }

        if ($config['username_lookup']) {
            $container->getDefinition(DoctrineInfra\Event\UsernameListener::class)
                ->setArgument('$mapping', $config['username_lookup']);
        } else {
            $container->removeDefinition(DoctrineInfra\Event\UsernameListener::class);
        }
    }

    private static function getDoctrineMappingFiles(array $config, ContainerBuilder $container): array
    {
        $files = glob(($baseDir = dirname((new \ReflectionClass(UserIdInterface::class))->getFileName()).'/Infra/Doctrine/Resources/dist-mapping').'/*.orm.xml');
        $files = array_flip($files);

        if (!ContainerHelper::hasBundle($container, MsgPhpEavBundle::class)) {
            unset($files[$baseDir.'/User.Entity.UserAttributeValue.orm.xml']);
        }

        if (!$config['username_lookup']) {
            unset($files[$baseDir.'/User.Entity.Username.orm.xml']);
        }

        return array_values(array_flip($files));
    }
}
