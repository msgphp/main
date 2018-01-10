<?php

declare(strict_types=1);

namespace MsgPhp\Domain\Infra\Doctrine;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use MsgPhp\Domain\{DomainCollectionInterface, DomainIdInterface};
use MsgPhp\Domain\Exception\{DuplicateEntityException, EntityNotFoundException};

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
trait DomainEntityRepositoryTrait
{
    private $em;
    private $class;
    private $idFields;

    public function __construct(EntityManagerInterface $em, string $class)
    {
        $this->em = $em;
        $this->class = $class;
        $this->idFields = $this->em->getClassMetadata($this->class)->getIdentifierFieldNames();
    }

    private function doFindAll(int $offset = 0, int $limit = 0): DomainCollectionInterface
    {
        return $this->createResultSet($this->createQueryBuilder()->getQuery(), $offset, $limit);
    }

    private function doFindAllByFields(array $fields, int $offset = 0, int $limit = 0): DomainCollectionInterface
    {
        if (!$fields) {
            throw new \LogicException('No fields provided.');
        }

        $this->addFieldCriteria($qb = $this->createQueryBuilder(), $fields);

        return $this->createResultSet($qb->getQuery(), $offset, $limit);
    }

    private function doFind($id, ...$idN)
    {
        if (!$this->isValidEntityId($ids = func_get_args())) {
            throw EntityNotFoundException::createForId($this->class, ...$ids);
        }

        return $this->doFindByFields(array_combine($this->idFields, $ids));
    }

    private function doFindByFields(array $fields)
    {
        if (!$fields) {
            throw new \LogicException('No fields provided.');
        }

        $this->addFieldCriteria($qb = $this->createQueryBuilder(), $fields);
        $qb->setFirstResult(0);
        $qb->setMaxResults(1);

        if (null === $entity = $qb->getQuery()->getOneOrNullResult()) {
            if (count($fields) === count($this->idFields) && array() === array_diff(array_keys($fields), $this->idFields)) {
                throw EntityNotFoundException::createForId($this->class, ...array_values($fields));
            }

            throw EntityNotFoundException::createForFields($this->class, $fields);
        }

        return $entity;
    }

    private function doExists($id, ...$idN): bool
    {
        if (!$this->isValidEntityId($ids = func_get_args())) {
            return false;
        }

        return $this->doExistsByFields(array_combine($this->idFields, $ids));
    }

    private function doExistsByFields(array $fields): bool
    {
        if (!$fields) {
            throw new \LogicException('No fields provided.');
        }

        $this->addFieldCriteria($qb = $this->createQueryBuilder(), $fields);
        $qb->select('1');
        $qb->setFirstResult(0);
        $qb->setMaxResults(1);

        return (bool) $qb->getQuery()->getScalarResult();
    }

    /**
     * @param object $entity
     */
    private function doSave($entity): void
    {
        $this->em->persist($entity);

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException $e) {
            throw DuplicateEntityException::createForId(get_class($entity), ...array_values($this->em->getClassMetadata($this->class)->getIdentifierValues($entity)));
        }
    }

    /**
     * @param object $entity
     */
    private function doDelete($entity): void
    {
        $this->em->remove($entity);
        $this->em->flush();
    }

    private function createResultSet(Query $query, int $offset = null, int $limit = null): DomainCollectionInterface
    {
        if (null !== $offset || !$query->getFirstResult()) {
            $query->setFirstResult($offset ?? 0);
        }

        if (null !== $limit) {
            $query->setMaxResults(0 === $limit ? null : $limit);
        }

        return new DomainCollection(new ArrayCollection($query->getResult()));
    }

    private function createQueryBuilder(string $alias = null): QueryBuilder
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select($this->alias);
        $qb->from($this->class, $alias ?? $this->alias);

        return $qb;
    }

    private function addFieldCriteria(QueryBuilder $qb, array $fields, bool $or = false, string $alias = null): void
    {
        if (!$fields) {
            return;
        }

        $expr = $qb->expr();
        $where = $or ? $expr->orX() : $expr->andX();
        $alias = $alias ?? $qb->getAllAliases()[0] ?? $this->alias;
        $metadata = $this->em->getClassMetadata($this->class);

        foreach ($fields as $field => $value) {
            $fieldAlias = $alias.'.'.$field;
            $value = $this->normalizeEntityId($value);

            if (null === $value) {
                $where->add($expr->isNull($fieldAlias));
            } elseif (true === $value) {
                $where->add($expr->eq($fieldAlias, 'TRUE'));
            } elseif (false === $value) {
                $where->add($expr->eq($fieldAlias, 'FALSE'));
            } elseif (is_array($value)) {
                $where->add($expr->in($fieldAlias, ':'.($param = uniqid($field))));
                $qb->setParameter($param, $value);
            } elseif ($metadata->hasAssociation($field)) {
                $where->add($expr->eq('IDENTITY('.$fieldAlias.')', ':'.($param = uniqid($field))));
                $qb->setParameter($param, $value);
            } else {
                $where->add($expr->eq($fieldAlias, ':'.($param = uniqid($field))));
                $qb->setParameter($param, $value);
            }
        }

        $qb->andWhere($where);
    }

    private function isValidEntityId(array $ids): bool
    {
        if (count($ids) !== count($this->idFields)) {
            return false;
        }

        foreach ($ids as $id) {
            if (null === $this->normalizeEntityId($id)) {
                return false;
            }
        }

        return true;
    }

    private function normalizeEntityId($id)
    {
        if ($id instanceof DomainIdInterface) {
            return $id->isEmpty() ? null : $id;
        }

        if (is_object($id)) {
            $class = get_class($id);
            $metadata = $this->em->getClassMetadata($this->class);

            foreach ($metadata->getIdentifierFieldNames() as $idFieldName) {
                if ($class === $metadata->getAssociationTargetClass($idFieldName)) {
                    return $this->em->getClassMetadata($class)->getIdentifierValues($id) ? $id : null;
                }
            }

            return $id;
        }

        if (is_array($id)) {
            return array_map(function ($id) {
                return $this->normalizeEntityId($id);
            }, $id);
        }

        return $id;
    }
}
