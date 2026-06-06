<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\GoLiveChecklistItem;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * GoLive Checklist Controller
 * 
 * API endpoints for managing GoLive verification tasks
 */
class GoLiveChecklistController extends Controller
{
    /**
     * Get all checklist items with filtering
     * 
     * GET /api/v1/golive-checklist
     * Query params: category, status, priority
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = tenant();
        
        $query = GoLiveChecklistItem::where('tenant_id', $tenant->id);
        
        if ($request->has('category')) {
            $query->where('category', $request->input('category'));
        }
        
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }
        
        if ($request->has('priority')) {
            $query->where('priority', $request->input('priority'));
        }
        
        $items = $query->orderBy('sequence')->get();
        
        // Calculate progress
        $totalItems = $items->count();
        $completedItems = $items->where('status', 'completed')->count();
        $progress = $totalItems > 0 ? ($completedItems / $totalItems) * 100 : 0;
        
        // Get critical blocking items
        $blocking = $items->where('status', '!=', 'completed')
            ->where('risk_level', 'critical');
        
        return response()->json([
            'data' => $items,
            'meta' => [
                'total' => $totalItems,
                'completed' => $completedItems,
                'incomplete' => $totalItems - $completedItems,
                'progress_percent' => round($progress, 2),
                'blocking_items_count' => $blocking->count(),
                'can_golive' => $blocking->count() === 0,
            ],
        ]);
    }

    /**
     * Get items grouped by category
     * 
     * GET /api/v1/golive-checklist/by-category
     */
    public function byCategory(): JsonResponse
    {
        $tenant = tenant();
        
        $items = GoLiveChecklistItem::where('tenant_id', $tenant->id)
            ->orderBy('sequence')
            ->get()
            ->groupBy('category');
        
        $categories = [];
        foreach ($items as $category => $categoryItems) {
            $completed = $categoryItems->where('status', 'completed')->count();
            $total = $categoryItems->count();
            
            $categories[$category] = [
                'items' => $categoryItems->values(),
                'completed' => $completed,
                'total' => $total,
                'progress_percent' => ($completed / $total) * 100,
            ];
        }
        
        return response()->json(['data' => $categories]);
    }

    /**
     * Get items with dependency chain
     * 
     * GET /api/v1/golive-checklist/dependencies
     */
    public function getDependencies(): JsonResponse
    {
        $tenant = tenant();
        
        $items = GoLiveChecklistItem::where('tenant_id', $tenant->id)
            ->orderBy('sequence')
            ->get();
        
        $graph = [];
        foreach ($items as $item) {
            $graph[$item->item_code] = [
                'item' => $item,
                'dependencies' => $item->dependencies ?? [],
                'blocked_by' => [],
            ];
        }
        
        // Build blocked_by relationships
        foreach ($graph as $code => &$node) {
            if (!empty($node['dependencies'])) {
                foreach ($node['dependencies'] as $dep) {
                    if (isset($graph[$dep])) {
                        $graph[$dep]['blocked_by'][] = $code;
                    }
                }
            }
        }
        
        return response()->json(['data' => $graph]);
    }

    /**
     * Mark item as complete
     * 
     * PATCH /api/v1/golive-checklist/{id}/complete
     */
    public function markComplete(GoLiveChecklistItem $item, Request $request): JsonResponse
    {
        $this->authorize('update', $item);
        
        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
            'evidence_url' => 'nullable|url',
        ]);
        
        $item->mark_complete(
            auth()->id(),
            $validated['notes'] ?? null
        );
        
        if ($validated['evidence_url'] ?? null) {
            $item->update(['evidence_url' => $validated['evidence_url']]);
        }
        
        return response()->json([
            'message' => 'Item marked as complete',
            'data' => $item,
        ]);
    }

    /**
     * Mark item as failed
     * 
     * PATCH /api/v1/golive-checklist/{id}/fail
     */
    public function markFailed(GoLiveChecklistItem $item, Request $request): JsonResponse
    {
        $this->authorize('update', $item);
        
        $validated = $request->validate([
            'notes' => 'required|string|max:1000',
        ]);
        
        $item->mark_failed($validated['notes']);
        
        return response()->json([
            'message' => 'Item marked as failed',
            'data' => $item,
        ]);
    }

    /**
     * Get status summary
     * 
     * GET /api/v1/golive-checklist/summary
     */
    public function getSummary(): JsonResponse
    {
        $tenant = tenant();
        
        $items = GoLiveChecklistItem::where('tenant_id', $tenant->id)->get();
        
        $summary = [
            'total_items' => $items->count(),
            'completed' => $items->where('status', 'completed')->count(),
            'in_progress' => $items->where('status', 'in_progress')->count(),
            'not_started' => $items->where('status', 'not_started')->count(),
            'failed' => $items->where('status', 'failed')->count(),
            'blocked' => $items->where('status', '!=', 'completed')
                ->where('risk_level', 'critical')->count(),
            'progress_percent' => ($items->where('status', 'completed')->count() / $items->count()) * 100,
            'estimated_hours_remaining' => $items->where('status', '!=', 'completed')
                ->sum('estimated_hours'),
            'can_golive' => $items->where('status', '!=', 'completed')
                ->where('risk_level', 'critical')->count() === 0,
            'golive_readiness' => $this->calculateReadiness($items),
        ];
        
        return response()->json(['data' => $summary]);
    }

    /**
     * Get GoLive Readiness Report
     * 
     * GET /api/v1/golive-checklist/readiness-report
     */
    public function getReadinessReport(): JsonResponse
    {
        $tenant = tenant();
        
        $items = GoLiveChecklistItem::where('tenant_id', $tenant->id)
            ->orderBy('sequence')
            ->get();
        
        $report = [
            'timestamp' => now(),
            'tenant' => [
                'code' => $tenant->code,
                'name' => $tenant->name,
            ],
            'overall_status' => $this->getOverallStatus($items),
            'categories' => $this->getCategoryReport($items),
            'critical_issues' => $items->where('status', '!=', 'completed')
                ->where('risk_level', 'critical')
                ->values(),
            'recommendations' => $this->getRecommendations($items),
            'estimated_time_to_golive_hours' => $items->where('status', '!=', 'completed')
                ->sum('estimated_hours'),
            'go_live_eligible' => $items->where('status', '!=', 'completed')
                ->where('risk_level', 'critical')->count() === 0,
        ];
        
        return response()->json(['data' => $report]);
    }

    /**
     * Export checklist as PDF/CSV
     * 
     * GET /api/v1/golive-checklist/export?format=pdf
     */
    public function export(Request $request): JsonResponse
    {
        $format = $request->input('format', 'json'); // json, csv, pdf
        $tenant = tenant();
        
        $items = GoLiveChecklistItem::where('tenant_id', $tenant->id)
            ->orderBy('sequence')
            ->get();
        
        if ($format === 'csv') {
            return $this->exportCsv($items);
        } elseif ($format === 'pdf') {
            return $this->exportPdf($items);
        }
        
        return response()->json(['data' => $items]);
    }

    /**
     * Update item
     * 
     * PATCH /api/v1/golive-checklist/{id}
     */
    public function update(GoLiveChecklistItem $item, Request $request): JsonResponse
    {
        $this->authorize('update', $item);
        
        $validated = $request->validate([
            'status' => 'in:not_started,in_progress,completed,failed',
            'assigned_to' => 'nullable|exists:lms_users,id',
            'notes' => 'nullable|string',
            'priority' => 'in:low,medium,high,critical',
        ]);
        
        $item->update($validated);
        
        return response()->json([
            'message' => 'Item updated successfully',
            'data' => $item,
        ]);
    }

    // Private helper methods

    protected function calculateReadiness($items)
    {
        $total = $items->count();
        $completed = $items->where('status', 'completed')->count();
        $critical_completed = $items->where('risk_level', 'critical')
            ->where('status', 'completed')->count();
        $critical_total = $items->where('risk_level', 'critical')->count();
        
        return [
            'overall_percent' => round(($completed / $total) * 100, 1),
            'critical_percent' => round(($critical_completed / $critical_total) * 100, 1),
            'status' => $critical_completed === $critical_total ? 'READY' : 'NOT_READY',
        ];
    }

    protected function getOverallStatus($items)
    {
        $completed = $items->where('status', 'completed')->count();
        $total = $items->count();
        $percent = ($completed / $total) * 100;
        
        if ($percent >= 90) {
            return 'EXCELLENT';
        } elseif ($percent >= 70) {
            return 'GOOD';
        } elseif ($percent >= 50) {
            return 'FAIR';
        } else {
            return 'POOR';
        }
    }

    protected function getCategoryReport($items)
    {
        $grouped = $items->groupBy('category');
        $report = [];
        
        foreach ($grouped as $category => $categoryItems) {
            $completed = $categoryItems->where('status', 'completed')->count();
            $total = $categoryItems->count();
            
            $report[$category] = [
                'completed' => $completed,
                'total' => $total,
                'percent' => round(($completed / $total) * 100, 1),
                'status' => $completed === $total ? 'COMPLETE' : 'INCOMPLETE',
            ];
        }
        
        return $report;
    }

    protected function getRecommendations($items)
    {
        $recommendations = [];
        
        // Check critical items
        $criticalIncomplete = $items->where('status', '!=', 'completed')
            ->where('risk_level', 'critical');
        
        if ($criticalIncomplete->count() > 0) {
            $recommendations[] = "URGENT: {$criticalIncomplete->count()} critical items must be completed before GoLive";
        }
        
        // Check failed items
        $failed = $items->where('status', 'failed');
        if ($failed->count() > 0) {
            $recommendations[] = "ATTENTION: {$failed->count()} items marked as failed, need to be re-addressed";
        }
        
        return $recommendations;
    }

    protected function exportCsv($items)
    {
        $csv = "Item Code,Category,Title,Status,Priority,Risk Level,Assigned To,Completed At\n";
        
        foreach ($items as $item) {
            $assignee = $item->assignee?->full_name ?? 'Unassigned';
            $completedAt = $item->completed_at ?? '';

            $csv .= "\"{$item->item_code}\",\"{$item->category}\",\"{$item->title}\",";
            $csv .= "\"{$item->status}\",\"{$item->priority}\",\"{$item->risk_level}\",";
            $csv .= "\"{$assignee}\",\"{$completedAt}\"\n";
        }
        
        return response()->json([
            'csv' => $csv,
            'filename' => 'golive_checklist_' . now()->format('Y-m-d_His') . '.csv',
        ]);
    }

    protected function exportPdf($items)
    {
        // This would use a PDF library like TCPDF or Laravel-PDF
        // For now, returning placeholder
        return response()->json([
            'message' => 'PDF export functionality would be implemented with PDF library',
            'items_count' => $items->count(),
        ]);
    }
}
