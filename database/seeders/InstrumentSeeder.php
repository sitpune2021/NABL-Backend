<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Instrument;

class InstrumentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         $instruments = [
            [
                'name' => 'Metal Plate',
                'short_name' => 'Metal Plat',
                'manufacturer' => 'Generic',
                'serial_no' => 'SN-MTP-001',
                'identifier' => 'MTP',
            ],
            [
                'name' => 'Hematology Analyzer',
                'short_name' => 'Hematology',
                'manufacturer' => 'Sysmex',
                'serial_no' => 'SN-HMA-002',
                'identifier' => 'HMA',
            ],
            [
                'name' => 'Biochemistry Analyzer',
                'short_name' => 'Biochem',
                'manufacturer' => 'Roche',
                'serial_no' => 'SN-BCA-003',
                'identifier' => 'BCA',
            ],
            [
                'name' => 'Semi Auto Analyzer',
                'short_name' => 'Semi Auto',
                'manufacturer' => 'Erba',
                'serial_no' => 'SN-SAA-004',
                'identifier' => 'SAA',
            ],
            [
                'name' => 'Centrifuge Machine',
                'short_name' => 'Centrifuge',
                'manufacturer' => 'Remi',
                'serial_no' => 'SN-CFG-005',
                'identifier' => 'CFG',
            ],
            [
                'name' => 'Microscope',
                'short_name' => 'Microscope',
                'manufacturer' => 'Olympus',
                'serial_no' => 'SN-MSC-006',
                'identifier' => 'MSC',
            ],
            [
                'name' => 'ELISA Reader',
                'short_name' => 'ELISA',
                'manufacturer' => 'Bio-Rad',
                'serial_no' => 'SN-ELS-007',
                'identifier' => 'ELS',
            ],
            [
                'name' => 'Urine Analyzer',
                'short_name' => 'Urine',
                'manufacturer' => 'Dirui',
                'serial_no' => 'SN-URA-008',
                'identifier' => 'URA',
            ],
            [
                'name' => 'Incubator',
                'short_name' => 'Incubator',
                'manufacturer' => 'Thermo',
                'serial_no' => 'SN-INC-009',
                'identifier' => 'INC',
            ],
            [
                'name' => 'Autoclave',
                'short_name' => 'Autoclave',
                'manufacturer' => 'Tuttnauer',
                'serial_no' => 'SN-AUT-010',
                'identifier' => 'AUT',
            ],
        ];

        foreach ($instruments as $instrument) {
            Instrument::create($instrument);
        }
    }
}
