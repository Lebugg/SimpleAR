<?php
/**
 * This file contains the ReadOnlyModel class.
 *
 * @author Lebugg
 */
namespace SimpleAR;

/**
 * This class limits object operations to read-only.
 */
abstract class ReadOnlyModel extends Model
{
    /**
     * Disable of addTo.
     *
     * @throws ReadOnlyException in any case.
     */
    public function addTo($relation, $linkedModel)
    {
        throw new ReadOnlyException('addTo');
    }

    /**
     * Disable of create.
     *
     * @throws ReadOnlyException in any case.
     */
    public static function create($attributes)
    {
        throw new ReadOnlyException('create');
    }

    /**
     * Disable of delete.
     *
     * @throws ReadOnlyException in any case.
     */
    public function delete($relationName = null)
    {
        throw new ReadOnlyException('delete');
    }

    /**
     * Disable of remove.
     *
     * @throws ReadOnlyException in any case.
     */
    public static function remove($conditions = array())
    {
        throw new ReadOnlyException('remove');
    }

    /**
     * Disable of removeFrom.
     *
     * @throws ReadOnlyException in any case.
     */
    public function removeFrom($relation, $linkedModel)
    {
        throw new ReadOnlyException('removeFrom');
    }

    /**
     * Disable of save.
     *
     * @throws ReadOnlyException in any case.
     */
    public function save()
    {
        throw new ReadOnlyException('save');
    }
}
