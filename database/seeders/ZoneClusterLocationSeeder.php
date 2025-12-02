<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Zone;
use App\Models\Cluster;
use App\Models\Location;

class ZoneClusterLocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    private function makeCode($name)
    {
        $clean = strtoupper(str_replace(' ', '', $name));
        $len = strlen($clean);

        if ($len <= 2) {
            return $clean;             // GO, UP, TN
        }
        if ($len == 3) {
            return $clean;             // PUN, GOA
        }

        return substr($clean, 0, 4);    // MAHA, GUJA, KERA
    }


    public function run(): void
    {
        $zoneNames = ['East', 'West', 'North', 'South'];
        $zones = [];

        foreach ($zoneNames as $name) {
            $zones[$name] = Zone::create([
                'name'       => $name,
                'identifier' => $this->makeCode($name), 
            ]);
        }

        $clusterNames = [
            'East'  => ['Maharashtra', 'Goa'],
            'West'  => ['Gujarat', 'Rajasthan'],
            'North' => ['Punjab', 'UP'],
            'South' => ['Kerala', 'TN'],
        ];

        $clusters = [];

        foreach ($clusterNames as $zoneName => $clusterList) {
            $zone = $zones[$zoneName];

            foreach ($clusterList as $clusterName) {
                $clusterCode = $this->makeCode($clusterName);            

                $identifier = $zone->identifier . '-' . $clusterCode;   

                $clusters[$clusterName] = Cluster::create([
                    'zone_id'    => $zone->id,
                    'name'       => $clusterName,
                    'identifier' => $identifier,
                ]);
            }
        }

        $locationNames = [
            'Maharashtra' => ['Pune', 'Mumbai'],
            'Goa'         => ['Panjim', 'Margao'],
            'Gujarat'     => ['Surat', 'Rajkot'],
            'Punjab'      => ['Ludhiana', 'Amritsar'],
            'Kerala'      => ['Kochi', 'Kollam'],
        ];

        foreach ($locationNames as $clusterName => $locationList) {
            $cluster = $clusters[$clusterName];

            foreach ($locationList as $loc) {
                $short = $this->makeCode($loc);                       

                $identifier = $cluster->identifier . '-' . $short;    

                Location::create([
                    'cluster_id' => $cluster->id,
                    'name'       => $loc,
                    'short_name' => $short,
                    'identifier' => $identifier,
                ]);
            }
        }
    }
}
