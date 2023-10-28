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
                    // If the operator is 'BETWEEN', assume $value is an array with [start_date, end_date]
                    $query->whereBetween($field, $value);
                } elseif ($operator === '>=') {
                    // If the operator is '>=', assume it's a start_date
                    $query->where($field, '>=', $value);
                } elseif ($operator === '<=') {
                    // If the operator is '<=', assume it's an end_date
                    $query->where($field, '<=', $value);
                } else {
                    $query->where($field, $operator, $value);
                }
            }
        }
    }
}
