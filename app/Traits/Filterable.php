<?php

namespace App\Traits;

use Illuminate\Database\Query\Builder;

trait Filterable
{
    protected function applyFilters(Builder $query, $filters)
    {
        foreach ($filters as $field => $filterData) {
            $value = $filterData['value'];
            $operator = $filterData['operator'];

            if ($value !== null) {
                $query->where($field, $operator, $value);
            }
        }
    }
}
