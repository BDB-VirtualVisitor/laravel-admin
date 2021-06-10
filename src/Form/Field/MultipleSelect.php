<?php

namespace Encore\Admin\Form\Field;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class MultipleSelect extends Select
{
    /**
     * Other key for many-to-many relation.
     *
     * @var string
     */
    protected $otherKey;

    /**
     * Get other key for this many-to-many relation.
     *
     * @throws \Exception
     *
     * @return string
     */
    protected function getOtherKey()
    {
        if ($this->otherKey) {
            return $this->otherKey;
        }
        /**
         * If we don't have a method of the parent model, assume that this is a nested relationship
         * This appears to work but may not hold up in all cases
         */
        if (!method_exists($this->form->model(), $this->column)) {
            /**
             * Patch in a VERY VV-specific hack here, which allows us to identify (eg)
             * the "role_id" column of the "excludedRoles" relationship. I don't believe
             * we can do this in a more intelligent, programmatic way, because we don't appear
             * to have any knowledge of the "child model" (ie, the nested mode/form) at this stage
             */
            if (strpos($this->column, 'excluded') === 0) {
                $otherKey = strtolower(
                            \Str::singular(
                                str_replace('excluded', '', $this->column)                                
                            )
                        ).'_id';
            } else {
                $otherKey = \Str::singular($this->column).'_id';
            }
            return $otherKey;
        }
        if (
            is_callable([$this->form->model(), $this->column]) &&
            ($relation = $this->form->model()->{$this->column}()) instanceof BelongsToMany
        ) {
            /* @var BelongsToMany $relation */
            $fullKey = $relation->getQualifiedRelatedPivotKeyName();
            $fullKeyArray = explode('.', $fullKey);

            return $this->otherKey = end($fullKeyArray);
        }

        throw new \Exception('Column of this field must be a `BelongsToMany` relation.');
    }

    /**
     * {@inheritdoc}
     */
    public function fill($data)
    {
        if ($this->form && $this->form->shouldSnakeAttributes()) {
            $key = Str::snake($this->column);
        } else {
            $key = $this->column;
        }

        $relations = Arr::get($data, $key);

        if (is_string($relations)) {
            $this->value = explode(',', $relations);
        }

        if (!is_array($relations)) {
            $this->applyCascadeConditions();

            return;
        }

        $first = current($relations);

        if (is_null($first)) {
            $this->value = null;

        // MultipleSelect value store as an ont-to-many relationship.
        } elseif (is_array($first)) {
            foreach ($relations as $relation) {
                $this->value[] = Arr::get($relation, "pivot.{$this->getOtherKey()}");
            }

            // MultipleSelect value store as a column.
        } else {
            $this->value = $relations;
        }

        $this->applyCascadeConditions();
    }

    /**
     * {@inheritdoc}
     */
    public function setOriginal($data)
    {
        $relations = Arr::get($data, $this->column);

        if (is_string($relations)) {
            $this->original = explode(',', $relations);
        }

        if (!is_array($relations)) {
            return;
        }

        $first = current($relations);

        if (is_null($first)) {
            $this->original = null;

        // MultipleSelect value store as an ont-to-many relationship.
        } elseif (is_array($first)) {
            foreach ($relations as $relation) {
                $this->original[] = Arr::get($relation, "pivot.{$this->getOtherKey()}");
            }

            // MultipleSelect value store as a column.
        } else {
            $this->original = $relations;
        }
    }

    public function prepare($value)
    {
        $value = (array) $value;

        return array_filter($value, 'strlen');
    }
}
