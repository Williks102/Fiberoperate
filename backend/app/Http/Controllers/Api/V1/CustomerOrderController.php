<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Http\Resources\CustomerOrderResource;
use App\Models\CustomerOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/** Liste et fiche des commandes clients (lecture) ; les actions (mise en étude, programmation) viennent par tranches. */
class CustomerOrderController extends ApiResourceController
{
    protected function modelClass(): string
    {
        return CustomerOrder::class;
    }

    protected function resourceClass(): string
    {
        return CustomerOrderResource::class;
    }

    protected function orderBy(Builder $query): void
    {
        $query->orderByDesc('created_at')->orderBy('customer_ref');
    }

    protected function load(Model $model): Model
    {
        return $model->load(['zone:id,code', 'pco:id,code', 'history.author:id,name']);
    }

    protected function filters(Request $request, Builder $query): void
    {
        $status = OrderStatus::tryFrom((string) $request->query('status'));

        $query->with(['zone:id,code', 'pco:id,code'])
            ->when($status, fn ($q) => $q->where('status', $status->value))
            ->when($request->query('zone_id'), fn ($q, $id) => $q->where('zone_id', $id))
            ->when($request->string('q')->trim()->value(), function ($q, $term) {
                $like = '%'.mb_strtolower($term).'%';
                $q->where(fn ($w) => $w->whereRaw('lower(name) like ?', [$like])
                    ->orWhereRaw('lower(customer_ref) like ?', [$like])
                    ->orWhere('phone_1', 'like', '%'.preg_replace('/\D/', '', $term).'%')
                    ->orWhereRaw('lower(ft_line) like ?', [$like]));
            });
    }
}
