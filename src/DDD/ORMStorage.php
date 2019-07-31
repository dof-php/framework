<?php

declare(strict_types=1);

namespace Dof\Framework\DDD;

use Dof\Framework\StorageManager;
use Dof\Framework\RepositoryManager;
use Dof\Framework\Collection;
use Dof\Framework\TypeHint;

/**
 * In Dof, ORMStorage also the configuration of ORM
 */
class ORMStorage extends Storage
{
    /**
     * @Column(id)
     * @Type(int)
     * @Length(10)
     * @Unsigned(1)
     * @AutoInc(1)
     * @Notnull(1)
     */
    protected $id;

    final public function builder()
    {
        return $this->__storage->builder();
    }

    final public function count() : int
    {
        return $this->builder()->count();
    }

    final public function paginate(int $page, int $size)
    {
        return $this->converts($this->builder()->paginate($page, $size));
    }

    final public static function table(bool $database = false, bool $prefix = true) : string
    {
        $meta = self::annotations()['meta'] ?? [];
        $table = $meta['TABLE'] ?? '';
        if ($prefix) {
            $prefix = $meta['PREFIX'] ?? '';
        }
        if ($database && ($database = $meta['DATABASE'] ?? '')) {
            $database = "`{$database}`.";
        }

        return "{$database}`{$prefix}{$table}`";
    }

    final public function create(Entity &$entity) : Entity
    {
        return $this->add($entity);
    }

    final public function add(Entity &$entity) : Entity
    {
        $storage = static::class;
        $annotation = StorageManager::get($storage);
        $columns = $annotation['columns'] ?? [];
        if (! $columns) {
            exception('NoColumnsOnStorageToAdd', compact('storage'));
        }

        unset($columns['id']);

        $data = [];
        foreach ($columns as $column => $property) {
            $attribute = $annotation['properties'][$property] ?? [];
            $val = $entity->{$property} ?? null;
            // Null value check and set default if necessary
            if (is_null($val)) {
                $val = $attribute['DEFAULT'] ?? null;
            }

            $type = $attribute['TYPE'] ?? null;
            if (! $type) {
                $entity = get_class($entity);
                exception('MissingEntityType', compact('type', 'attribute', 'storage', 'entity'));
            }
            if (! TypeHint::support($type)) {
                $entity = get_class($entity);
                exception('UnsupportedEntityType', compact('type', 'attribute', 'storage', 'entity'));
            }

            $data[$column] = TypeHint::convert($val, $type, true);
        }

        if (! $data) {
            exception('NoDataForStorageToAdd', [
                'storage' => static::class,
                'entity'  => get_class($entity),
            ]);
        }

        $id = $this->__storage->add($data);
        $entity->setId($id);

        // Add entity into repository cache
        RepositoryManager::add($storage, $entity);

        if (method_exists($entity, 'onCreated')) {
            $entity->onCreated();
        }

        return $entity;
    }

    final public function deletes(array $pks)
    {
        $this->removes($pks);
    }

    final public function removes(array $pks)
    {
        $pks = array_unique($pks);
        foreach ($pks as $pk) {
            if (! TypeHint::isPint($pk)) {
                continue;
            }
            $pk = TypeHint::convertToPint($pk);
            $this->remove($pk);
        }
    }

    final public function delete($entity) : ?int
    {
        return $this->remove($entity);
    }

    final public function remove($entity) : ?int
    {
        if ((! is_int($entity)) && (! ($entity instanceof Entity))) {
            return 0;
        }
        if (is_int($entity)) {
            if ($entity < 1) {
                return 0;
            }

            $entity = $this->find($entity);
            if (! $entity) {
                return 0;
            }
        }

        $pk = $entity->getPk();
        $storage = static::class;

        try {
            // Ignore when entity not exists in repository
            $res = $this->__storage->delete($pk);
            // Remove entity from repository cache
            RepositoryManager::remove($storage, $entity);

            if ($res > 0) {
                if (method_exists($entity, 'onRemoved')) {
                    $entity->onRemoved();
                } elseif (method_exists($entity, 'onDeleted')) {
                    $entity->onDeleted();
                }
            }

            return $res;
        } catch (Throwable $e) {
            $entity = get_class($entity);
            exception('RemoveEntityFailed', compact('entity', 'pk', 'storage'), $e);
        }
    }

    final public function save(Entity &$entity) : ?Entity
    {
        $_pk = $entity->getPk();
        if ((! is_int($_pk)) || ($_pk < 1)) {
            return null;
        }
        $_entity = $this->find($_pk);
        if (! $_entity) {
            return null;
        }

        $storage = static::class;
        $annotation = StorageManager::get($storage);
        $columns = $annotation['columns'] ?? [];

        if (! $columns) {
            exception('NoColumnsOnStorageToUpdate', ['storage' => static::class]);
        }

        // Primary key is not allowed to update
        unset($columns['id']);

        $data = [];
        foreach ($columns as $column => $property) {
            $attribute = $annotation['properties'][$property] ?? [];
            $val = $entity->{$property} ?? null;
            // Null value check and set default if specific
            if (is_null($val)) {
                $val = $attribute['DEFAULT'] ?? null;
            }

            $type = $attribute['TYPE'] ?? null;
            if (! $type) {
                $entity = get_class($entity);
                exception('MissingEntityType', compact('type', 'attribute', 'storage', 'entity'));
            }
            if (! TypeHint::support($type)) {
                $entity = get_class($entity);
                exception('UnsupportedEntityType', compact('type', 'attribute', 'storage', 'entity'));
            }

            $data[$column] = TypeHint::convert($val, $type, true);
        }

        if (! $data) {
            exception('NoDataForStorageToUpdate', [
                'storage' => static::class,
                'entity'  => get_class($entity),
            ]);
        }

        $this->__storage->update($entity->getId(), $data);

        // Update/Reset repository cache
        RepositoryManager::update($storage, $entity);

        if (method_exists($entity, 'onUpdated')) {
            $entity->onUpdated($_entity);
        }

        return $entity;
    }

    final public function finds(array $pks)
    {
        $list = [];

        $pks = array_unique($pks);
        foreach ($pks as $pk) {
            if (! TypeHint::isPint($pk)) {
                continue;
            }
            $pk = TypeHint::convertToPint($pk);
            if ($entity = $this->find($pk)) {
                $list[] = $entity;
            }
        }

        return $list;
    }

    final public function find(int $pk) : ?Entity
    {
        // Find in repository cache first
        if ($entity = RepositoryManager::find(static::class, $pk)) {
            return $entity;
        }

        $result = $this->__storage->find($pk);
        if (! $result) {
            return null;
        }

        $entity = RepositoryManager::map(static::class, $result);

        RepositoryManager::add(static::class, $entity);

        return $entity;
    }

    public function list(
        int $page,
        int $size,
        Collection $filter,
        string $sortField = null,
        string $sortOrder = null
    ) {
        $builder = $this->builder();

        if ($sortField && ($column = $this->column($sortField))) {
            $builder->order($column, $sortOrder ?: 'desc');
        } else {
            $builder->order('id', 'desc');
        }

        $list = $builder->paginate($page, $size);

        return $this->converts($list);
    }

    final public function column(string $attr) : ?string
    {
        return $this->annotations()['properties'][$attr]['COLUMN'] ?? null;
    }

    /**
     * Set a value for a entity attr without guaranteeing record existence
     */
    final public function set(int $pk, string $attr, $value)
    {
        $column = $this->column($attr);
        if (! $column) {
            exception('MissingColumnOfAttributeToSet', compact('attr'));
        }

        $res = $this->builder()->where('id', $pk)->set($column, $value);

        if ($res > 0) {
            RepositoryManager::remove(static::class, $pk);
        }

        return $this;
    }

    /**
     * Set a batch value for entity attrs without guaranteeing record existence
     */
    final public function update(int $pk, array $data)
    {
        $_data = [];
        foreach ($data as $attr => $value) {
            $column = $this->column($attr);
            if (! $column) {
                exception('MissingColumnOfAttributeToSet', compact('attr'));
            }
            $_data[$column] = $value;
        }

        if (! $_data) {
            return $this;
        }

        $res = $this->builder()->where('id', $pk)->update($_data);

        if ($res > 0) {
            RepositoryManager::remove(static::class, $pk);
        }

        return $this;
    }

    /**
     * Flush entity cache only - single
     *
     * @param int $pk
     */
    final public function flush(int $pk)
    {
        RepositoryManager::remove(static::class, $pk);
    }

    /**
     * Flush entity cache only - multiples
     *
     * @param array $pks
     */
    final public function flushs(array $pks)
    {
        RepositoryManager::removes(static::class, $pks);
    }
}
