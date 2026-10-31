<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Enums\StudyResult;
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
        return $model->load(['zone:id,code', 'pco:id,code', 'history.author:id,name', 'interventions.technician:id,name', 'interventions.reason', 'studies']);
    }

    protected function filters(Request $request, Builder $query): void
    {
        $status = OrderStatus::tryFrom((string) $request->query('status'));

        $query->with(['zone:id,code', 'pco:id,code', 'interventions' => fn ($q) => $q->where(fn ($w) => $w->where('status', 'planned')->orWhereIn('outcome', ['postponed', 'failed']))->with(['technician:id,name', 'reason'])])
            ->when($request->query('stage'), fn ($q, $stage) => $this->stage($q, (string) $stage))
            ->when($status, fn ($q) => $q->where('status', $status->value))
            ->when($request->query('zone_id'), fn ($q, $id) => $q->where('zone_id', $id))
            ->when($request->string('q')->trim()->value(), function ($q, $term) {
                $like = '%'.mb_strtolower($term).'%';
                $digits = preg_replace('/\D/', '', $term);

                $q->where(fn ($w) => $w->whereRaw('lower(name) like ?', [$like])
                    ->orWhereRaw('lower(customer_ref) like ?', [$like])
                    ->orWhereRaw('lower(ft_line) like ?', [$like])
                    ->when($digits !== '', fn ($d) => $d->orWhere('phone_1', 'like', "%{$digits}%")));
            });
    }

    /**
     * Les trois listes du raccordement : instances (étude à faire, ou étude sans client raccordable : non faisable, client
     * absent), raccordables (étude faite, installation à programmer, reportée ou en échec), raccordés (installés, activés).
     */
    private function stage(Builder $query, string $stage): void
    {
        $instance = [OrderStatus::Received->value, OrderStatus::InStudy->value];
        $connectable = [OrderStatus::AppointmentSet->value, OrderStatus::Postponed->value, OrderStatus::Failed->value];
        $connected = [OrderStatus::Installed->value, OrderStatus::Activated->value];

        match ($stage) {
            'instances' => $query->where(fn ($q) => $q->whereIn('status', $instance)
                ->orWhere(fn ($w) => $w->where('status', OrderStatus::Studied->value)->where(
                    fn ($r) => $r->whereNull('study_result')->orWhere('study_result', '!=', StudyResult::Feasible->value)
                ))),
            'raccordables' => $query->where(fn ($q) => $q->whereIn('status', $connectable)
                ->orWhere(fn ($w) => $w->where('status', OrderStatus::Studied->value)->where('study_result', StudyResult::Feasible->value))),
            'raccordes' => $query->whereIn('status', $connected),
            default => $query->whereRaw('false'),
        };
    }
}
