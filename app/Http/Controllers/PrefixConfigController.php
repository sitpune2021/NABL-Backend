<?php

namespace App\Http\Controllers;

use App\Models\PrefixConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PrefixConfigController extends Controller
{
    public function index()
    {
        return PrefixConfig::latest()->get( );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'prefix_master' => 'required|string',
            'type' => 'required|string',
            'min_length' => 'required|integer|min:1|max:10',
            'max_length' => 'required|integer|min:1|max:10',
        ]);

        return PrefixConfig::create($validated);
    }

    public function show($id)
    {
        return PrefixConfig::findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $prefix = PrefixConfig::findOrFail($id);

        $validated = $request->validate([
            'prefix_master' => 'required|string',
            'type' => 'required|string',
            'min_length' => 'required|integer|min:1|max:10',
            'max_length' => 'required|integer|min:1|max:10',
        ]);

        $prefix->update($validated);

        return $prefix;
    }

    public function destroy($id)
    {
        PrefixConfig::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Deleted successfully',
        ]);
    }

    public function getPrefixMasters()
    {
        $tables = Schema::getTableListing();

        $masters = [];

        foreach ($tables as $table) {

            $columns = Schema::getColumnListing($table);

            if (in_array('identifier', $columns)) {

                $tableName = str_replace('public.', '', $table);

                $masters[] = [
                    'label' => Str::title(str_replace('_', ' ', $tableName)),
                    'value' => $tableName,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $masters,
        ]);
    }
}
