<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NavigationItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class NavigationItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();

        $items = NavigationItem::with('children')
            ->whereNull('parent_id')
            ->orderBy('order')
            ->get();

        if ($user->is_super_admin) {
            $cleanedItems = $this->cleanPaths($items);
            return response()->json($cleanedItems);
        }

        // Permissions for non-super-admins
        $permissions = $user->getAllPermissions()
            ->pluck('name')
            ->filter(function ($perm) {
                return str_ends_with($perm, '.list');
            })
            ->push('home')
            ->toArray();

        // dd($permissions);
        // Filter menu array
        $menuArray = $items->toArray();
        $filteredMenu = $this->filterMenuArray($menuArray, $permissions);

        // Clean paths recursively
        $cleanedItems = $this->cleanPaths($filteredMenu, false); // false = it's array now

        return response()->json($cleanedItems);
    }

    /**
     * Filter menu array by permissions
     */
    private function filterMenuArray(array $items, array $permissions): array
    {
        $filtered = [];
        foreach ($items as $item) {
            if (!empty($item['children'])) {
                $item['children'] = $this->filterMenuArray($item['children'], $permissions);
            }
            $hasPermission =
                in_array($item['key'], $permissions)   // direct permission
                || !empty($item['children']);          // keep parent if it has allowed children

            if ($hasPermission) {
                $filtered[] = $item;
            }
        }

        return $filtered;
    }


    /**
     * Clean paths for both Collections and Arrays recursively
     */
    private function cleanPaths($items, bool $isCollection = true)
    {
        if ($isCollection) {
            return $items->map(function ($item) {
                $item->path = is_string($item->path) ? str_replace('\\/', '/', $item->path) : $item->path;

                if ($item->relationLoaded('children')) {
                    $item->children = $this->cleanPaths($item->children);
                }

                return $item;
            });
        } else {
            return array_map(function ($item) {
                $item['path'] = is_string($item['path']) ? str_replace('\\/', '/', $item['path']) : $item['path'];

                if (!empty($item['children'])) {
                    $item['children'] = $this->cleanPaths($item['children'], false);
                }

                return $item;
            }, $items);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $items = $request->all(); // Accept full array of navigation items
        DB::beginTransaction();
        try {
            foreach ($items as $item) {
                $this->storeNavigationItem($item);
            }

            DB::commit();
            return response()->json(['message' => 'Navigation items stored successfully'], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    protected function storeNavigationItem(array $data, $parentId = null)
    {
        $validated = validator($data, [
            'key' => 'required|string|unique:navigation_items,key',
            'path' => 'nullable|string',
            'title' => 'required|string',
            'translateKey' => 'required|string',
            'icon' => 'required|string',
            'type' => 'nullable|in:title,collapse,item',
            'is_external_link' => 'boolean|nullable',
            'authority' => 'array|nullable',
            'description' => 'string|nullable',
            'description_key' => 'string|nullable',
            'layout' => 'nullable|in:default,columns,tabs',
            'show_column_title' => 'boolean|nullable',
            'columns' => 'nullable|integer|between:1,5',
            'order' => 'integer|nullable',
        ])->validate();

        // Map camelCase to snake_case
        $itemData = [
            'key' => $validated['key'],
            'path' => $validated['path'] ?? null,
            'title' => $validated['title'],
            'translate_key' => $validated['translateKey'],
            'icon' => $validated['icon'],
            'type' => $validated['type'] ?? 'item',
            'is_external_link' => $validated['is_external_link'] ?? false,
            'authority' => $validated['authority'] ?? [],
            'description' => $validated['description'] ?? null,
            'description_key' => $validated['description_key'] ?? null,
            'parent_id' => $parentId,
            'layout' => $validated['layout'] ?? null,
            'show_column_title' => $validated['show_column_title'] ?? false,
            'columns' => $validated['columns'] ?? null,
            'order' => $validated['order'] ?? 0,
        ];

        $item = NavigationItem::create($itemData);

        // Recursively store children if exist (e.g. 'subMenu')
        if (isset($data['subMenu']) && is_array($data['subMenu'])) {
            foreach ($data['subMenu'] as $child) {
                $this->storeNavigationItem($child, $item->id);
            }
        }

        return $item;
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return response()->json($navigationItem->load('children'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
            $data = $request->validate([
            'path' => 'nullable|string',
            'title' => 'string',
            'translate_key' => 'string',
            'icon' => 'string',
            'type' => 'in:title,collapse,item',
            'is_external_link' => 'boolean|nullable',
            'authority' => 'array|nullable',
            'description' => 'string|nullable',
            'description_key' => 'string|nullable',
            'parent_id' => 'nullable|exists:navigation_items,id',
            'layout' => 'nullable|in:default,columns,tabs',
            'show_column_title' => 'boolean|nullable',
            'columns' => 'nullable|integer|between:1,5',
            'order' => 'integer|nullable',
        ]);

        $navigationItem->update($data);

        return response()->json($navigationItem);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function accessModules()
    {
        $items = NavigationItem::with('children')->whereNull('parent_id')->orderBy('order')->get();

        $modules = $this->mapToAccessModules($items);

       return response()->json($modules, 200, [], JSON_PRETTY_PRINT);
    }

    private function mapToAccessModules($items)
    {
        $modules = [];

        foreach ($items as $item) {
            if ($item->relationLoaded('children') && $item->children->isNotEmpty()) {
                foreach ($item->children as $child) {
                    [$group, $module] = explode('.', str_replace('.list', '', $child->key));
                    $modules[$group][] = [
                        'id' => $module,
                        'key' => str_replace('.list', '', $child->key),
                        'name' => $child->title . ' Management',
                        'description' => 'Access control for ' . strtolower($child->title) . ' operations',
                        'linkedMenuKeys' => [$child->key],
                        'accessor' => $this->getDefaultAccessors($child->key),
                    ];
                }
            }
        }

        return $modules;
    }

    private function getDefaultAccessors($key)
    {
        if ($key === 'masters.document.list') {
            return [
                ['label' => 'Read', 'value' => 'list'],
                ['label' => 'Write', 'value' => 'write'],
                ['label' => 'Data Entry', 'value' => 'data-entry'],
                ['label' => 'Data Review', 'value' => 'data-review'],
                ['label' => 'Delete', 'value' => 'delete'],
            ];
        }

        return [
            ['label' => 'Read', 'value' => 'list'],
            ['label' => 'Write', 'value' => 'write'],
            ['label' => 'Delete', 'value' => 'delete'],
        ];
    }
}



