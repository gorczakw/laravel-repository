<?php

declare(strict_types=1);

namespace Gorczakw\LaravelRepository;

use Gorczakw\LaravelRepository\Contracts\Filterable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait HasFilters
{
    public function applyFilters(Builder $query, ?array $requestData = []): void
    {
        $model = $query->getModel();
        foreach ($requestData as $filter => $value) {
            $methodName = 'applyFilter' . Str::camel($filter);

            if (method_exists($this, $methodName)) {
                $this->$methodName($query, $value);
            } elseif ($model instanceof Filterable && in_array($filter, $model->getFilterable(), true)) {
                if (!empty($model->filterTypes[$filter])) {
                    $this->columnFilterByType($query, $model->filterTypes[$filter], $filter, $value);
                } else {
                    $this->columnFilter($query, $filter, $value);
                }
            } elseif (method_exists($model, 'parameters')) {
                $this->parameterFilter($query, $filter, $value);
            }
        }
    }

    public function parseRawFields(array $rawFields): array
    {
        $fields = [];

        foreach ($rawFields as $key => $field) {
            $fields[] = DB::raw($field . ' as ' . $key);
        }

        return $fields;
    }

    public function applyFilterSearch(Builder $query, mixed $value): void
    {
        $value = trim((string)$value);
        if ($value !== '') {
            $query->where('name', 'LIKE', '%' . $value . '%');
        }
    }

    private function columnFilter(Builder $query, $filter, $value): void
    {
        if (isset($value['min']) && isset($value['max'])) {
            $this->columnFilterByType($query, 'range', $filter, $value);
        } elseif (is_array($value)) {
            $this->columnFilterByType($query, 'array', $filter, $value);
        } elseif (strpos((string)$value, '%')) {
            $this->columnFilterByType($query, 'like', $filter, $value);
        } else {
            $this->columnFilterByType($query, 'default', $filter, $value);
        }
    }

    private function columnFilterByType(Builder $query, string $type, $filter, $value): void
    {
        $raw = array_key_exists($filter, $this->rawFields ?? []);
        $filter = $raw && !empty($this->rawFields[$filter]) ? DB::raw($this->rawFields[$filter]) : $filter;

        switch ($type) {
            case 'range':
                $query->where($filter, '>=', $value['min'])->where($filter, '<=', $value['max']);
                break;
            case 'array':
                if ($raw) {
                    $query->where(static function (Builder $have) use ($filter, $value) {
                        foreach ($value as $itemValue) {
                            $have->whereRaw($filter, $itemValue);
                        }
                    });
                } else {
                    $query->whereIn($filter, $value);
                }

                break;
            case 'like':
                $query->where($filter, 'LIKE', $value);
                break;
            default:
                $query->where($filter, $value);
                break;
        }
    }

    private function parameterFilter(Builder $query, $filter, $value): void
    {
        $query->whereHas('parameters', static function (Builder $queryHas) use ($filter, $value) {
            if (is_array($value) && count($value) === 2) {
                $queryHas->where('key', $filter)->where('filter_value', '>=', $value[0])->where('filter_value', '<=', $value[1])->limit(1);
            } else {
                $queryHas->where('key', $filter)->where('filter_value', strpos((string)$value, '%') ? 'LIKE' : '=', $value)->limit(1);
            }
        });
    }
}
