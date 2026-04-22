<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\{LabUser, Instrument};

class InstrumentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $ctx = $this->labContext($request);
            $query = Instrument::query()->where('status', 'completed');

            if ($ctx['lab_id'] == 0) {
                $query->with('lab')->SuperAdmin();
            } else {
                $query->ForLab($ctx['lab_id']);
            }
            if ($request->filled('query')) {
                $search = strtolower($request->input('query'));
                $query->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(short_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(serial_no) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(manufacturer) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(identifier) LIKE ?', ["%{$search}%"]);
                });
            }

            // Sorting
            $sortKey = $request->input('sort.key', 'id'); // default sort by id
            $sortOrder = $request->input('sort.order', 'asc'); // default ascending

            $allowedSortColumns = ['id', 'name','short_name','manufacturer', 'identifier','serial_no', 'created_at', 'updated_at'];
            $allowedSortOrder = ['asc', 'desc'];

            if (in_array($sortKey, $allowedSortColumns) && in_array(strtolower($sortOrder), $allowedSortOrder)) {
                $query->orderBy($sortKey, $sortOrder);
            }

            // Pagination
            $pageIndex = (int) $request->input('pageIndex', 1);
            $pageSize = (int) $request->input('pageSize', 10);

            $instruments = $query->paginate($pageSize, ['*'], 'page', $pageIndex);


            return response()->json([
                'status' => true,
                'data' => $instruments->items(),
                'total' => $instruments->total()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch instruments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $ctx = $this->labContext($request);
        $validator = Validator::make($request->all(), [
            'name' => ['required'],
            'identifier' => ['required'],
            'short_name' => ['required'],
            'serial_no' => ['required'],
            'manufacturer' => ['required'],
            'vendor_name' => ['required'],

            'parent_id' => [
                'nullable',
                'exists:instruments,id',
                fn($attr, $value, $fail) =>
                $value && $ctx['owner_type'] !== 'lab'
                    ? $fail('Only lab users can override master instruments')
                    : null
            ],

            'name' => [
                'required',
                Rule::unique('instruments')->where(
                    fn($q) =>
                    $q->where('owner_type', $ctx['owner_type'])
                        ->where('owner_id', $ctx['owner_id'])
                        ->whereNull('deleted_at')
                ),
            ],

            'identifier' => [
                'required',
                Rule::unique('instruments')->where(
                    fn($q) =>
                    $q->where('owner_type', $ctx['owner_type'])
                        ->where('owner_id', $ctx['owner_id'])
                        ->whereNull('deleted_at')
                ),
            ],

        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $instrument = Instrument::create([
                'parent_id' => $request->parent_id,
                'name' => $request->name,
                'identifier' => $request->identifier,
                'short_name' => $request->short_name,
                'serial_no' => $request->serial_no,
                'manufacturer' => $request->manufacturer,
                'vendor_name' => $request->vendor_name,
                'owner_type' => $ctx['owner_type'],
                'owner_id' => $ctx['owner_id'],
                'status' => 'completed',
            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $instrument
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create instrument',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $ctx = $this->labContext(request());
            $instrument = Instrument::accessible($ctx['lab_id'])->findOrFail($id);
            return response()->json([
                'success' => true,
                'data' => $instrument
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Instrument not found'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch instrument',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $instrument = Instrument::findOrFail($id);
        $validator = Validator::make($request->all(), [

            'name' => [
                'sometimes',
                Rule::unique('instruments')->ignore($id)->where(
                    fn($q) =>
                    $q->where('owner_type', $instrument->owner_type)
                        ->where('owner_id', $instrument->owner_id)
                        ->whereNull('deleted_at')
                ),
            ],

            'identifier' => [
                'sometimes',
                Rule::unique('instruments')->ignore($id)->where(
                    fn($q) =>
                    $q->where('owner_type', $instrument->owner_type)
                        ->where('owner_id', $instrument->owner_id)
                        ->whereNull('deleted_at')
                ),
            ],

        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {

            $instrument->update($request->only([
                'name',
                'identifier',
                'short_name',
                'serial_no',
                'manufacturer',
                'vendor_name'
            ]));
            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $instrument
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update instrument',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        DB::beginTransaction();
        try {
            $instrument = Instrument::findOrFail($id);
            if ($instrument->is_master) {

                return response()->json([
                    'success' => false,
                    'message' => 'Master instrument cannot be deleted',
                ], 403);
            }
            $instrument->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Instrument deleted successfully'
            ], 200);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Instrument not found'
            ], 404);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete instrument',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Lab instruments for master sync
     */
    public function labMasterInstruments(Request $request)
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:labs,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'key' => ['nullable', 'string', 'in:all,master']
        ]);

        $labId = $validated['id'];
        $key = $validated['key'] ?? null;

        $query = Instrument::query()
            ->with('appendedMaster')
            ->select([
                'id',
                'name',
                'identifier',
                'owner_id',
                'owner_type',
                'parent_id',
                'created_at'
            ])
            ->where('owner_type', 'lab')
            ->where('owner_id', $labId)
            ->whereNull('parent_id');

        if (!empty($validated['start_date']) && !empty($validated['end_date'])) {

            $query->whereBetween('created_at', [
                $validated['start_date'] . ' 00:00:00',
                $validated['end_date'] . ' 23:59:59'
            ]);
        }

        if ($key !== 'all') {
            $query->whereDoesntHave('appendedMaster');
        }

        $instruments = $query
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $instruments
        ]);
    }


    /**
     * Append lab instrument to master
     */
    public function appendLabInstrumentToMaster(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lab_instrument_id' => ['required', 'exists:instruments,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $labInstrument = Instrument::where('id', $request->lab_instrument_id)
            ->where('owner_type', 'lab')
            ->whereNull('parent_id')
            ->firstOrFail();

        $alreadyExists = Instrument::where('owner_type', 'super_admin')
            ->where('parent_id', $labInstrument->id)
            ->exists();

        if ($alreadyExists) {
            return response()->json([
                'success' => false,
                'message' => 'Instrument already synced',
            ], 409);
        }

        DB::beginTransaction();

        try {

            $masterInstrument = Instrument::create([
                'parent_id' => $labInstrument->id,
                'name' => $labInstrument->name,
                'identifier' => $labInstrument->identifier,
                'short_name' => $labInstrument->short_name,
                'serial_no' => $labInstrument->serial_no,
                'manufacturer' => $labInstrument->manufacturer,
                'vendor_name' => $labInstrument->vendor_name,
                'owner_type' => 'super_admin',
                'owner_id' => 0,
                'status' => 'pending',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $masterInstrument,
            ], 201);
        } catch (Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to append instrument',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function pendingInstruments()
    {
        $instruments = Instrument::with('lab')
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $instruments
        ]);
    }


    public function approveInstruments(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['exists:instruments,id']
        ]);

        Instrument::whereIn('id', $validated['ids'])
            ->update(['status' => 'completed']);

        return response()->json([
            'success' => true,
        ]);
    }
}
