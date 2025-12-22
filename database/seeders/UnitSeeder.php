<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Unit;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = [
            'g/dL',        // Hemoglobin, Total Protein
            'mg/dL',       // Glucose, Urea, Creatinine
            'mmol/L',      // Electrolytes, Glucose (SI)
            'µmol/L',      // Bilirubin, Creatinine (SI)
            'IU/L',        // Enzymes (ALT, AST, ALP)
            'U/L',         // Enzymes
            'mEq/L',       // Sodium, Potassium
            'ng/mL',       // Hormones, Tumor markers
            'pg/mL',       // Hormones
            'µg/dL',       // Iron, Cortisol
            'µg/L',        // Ferritin
            '%',           // HbA1c, Differential counts
            'cells/µL',    // WBC, Platelets
            '×10³/µL',     // CBC counts
            '×10⁶/µL',     // RBC count
            'sec',         // PT, APTT
            'ratio',       // INR
            'copies/mL',   // Viral load
            'CFU/mL',      // Culture reports
            'HPF',         // Urine microscopy
            'LPF',         // Urine microscopy
            'Positive',
            'Negative'
        ];


        foreach($units as $name){
            Unit::updateOrCreate(
                ['name' => $name]
            );
        }
    }
}
