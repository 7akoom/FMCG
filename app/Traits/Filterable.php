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
                if ($field === 'credit' || $field === 'debit') {
                    $this->applyCreditDebitFilter($query, $field, $operator, $value);
                } else {
                    if ($operator === 'LIKE') {
                        $query->where($field, 'LIKE', $value);
                    } elseif ($operator === 'BETWEEN') {
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

    protected function applyCreditDebitFilter(Builder $query, $field, $operator, $value)
    {
        $query->whereRaw("COALESCE($field, 0) $operator ?", [$value]);
    }
}
