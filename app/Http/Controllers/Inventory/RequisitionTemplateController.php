<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\RequisitionTemplateUpsertRequest;
use App\Models\RequisitionTemplate;
use App\Services\AuditLogService;
use App\Services\Inventory\RequisitionTemplateService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class RequisitionTemplateController extends Controller
{
    public function store(
        RequisitionTemplateUpsertRequest $request,
        RequisitionTemplateService $templates,
    ): RedirectResponse {
        $this->authorize('create', RequisitionTemplate::class);

        $validated = $request->validated();

        $template = RequisitionTemplate::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'notes' => $validated['notes'] ?? null,
            'lines' => $templates->normalizeLines($validated['lines']),
        ]);

        AuditLogService::logCreated($template, "Requisition template {$template->name} created.");

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template saved.')]);

        return back();
    }

    public function update(
        RequisitionTemplateUpsertRequest $request,
        RequisitionTemplate $requisitionTemplate,
        RequisitionTemplateService $templates,
    ): RedirectResponse {
        $this->authorize('update', $requisitionTemplate);

        $validated = $request->validated();
        $oldValues = $requisitionTemplate->toArray();

        $requisitionTemplate->update([
            'name' => $validated['name'],
            'notes' => $validated['notes'] ?? null,
            'lines' => $templates->normalizeLines($validated['lines']),
        ]);

        AuditLogService::logUpdated($requisitionTemplate, $oldValues, "Requisition template {$requisitionTemplate->name} updated.");

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template updated.')]);

        return back();
    }

    public function duplicate(RequisitionTemplate $requisitionTemplate): RedirectResponse
    {
        $this->authorize('duplicate', $requisitionTemplate);

        $copy = RequisitionTemplate::create([
            'user_id' => $requisitionTemplate->user_id,
            'name' => $this->duplicateName($requisitionTemplate->name),
            'notes' => $requisitionTemplate->notes,
            'lines' => $requisitionTemplate->lines,
        ]);

        AuditLogService::logCreated($copy, "Requisition template {$copy->name} duplicated.");

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template duplicated.')]);

        return back();
    }

    public function destroy(RequisitionTemplate $requisitionTemplate): RedirectResponse
    {
        $this->authorize('delete', $requisitionTemplate);

        AuditLogService::logDeleted($requisitionTemplate, "Requisition template {$requisitionTemplate->name} deleted.");
        $requisitionTemplate->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template deleted.')]);

        return back();
    }

    private function duplicateName(string $name): string
    {
        $prefix = 'Copy of ';
        $candidate = $prefix.$name;

        return mb_strimwidth($candidate, 0, 120, '');
    }
}
