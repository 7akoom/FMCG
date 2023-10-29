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
                if ($operator === 'BETWEEN') {
                    $query->whereBetween($field, $value);
                } elseif ($operator === '>=') {
                    $query->where($field, '>=', $value);
                } elseif ($operator === '<=') {
                    $query->where($field, '<=', $value);
                } else {
                    $query->where($field, $operator, $value);
                }
            }
        }
    }
}
