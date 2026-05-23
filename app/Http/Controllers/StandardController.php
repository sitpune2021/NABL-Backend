<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Standard;
use App\Models\LabUser;
use App\Models\Clause;
use App\Models\Lab;

class StandardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            $ctx = $this->labContext($request);
            $ownerType = $ctx['owner_type'];
            $ownerId   = $ctx['owner_id'];
            $query = Standard::with([
                'clauses' => function ($q) use ($ownerType, $ownerId) {
                    $q->whereNull('parent_id')
                    ->orderBy('sort_order')
                    ->with($this->clauseWithRelations($ownerType, $ownerId));
                }
            ]);

            if ($ctx['lab_id'] != 0 && $lab = Lab::find($ownerId)) {
                $query->where('id', $lab->standard_id);
            }

            // Search by name or UUID
            if ($request->filled('query')) {
                $search = $request->input('query');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('uuid', 'LIKE', "%{$search}%")
                    ->orWhereHas('clauses', function ($q2) use ($search) {
                        $q2->where('title', 'LIKE', "%{$search}%");
                    });
                });
            }

            // Filter by name
            if ($request->filled('name')) {
                $query->where('name', 'LIKE', '%' . $request->input('name') . '%');
            }

            // Filter by status
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            // Filter by publish date range
            if ($request->filled('publish_from')) {
                $query->whereDate('created_at', '>=', $request->input('publish_from'));
            }
            if ($request->filled('publish_to')) {
                $query->whereDate('created_at', '<=', $request->input('publish_to'));
            }

            // Sorting
            $sortKey = $request->input('sort.key', 'id');
            $sortOrder = $request->input('sort.order', 'asc');
            $allowedSortColumns = ['id', 'name', 'uuid', 'status', 'created_at', 'updated_at'];
            $allowedSortOrder = ['asc', 'desc'];

            if (in_array($sortKey, $allowedSortColumns) && in_array(strtolower($sortOrder), $allowedSortOrder)) {
                $query->orderBy($sortKey, $sortOrder);
            }

            // Pagination
            $pageIndex = (int) $request->input('pageIndex', 1);
            $pageSize  = (int) $request->input('pageSize', 10);

            $standards = $query->paginate($pageSize, ['*'], 'page', $pageIndex);

            $data = collect($standards->items())->values()->map(function ($standard, $index) use ($ownerType, $ownerId, $standards) {

                // ✅ SERIAL
                $serial = $standards->firstItem() + $index;
                $standard->sr = str_pad($serial, 4, '0', STR_PAD_LEFT);

                // existing logic
                $standard->is_document_link = $standard->clauseDocumentLinks()
                    ->when($ownerType === 'super_admin', fn($q) => $q->superAdmin())
                    ->when($ownerType === 'lab', fn($q) => $q->forLab($ownerId))
                    ->exists();

                return $standard;
            });

            return response()->json([
                'status' => true,
                'data' => $data,
                'total' => $standards->total()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch standards',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'clauses' => 'required|array',
        ]);

        DB::beginTransaction();

        try {
            // Create Standard (version 1.0 for first time)
            $standard = Standard::create([
                'uuid' => (string) Str::uuid(),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'version_major' => 1,
                'version_minor' => 0,
                'changes_type' => 'minor',
                'status' => 'draft',
                'is_current' => true,
                'created_by' => auth()->id()
            ]);

            // Recursive store clauses
            foreach ($data['clauses'] as $clause) {
                $this->storeClause($clause, $standard->id, null);
            }

            DB::commit();

            return response()->json($standard->load('clauses.children'), 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to create standard: '.$e->getMessage(), ['stack' => $e->getTraceAsString()]);
            return response()->json([
                'message' => 'Failed to create standard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function storeClause($clause, $standardId, $parentId)
    {
        $newClause = Clause::create([
            'standard_id' => $standardId,
            'parent_id' => $parentId,
            'title' => $clause['title'] ?? "",
            'message' => $clause['message'] ?? null,
            'note' => $clause['note'] ?? false,
            'is_child' => isset($clause['children']) && count($clause['children']) > 0,
            'numbering_type' => $clause['numbering_type'] ?? 'numerical',
            'numbering_value' => $clause['numbering_value'] ?? null,
            'sort_order' => $clause['sortOrder'] ?? 0
        ]);

        // Store child clauses recursively
        if (isset($clause['children']) && is_array($clause['children'])) {
            foreach ($clause['children'] as $child) {
                $this->storeClause($child, $standardId, $newClause->id);
            }
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
          $ctx = $this->labContext($request);

        $ownerType = $ctx['owner_type'];
        $ownerId   = $ctx['owner_id'];

        $standard =  Standard::with([
            'clauses' => function ($q) use ($ownerType, $ownerId) {
                $q->whereNull('parent_id')
                ->orderBy('id')
                ->with($this->clauseWithRelations($ownerType, $ownerId));
            }
        ])->findOrFail($id);

          return response()->json([
                'success' => true,
                'data' => $standard
            ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $data = $request->validate([
            'uuid' => 'required|uuid',
            'name' => 'required|string',
            'clauses' => 'required|array',
        ]);

        DB::beginTransaction();

        try {
            $standard = Standard::findOrFail($id);

            // if published move back to draft
            if ($standard->status === 'published') {
                $standard->status = 'draft';
            }

            $standard->uuid = $data['uuid'];
            $standard->name = $data['name'];
            $standard->description = $data['description'] ?? $standard->description;
            // $standard->updated_by = auth()->id();
            $standard->save();

            $incomingIds = [];

            $collectIds = function ($clauses) use (&$collectIds, &$incomingIds) {

                foreach ($clauses as $clause) {

                    if (!empty($clause['id'])) {
                        $incomingIds[] = $clause['id'];
                    }

                    if (!empty($clause['children']) && is_array($clause['children'])) {
                        $collectIds($clause['children']);
                    }
                }
            };

            $collectIds($data['clauses']);

            Clause::where('standard_id', $standard->id)
                ->whereNotIn('id', $incomingIds)
                ->delete();
                
            foreach ($data['clauses'] as $clause) {
                $this->updateClause($clause, $standard->id, null);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $standard->load('clauses.children')
            ], 200);

        } catch (\Exception $e) {

            DB::rollBack();

            \Log::error(
                'Failed to update standard: ' . $e->getMessage(),
                ['stack' => $e->getTraceAsString()]
            );

            return response()->json([
                'success' => false,
                'message' => 'Failed to update standard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    private function clauseWithRelations($ownerType, $ownerId)
    {
        return [
            'documentLinks' => function ($q) use ($ownerType, $ownerId) {

                if ($ownerType === 'super_admin') {
                    $q->superAdmin();
                } else {
                    $q->forLab($ownerId);
                }
                $q->with('document');
            },

            'children' => function ($q) use ($ownerType, $ownerId) {
                $q->orderBy('sort_order')
                ->with($this->clauseWithRelations($ownerType, $ownerId)); // 🔁 RECURSION
            }
        ];
    }

    private function updateClause($clause, $standardId, $parentId)
    {
        $existing = null;

        if (isset($clause['id'])) {
            $existing = Clause::where('id', $clause['id'])
                ->where('standard_id', $standardId)
                ->first();
        }

        if ($existing) {
            $existing->parent_id = $parentId;
            $existing->title = $clause['title'] ?? $existing->title;
            $existing->message = $clause['message'] ?? $existing->message;
            $existing->note = $clause['note'] ?? $existing->note;
            $existing->is_child = isset($clause['children']) && count($clause['children']) > 0;
            $existing->numbering_type = $clause['numbering_type'] ?? $existing->numbering_type;
            $existing->numbering_value = $clause['numbering_value'] ?? $existing->numbering_value;
            $existing->sort_order = $clause['sortOrder'] ?? $existing->sort_order;
            $existing->save();

            $clauseId = $existing->id;
        } else {
            $new = Clause::create([
                'standard_id' => $standardId,
                'parent_id' => $parentId,
                'title' => $clause['title'] ?? "",
                'message' => $clause['message'] ?? null,
                'note' => $clause['note'] ?? false,
                'is_child' => isset($clause['children']) && count($clause['children']) > 0,
                'numbering_type' => $clause['numbering_type'] ?? 'numerical',
                'numbering_value' => $clause['numbering_value'] ?? null,
                'sort_order' => $clause['sortOrder'] ?? 0
            ]);

            $clauseId = $new->id;
        }
        \Log::info("clauseId: " . $clauseId);
        if (isset($clause['children']) && is_array($clause['children'])) {
            foreach ($clause['children'] as $child) {
                $this->updateClause($child, $standardId, $clauseId);
            }
        }
    }
}