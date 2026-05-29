<?php

namespace App\Http\Controllers;

use App\Models\PrefixConfig;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PrefixConfigController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = PrefixConfig::query();

            if ($request->filled('query')) {
                $search = strtolower($request->input('query'));
                $query->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(master_key) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(master_name) LIKE ?', ["%{$search}%"]);
                });
            }

            if ($request->filled('is_active')) {
                $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
            }

            $sortKey = $request->input('sort.key', 'id');
            $sortOrder = strtolower($request->input('sort.order', 'asc'));
            $allowedSortColumns = ['id', 'master_key', 'master_name', 'segment_count', 'created_at', 'updated_at'];

            if (in_array($sortKey, $allowedSortColumns) && in_array($sortOrder, ['asc', 'desc'])) {
                $query->orderBy($sortKey, $sortOrder);
            }

            $pageIndex = (int) $request->input('pageIndex', 1);
            $pageSize = (int) $request->input('pageSize', 10);
            $configs = $query->paginate($pageSize, ['*'], 'page', $pageIndex);
            $data = collect($configs->items())->addSerial($configs->firstItem());

            return response()->json([
                'success' => true,
                'data' => $data,
                'total' => $configs->total(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch prefix configs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules());
        $this->addConfigValidation($validator, $request);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $data = $validator->validated();
            $data['master_name'] = $data['master_name'] ?? PrefixConfig::MASTER_KEYS[$data['master_key']];
            $data = $this->cleanPayload($data);

            $config = PrefixConfig::create($data);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $config,
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create prefix config',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $config = PrefixConfig::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $config,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Prefix config not found',
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $config = PrefixConfig::findOrFail($id);
        $validator = Validator::make($request->all(), $this->rules($config->id, true));
        $this->addConfigValidation($validator, $request, $config);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $data = $validator->validated();

            if (array_key_exists('master_key', $data) && !array_key_exists('master_name', $data)) {
                $data['master_name'] = PrefixConfig::MASTER_KEYS[$data['master_key']];
            }

            $data = $this->cleanPayload($data);
            $config->update($data);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $config->fresh(),
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update prefix config',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            PrefixConfig::findOrFail($id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Prefix config deleted successfully',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Prefix config not found',
            ], 404);
        }
    }

    public function masters()
    {
        return response()->json([
            'success' => true,
            'data' => collect(PrefixConfig::MASTER_KEYS)->map(fn ($name, $key) => [
                'master_key' => $key,
                'master_name' => $name,
            ])->values(),
        ]);
    }

    public function validateValue(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'master_key' => ['required', Rule::in(array_keys(PrefixConfig::MASTER_KEYS))],
            'value' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $config = PrefixConfig::where('master_key', $request->master_key)
            ->where('is_active', true)
            ->first();

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Active prefix config not found',
            ], 404);
        }

        $errors = $config->validateValue($request->value);

        return response()->json([
            'success' => count($errors) === 0,
            'errors' => $errors,
        ], count($errors) === 0 ? 200 : 422);
    }

    public function validateName(Request $request)
    {
        $request->merge(['value' => $request->input('value', $request->input('name'))]);

        return $this->validateValue($request);
    }

    private function rules(?int $ignoreId = null, bool $isUpdate = false): array
    {
        return [
            'master_key' => [
                $isUpdate ? 'sometimes' : 'required',
                Rule::in(array_keys(PrefixConfig::MASTER_KEYS)),
                Rule::unique('prefix_configs', 'master_key')->ignore($ignoreId)->whereNull('deleted_at'),
            ],
            'master_name' => ['nullable', 'string', 'max:255'],
            'separator' => ['nullable', 'string', 'max:5'],
            'segment_count' => [$isUpdate ? 'sometimes' : 'required', 'integer', 'min:1', 'max:20'],
            'value_min_length' => ['sometimes', 'integer', 'min:1', 'max:255'],
            'value_max_length' => ['sometimes', 'integer', 'min:1', 'max:255', 'gte:value_min_length'],
            'characters_min_length' => ['sometimes', 'integer', 'min:1', 'max:255'],
            'characters_max_length' => ['sometimes', 'integer', 'min:1', 'max:255', 'gte:characters_min_length'],
            'segments' => [$isUpdate ? 'sometimes' : 'required', 'array'],
            'segments.*.type' => ['required_with:segments', Rule::in(['any', 'letters', 'numbers', 'alphanumeric'])],
            'segments.*.case' => ['required_with:segments', Rule::in(['any', 'upper', 'lower', 'title'])],
            'segments.*.min_length' => ['required_with:segments', 'integer', 'min:1', 'max:255'],
            'segments.*.max_length' => ['required_with:segments', 'integer', 'min:1', 'max:255'],
            'segments.*.starts_with' => ['required_with:segments', Rule::in(['any', 'letter', 'number'])],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function addConfigValidation($validator, Request $request, ?PrefixConfig $config = null): void
    {
        $validator->after(function ($validator) use ($request, $config) {
            $segmentCount = (int) $request->input('segment_count', $config?->segment_count ?? 0);
            $segments = $request->input('segments', $config?->segments ?? []);

            if ($segmentCount && is_array($segments) && count($segments) !== $segmentCount) {
                $validator->errors()->add('segments', 'Segments count must match segment_count.');
            }

            foreach ((array) $request->input('segments', []) as $index => $segment) {
                $min = (int) ($segment['min_length'] ?? 0);
                $max = (int) ($segment['max_length'] ?? 0);

                if ($min && $max && $max < $min) {
                    $validator->errors()->add("segments.{$index}.max_length", 'Segment max_length must be greater than or equal to min_length.');
                }
            }
        });
    }

    private function cleanPayload(array $data): array
    {
        if (array_key_exists('separator', $data) && $data['separator'] === null) {
            unset($data['separator']);
        }

        return $data;
    }
}
