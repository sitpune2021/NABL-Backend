<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\PermissionRegistrar;

use App\Services\NavigationAccessService;

use App\Models\{NavigationItem, LabUser};

class NavigationItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        $labUser = LabUser::where('user_id', $user->id)->first();
        $lab =  $labUser ? $labUser->lab_id  : 0;
        $isLabUser = !is_null($labUser);
        request()->attributes->set('isLabUser', $isLabUser);
        $items = NavigationItem::with('children')
            ->when($lab > 0 , fn ($q) => $q->forLab())
            ->when($lab == 0 , fn ($q) => $q->forMaster())
            ->whereNull('parent_id')
            ->orderBy('order')
            ->get();

        $role = $user->roles()->first(); // or by role name
        $permissions =  $role->permissions->pluck('name')
            ->filter(function ($perm) {
                return str_ends_with($perm, '.list');
            })
            ->push('home')
            ->toArray();

        $menuArray = $items->toArray();
        $filteredMenu = $this->filterMenuArray($menuArray, $permissions);

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

    public function accessModules(NavigationAccessService $service)
    {
        $ctx = $this->labContext(request());
        $isMaster =  $ctx['lab_id'] == 0  ? false : true;
        return response()->json(
            $service->getAccessModules($isMaster),
            200,
            [],
            JSON_PRETTY_PRINT
        );
    }
}



