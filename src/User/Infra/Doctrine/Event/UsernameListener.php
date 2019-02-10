<?php

declare(strict_types=1);

namespace MsgPhp\User\Infra\Doctrine\Event;

use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Mapping\MappingException;
use MsgPhp\Domain\Factory\DomainObjectFactoryInterface;
use MsgPhp\User\Entity\{User, Username};

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
final class UsernameListener
{
    /**
     * @var DomainObjectFactoryInterface
     */
    private $factory;

    /**
     * @var array[]
     */
    private $targetMappings;

    /**
     * @param array[] $targetMappings
     */
    public function __construct(DomainObjectFactoryInterface $factory, array $targetMappings)
    {
        $this->factory = $factory;
        $this->targetMappings = $targetMappings;
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $event): void
    {
        $metadata = $event->getClassMetadata();

        if (!isset($this->targetMappings[$metadata->getName()])) {
            return;
        }

        try {
            $metadata->addEntityListener('prePersist', self::class, 'add');
            $metadata->addEntityListener('preUpdate', self::class, 'update');
            $metadata->addEntityListener('preRemove', self::class, 'remove');
        } catch (MappingException $e) {
            // duplicate
        }
    }

    /**
     * @param object $entity
     */
    public function add($entity, LifecycleEventArgs $event): void
    {
        $em = $event->getEntityManager();

        foreach ($this->createUsernames($entity, $em) as $username) {
            $em->persist($username);
        }
    }

    /**
     * @param object $entity
     */
    public function update($entity, PreUpdateEventArgs $event): void
    {
        $em = $event->getEntityManager();

        foreach ($this->getTargetMapping($entity, $em) as $field => $mappedBy) {
            if (!$event->hasChangedField($field)) {
                continue;
            }

            $oldUsername = $event->getOldValue($field);
            $newUsername = $event->getNewValue($field);

            if (null !== $oldUsername) {
                $em->remove($this->getUsername($oldUsername));
            }

            if (null !== $newUsername) {
                $user = null === $mappedBy ? $entity : $em->getClassMetadata(\get_class($entity))->getFieldValue($entity, $mappedBy);

                if (null !== $user) {
                    $em->persist($this->createUsername($user, $newUsername));
                }
            }
        }
    }

    /**
     * @param object $entity
     */
    public function remove($entity, LifecycleEventArgs $event): void
    {
        $em = $event->getEntityManager();
        $metadata = $em->getClassMetadata(\get_class($entity));

        foreach (array_keys($this->getTargetMapping($entity, $em)) as $field) {
            if (null === $username = $metadata->getFieldValue($entity, $field)) {
                continue;
            }

            $em->remove($this->getUsername($username));
        }
    }

    /**
     * @param object $entity
     *
     * @return Username[]
     */
    private function createUsernames($entity, EntityManagerInterface $em): iterable
    {
        $metadata = $em->getClassMetadata(\get_class($entity));

        foreach ($this->getTargetMapping($entity, $em) as $field => $mappedBy) {
            $user = null === $mappedBy ? $entity : $metadata->getFieldValue($entity, $mappedBy);

            if (null === $user || null === $username = $metadata->getFieldValue($entity, $field)) {
                continue;
            }

            yield $this->createUsername($user, $username);
        }
    }

    private function createUsername(User $user, string $username): Username
    {
        return $this->factory->create(Username::class, compact('user', 'username'));
    }

    private function getUsername(string $username): Username
    {
        return $this->factory->reference(Username::class, compact('username'));
    }

    /**
     * @param object $entity
     */
    private function getTargetMapping($entity, EntityManagerInterface $em): array
    {
        if (isset($this->targetMappings[$class = ClassUtils::getClass($entity)])) {
            return $this->targetMappings[$class];
        }

        foreach ($em->getClassMetadata($class)->parentClasses as $parent) {
            if (isset($this->targetMappings[$parent])) {
                return $this->targetMappings[$parent];
            }
        }

        throw new \LogicException(sprintf('No username mapping available for entity "%s".', $class));
    }
}
