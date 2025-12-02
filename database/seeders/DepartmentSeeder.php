<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Department;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         $departments = [
            'Clinical Biochemistry',
            'Immunoassay',
            'Hematology',
            'Serology',
            'Clinical Pathology',
            'Microbiology',
            'Molecular Biology',
            'Histopathology',
            'Cytogenetics',
            'R & D',
        ];

        foreach($departments as $name){
            $identifier = $this->generateIdentifier($name);

            Department::updateOrCreate(
                ['name' => $name],
                ['identifier' => $identifier]
            );
        }
    }

    private function generateIdentifier($name)
    {
        $words = preg_split('/\s+/', strtoupper($name));

        if(count($words) == 1){
            return substr($words[0], 0, 4);
        } else if(count($words) >= 2){
            $first = substr($words[0], 0, 2);
            $second = substr($words[1], 0, 2);
            return $first.$second;
        }
        return strtoupper(substr($name,0,4));
    }
}
