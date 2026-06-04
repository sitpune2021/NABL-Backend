<?php

namespace Database\Seeders;

use App\Models\Clause;
use App\Models\Standard;
use App\Models\User;
use Illuminate\Database\Seeder;

class StandardSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::where('email', 'superadmin@example.com')->first();

        if (! $superAdmin) {
            $this->command->warn('StandardSeeder skipped: super admin user not found.');
            return;
        }

        $standards = [
            [
                'uuid' => 'cd8907fc-ca40-49d6-a13b-d6a5899e1947',
                'name' => '1STEP_NABL_MELT_V1.1',
                'description' => null,
                'version_major' => 1,
                'version_minor' => 0,
                'changes_type' => 'minor',
                'status' => 'draft',
                'is_current' => true,
                'created_by' => $superAdmin->id,
                'clauses' => [
                    [
                        'title' => 'Application Annexure',
                        'message' => 'Application Annexure',
                        'note' => true,
                        'numbering_type' => 'numerical',
                        'numbering_value' => '1',
                        'sort_order' => 0,
                        'children' => [
                            [
                                'title' => 'Laboratory Profile',
                                'message' => "Refer Excel and Fill data and upload.\nRequired Details\nName of laboratory(Full legal / operating name)\nCountry\nState / Province\nDistrict\nFull laboratory address\nPin code\nMobile number (whom NABL can contact)\nEmail ID (whom NABL can contact)\nNACO ICTC laboratory status\tYes / No\nType of legal entity\tProprietorship / Partnership / LLP / Company / Society / Trust / Government\nTechnical Head / Lab Manager\tName and designation\nAccredited PT program participation Yes/No",
                                'note' => true,
                                'numbering_type' => 'numerical',
                                'numbering_value' => '1',
                                'sort_order' => 0,
                            ],
                            [
                                'title' => 'Legal Identity Documents',
                                'message' => "Refer Excel and Fill data and upload according to legal document checklist.\nProvide All legal documents. (As applicable)\nCancelled Cheque\nRent agreement/purchase\nMPCB certificate\nBMW certificate\nPathologist Degree certificate\nPathologist medical council registration certificate\nBank passbook / account statement and PAN of CAB\nRegistration certificate under Partnership\nRegistration certificate under LLP Act\nRegistration certificate under Companies Act\nSociety / Trust\tSociety Registration Act certificate / Indian Trusts Act registration\nGovernment: Gazette / Government notification / self-declaration on letterhead by Head of Organization",
                                'note' => true,
                                'numbering_type' => 'numerical',
                                'numbering_value' => '2',
                                'sort_order' => 0,
                            ],
                            [
                                'title' => 'Test Scope',
                                'message' => "Check Excel and fill and upload data\t\nTest Scope – PT Provider, Name, Discipline, Sample, Test Method, Range of Detection, Unit",
                                'note' => true,
                                'numbering_type' => 'numerical',
                                'numbering_value' => '3',
                                'sort_order' => 0,
                            ],
                            [
                                'title' => 'Equipment Details',
                                'message' => "Check Excel and fill and upload data\nName of equipment, Model, Serial No, Date of Calibration, Calibration Due On, Calibrated By, Calibration certificate of equipment (Available?), Range",
                                'note' => true,
                                'numbering_type' => 'numerical',
                                'numbering_value' => '4',
                                'sort_order' => 0,
                            ],
                            [
                                'title' => 'PT / EQAS Requirements',
                                'message' => "Check Excel and fill and upload data\nDate of issue of PT report, Is result satisfactory, Upload report",
                                'note' => true,
                                'numbering_type' => 'numerical',
                                'numbering_value' => '5',
                                'sort_order' => 0,
                            ],
                            [
                                'title' => 'Staff (Employee) Details',
                                'message' => "Check Excel and fill and upload data\nName, Designation, Academic and Professional Qualifications, Experience related to present work (in years), Department",
                                'note' => true,
                                'numbering_type' => 'numerical',
                                'numbering_value' => '6',
                                'sort_order' => 0,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'uuid' => '7853f07e-e75f-4772-9158-175e7b9fe0cc',
                'name' => '1STEP_NABL_MELT_V1.0',
                'description' => null,
                'version_major' => 1,
                'version_minor' => 0,
                'changes_type' => 'minor',
                'status' => 'draft',
                'is_current' => true,
                'created_by' => $superAdmin->id,
                'clauses' => [
                    [
                        'title' => 'Application Annexure',
                        'message' => 'Application Annexure',
                        'note' => true,
                        'numbering_type' => 'numerical',
                        'numbering_value' => '1',
                        'sort_order' => 0,
                        'children' => [
                            [
                                'title' => 'Laboratory Profile',
                                'message' => "Refer Excel and Fill data and upload.\nRequired Details\nName of laboratory(Full legal / operating name)\nCountry\nState / Province\nDistrict\nFull laboratory address\nPin code\nMobile number (whom NABL can contact)\nEmail ID (whom NABL can contact)\nNACO ICTC laboratory status\tYes / No\nType of legal entity\tProprietorship / Partnership / LLP / Company / Society / Trust / Government\nTechnical Head / Lab Manager\tName and designation\nAccredited PT program participation Yes/No",
                                'note' => true,
                                'numbering_type' => 'numerical',
                                'numbering_value' => '1',
                                'sort_order' => 0,
                            ],
                            [
                                'title' => 'Legal Identity Documents',
                                'message' => "Refer Excel and Fill data and upload according to legal document checklist.\nProvide All legal documents. (As applicable)\nCancelled Cheque\nRent agreement/purchase\nMPCB certificate\nBMW certificate\nPathologist Degree certificate\nPathologist medical council registration certificate\nBank passbook / account statement and PAN of CAB\nRegistration certificate under Partnership\nRegistration certificate under LLP Act\nRegistration certificate under Companies Act\nSociety / Trust\tSociety Registration Act certificate / Indian Trusts Act registration\nGovernment: Gazette / Government notification / self-declaration on letterhead by Head of Organization",
                                'note' => true,
                                'numbering_type' => 'numerical',
                                'numbering_value' => '2',
                                'sort_order' => 0,
                            ],
                            [
                                'title' => 'Test Scope',
                                'message' => "Check Excel and fill and upload data\t\nTest Scope – PT Provider, Name, Discipline, Sample, Test Method, Range of Detection, Unit",
                                'note' => true,
                                'numbering_type' => 'numerical',
                                'numbering_value' => '3',
                                'sort_order' => 0,
                            ],
                            [
                                'title' => 'Equipment Details',
                                'message' => "Check Excel and fill and upload data\nName of equipment, Model, Serial No, Date of Calibration, Calibration Due On, Calibrated By, Calibration certificate of equipment (Available?), Range",
                                'note' => true,
                                'numbering_type' => 'numerical',
                                'numbering_value' => '4',
                                'sort_order' => 0,
                            ],
                            [
                                'title' => 'PT / EQAS Requirements',
                                'message' => "Check Excel and fill and upload data\nDate of issue of PT report, Is result satisfactory, Upload report",
                                'note' => true,
                                'numbering_type' => 'numerical',
                                'numbering_value' => '5',
                                'sort_order' => 0,
                            ],
                            [
                                'title' => 'Staff (Employee) Details',
                                'message' => "Check Excel and fill and upload data\nName, Designation, Academic and Professional Qualifications, Experience related to present work (in years), Department",
                                'note' => true,
                                'numbering_type' => 'numerical',
                                'numbering_value' => '6',
                                'sort_order' => 0,
                            ],
                        ],
                    ],
                    [
                        'title' => 'Application Requirements',
                        'message' => 'Application Requirements',
                        'note' => true,
                        'numbering_type' => 'numerical',
                        'numbering_value' => '2',
                        'sort_order' => 0,
                        'children' => [
                            [
                                'title' => 'Signage',
                                'message' => 'Signage',
                                'note' => true,
                                'numbering_type' => 'numerical',
                                'numbering_value' => '1',
                                'sort_order' => 0,
                                'children' => [
                                    [
                                        'title' => 'Laboratory display board outside or at entrance',
                                        'message' => 'Photo of display board with Lab Name, Logo, Timing and Pathologist Name and Degree',
                                        'note' => true,
                                        'numbering_type' => 'numerical',
                                        'numbering_value' => '1',
                                        'sort_order' => 0,
                                    ],
                                    [
                                        'title' => 'Name of person-in-charge with qualification',
                                        'message' => 'Photo of qualification certificate and medical council registration certificates displayed at reception',
                                        'note' => true,
                                        'numbering_type' => 'numerical',
                                        'numbering_value' => '2',
                                        'sort_order' => 0,
                                    ],
                                    [
                                        'title' => 'Fee structure displayed separately',
                                        'message' => 'Photo of Fee chart showing investigation type and charges for routine tests displayed at reception',
                                        'note' => true,
                                        'numbering_type' => 'numerical',
                                        'numbering_value' => '3',
                                        'sort_order' => 0,
                                    ],
                                ],
                            ],
                            [
                                'title' => 'Hygiene and Safety',
                                'message' => 'Hygiene and Safety',
                                'note' => true,
                                'numbering_type' => 'numerical',
                                'numbering_value' => '2',
                                'sort_order' => 0,
                                'children' => [
                                    [
                                        'title' => 'General cleanliness (Dust-free and Good Housekeeping)',
                                        'message' => "Photographs of:\n1. Lab area\n2. Biomedical waste bins\n3. Housekeeping log\n4. Daily cleaning record",
                                        'note' => true,
                                        'numbering_type' => 'numerical',
                                        'numbering_value' => '1',
                                        'sort_order' => 0,
                                    ],
                                    [
                                        'title' => 'Universal standard safety precautions',
                                        'message' => "Photograph of:\n1. Lab technician wearing PPE (lab coat, gloves, head mask, mask, goggles, close shoe\n2. Biomedical waste bins\n3. Biomedical waste pickup record",
                                        'note' => true,
                                        'numbering_type' => 'numerical',
                                        'numbering_value' => '2',
                                        'sort_order' => 0,
                                    ],
                                ],
                            ],
                            [
                                'title' => 'Space Requirements',
                                'message' => 'Space Requirements',
                                'note' => true,
                                'numbering_type' => 'numerical',
                                'numbering_value' => '3',
                                'sort_order' => 0,
                                'children' => [
                                    [
                                        'title' => 'Registration, waiting space, public utilities, safe drinking water etc',
                                        'message' => "Photograph of:\n1. Reception\n2. Registration area/PC\n3. Waiting area\n4. Patient washroom outside\n5. Patient washroom inside\n6. Drinking water\n7. Washroom cleaning record (FF)\n",
                                        'note' => true,
                                        'numbering_type' => 'numerical',
                                        'numbering_value' => '1',
                                        'sort_order' => 0,
                                    ],
                                    [
                                        'title' => 'Sample collection room / area',
                                        'message' => "Photograph of:\n1. Collection room outside\n2. Collection room inside\n3. Collection couch/chair",
                                        'note' => true,
                                        'numbering_type' => 'numerical',
                                        'numbering_value' => '2',
                                        'sort_order' => 0,
                                    ],
                                    [
                                        'title' => 'Washing area',
                                        'message' => "Photograph of:\n1. Washing area",
                                        'note' => true,
                                        'numbering_type' => 'numerical',
                                        'numbering_value' => '3',
                                        'sort_order' => 0,
                                    ],
                                    [
                                        'title' => 'Preservation of specimens and slides',
                                        'message' => "Photograph of:\n1. Sample storage refrigerator outside with thermometer\n2. Sample storage refrigerator inside (sample trey visible with samples with cap)\n3. Refrigerator temperature record (sample)\n4. Thermometer calibration certificate\n5. Slide box (outside)\n6. Slide box (inside)",
                                        'note' => true,
                                        'numbering_type' => 'numerical',
                                        'numbering_value' => '4',
                                        'sort_order' => 0,
                                    ],
                                    [
                                        'title' => 'Temperature control for specialized equipment',
                                        'message' => "Photograph of:\n1. Sample storage refrigerator outside with thermometer\n2. Sample storage refrigerator inside (sample trey visible with samples with cap)\n3. Refrigerator temperature record (sample)\n4. Thermometer calibration certificate (sample refrigerator)\n5. Reagent storage refrigerator outside with thermometer\n6. Reagent storage refrigerator inside (reagents visible)\n7. Refrigerator temperature record (Reagent)\n8. Thermometer calibration certificate (Reagent refrigerator)",
                                        'note' => true,
                                        'numbering_type' => 'numerical',
                                        'numbering_value' => '5',
                                        'sort_order' => 0,
                                    ],
                                    [
                                        'title' => 'Counselling room for HIV (If HIV is done)',
                                        'message' => "Photograph of:\n1. HIV counselling room outside\n2. HIV counselling room inside\n\n",
                                        'note' => true,
                                        'numbering_type' => 'numerical',
                                        'numbering_value' => '6',
                                        'sort_order' => 0,
                                    ],
                                    [
                                        'title' => 'Basins',
                                        'message' => "Photograph of:\n1. Basin",
                                        'note' => true,
                                        'numbering_type' => 'numerical',
                                        'numbering_value' => '7',
                                        'sort_order' => 0,
                                    ],
                                ],
                            ],
                            [
                                'title' => 'Equipment',
                                'message' => 'Photographs of Dicipline (department wise equipment records and certificates)',
                                'note' => true,
                                'numbering_type' => 'numerical',
                                'numbering_value' => '4',
                                'sort_order' => 0,
                            ],
                            [
                                'title' => 'Legal / Statutory Requirements',
                                'message' => 'Legal / Statutory Requirements',
                                'note' => true,
                                'numbering_type' => 'numerical',
                                'numbering_value' => '5',
                                'sort_order' => 0,
                                'children' => [
                                    [
                                        'title' => 'Biomedical Waste Management registration',
                                        'message' => "Photograph /pdf of:\n1. Valid registration certificate under Biomedical Waste Management",
                                        'note' => true,
                                        'numbering_type' => 'numerical',
                                        'numbering_value' => '1',
                                        'sort_order' => 0,
                                    ],
                                    [
                                        'title' => 'Pollution Control Board registration certificate',
                                        'message' => 'Photograph/pdf of: Pollution Control Board registration certificate',
                                        'note' => true,
                                        'numbering_type' => 'numerical',
                                        'numbering_value' => '2',
                                        'sort_order' => 0,
                                    ],
                                ],
                            ],
                            [
                                'title' => 'Record Maintenance and Reporting',
                                'message' => 'Record Maintenance and Reporting',
                                'note' => true,
                                'numbering_type' => 'numerical',
                                'numbering_value' => '6',
                                'sort_order' => 0,
                                'children' => [
                                    [
                                        'title' => 'Records of all patient reports date-wise as per regulatory requirement or till next audit, whichever is later',
                                        'message' => "Photographs of:\n1. LIMS screen with patient list\n2. LIMS screen with result entry\n3. LIMS screen with reports\n4. Pdf report",
                                        'note' => true,
                                        'numbering_type' => 'numerical',
                                        'numbering_value' => '1',
                                        'sort_order' => 0,
                                    ],
                                    [
                                        'title' => 'Medico-legal records, if applicable (as per relavent law)',
                                        'message' => 'Not applicable',
                                        'note' => true,
                                        'numbering_type' => 'numerical',
                                        'numbering_value' => '2',
                                        'sort_order' => 0,
                                    ],
                                    [
                                        'title' => 'Duration of preservation of record (as applicable from time to time)',
                                        'message' => "Photographs of \n1. QSP - Control of records",
                                        'note' => true,
                                        'numbering_type' => 'numerical',
                                        'numbering_value' => '3',
                                        'sort_order' => 0,
                                    ],
                                ],
                            ],
                            [
                                'title' => 'Standards on Basic Process',
                                'message' => 'Standards on Basic Process',
                                'note' => true,
                                'numbering_type' => 'numerical',
                                'numbering_value' => '7',
                                'sort_order' => 0,
                                'children' => [
                                    [
                                        'title' => 'Infection Control practices - as per Bio Medical Waste Management Rules',
                                        'message' => "Photograph of:\nQSP- Biomedical waste disposal\n2. Biomedical waste dusbins\n3. Biomedical waste pickup details",
                                        'note' => true,
                                        'numbering_type' => 'numerical',
                                        'numbering_value' => '1',
                                        'sort_order' => 0,
                                    ],
                                    [
                                        'title' => 'Patient Information',
                                        'message' => "Photograph of:\n1. Filled TRF\n2. LIMS patient registration\n3. LIMS patient list\n4. Patient report\n5. Patient record register(if available)",
                                        'note' => true,
                                        'numbering_type' => 'numerical',
                                        'numbering_value' => '2',
                                        'sort_order' => 0,
                                    ],
                                    [
                                        'title' => 'Kit inserts used as SOPs',
                                        'message' => "Photograph of \n1. Dicipline (department) wise 1 SOP and its respective kit insert",
                                        'note' => true,
                                        'numbering_type' => 'numerical',
                                        'numbering_value' => '3',
                                        'sort_order' => 0,
                                    ],
                                    [
                                        'title' => 'Complaints redressal mechanism',
                                        'message' => "Photograph of:\n1. QSP- Complaint resolution\n2. FF - Complaint resolution",
                                        'note' => true,
                                        'numbering_type' => 'numerical',
                                        'numbering_value' => '4',
                                        'sort_order' => 0,
                                    ],
                                ],
                            ],
                            [
                                'title' => 'Quality Checks',
                                'message' => 'Quality control and EQAS details',
                                'note' => true,
                                'numbering_type' => 'numerical',
                                'numbering_value' => '8',
                                'sort_order' => 0,
                                'children' => [
                                    [
                                        'title' => 'Performing internal quality control',
                                        'message' => "Photograph of:\n1. Signed LJ charts (previous month/completed) of tests in scope",
                                        'note' => true,
                                        'numbering_type' => 'numerical',
                                        'numbering_value' => '1',
                                        'sort_order' => 0,
                                    ],
                                    [
                                        'title' => 'Participating in proficiency testing programs in every six months',
                                        'message' => "Photograph of:\n1. Signed EQAS evaluation report (current completed cycle) for of tests in scope. (not 6 month prior)",
                                        'note' => true,
                                        'numbering_type' => 'numerical',
                                        'numbering_value' => '2',
                                        'sort_order' => 0,
                                    ],
                                ],
                            ],
                            [
                                'title' => 'Document upload',
                                'message' => 'Document upload',
                                'note' => true,
                                'numbering_type' => 'numerical',
                                'numbering_value' => '9',
                                'sort_order' => 0,
                                'children' => [
                                    [
                                        'title' => 'Legal documents PDF',
                                        'message' => "Refer Excel and Fill data and upload according to legal document checklist.\nProvide All legal documents. (As applicable). \nCancelled Cheque\nRent agreement/purchase\nMPCB certificate\nBMW certificate\nPathologist Degree certificate\nPathologist medical council registration certificate\nBank passbook / account statement and PAN of CAB\nRegistration certificate under Partnership\nRegistration certificate under LLP Act\nRegistration certificate under Companies Act\nSociety / Trust\tSociety Registration Act certificate / Indian Trusts Act registration\nGovernment: Gazette / Government notification / self-declaration on letterhead by Head of Organization",
                                        'note' => true,
                                        'numbering_type' => 'numerical',
                                        'numbering_value' => '1',
                                        'sort_order' => 0,
                                    ],
                                    [
                                        'title' => 'PT Document PDF',
                                        'message' => 'Dicipline (department) wise PT evaluation document. Pdf of signed copy',
                                        'note' => true,
                                        'numbering_type' => 'numerical',
                                        'numbering_value' => '2',
                                        'sort_order' => 0,
                                    ],
                                    [
                                        'title' => 'Equipment Document',
                                        'message' => "PDF of \n1. Installation certificate, IQ, OQ, PQ and Calibration certificate (with instrument raw data) Department wise Testing equipment's\n1. Ancillary instrument calibration (Individual pdf of each instrument)",
                                        'note' => true,
                                        'numbering_type' => 'numerical',
                                        'numbering_value' => '3',
                                        'sort_order' => 0,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($standards as $standardData) {
            $clauses = $standardData['clauses'];
            unset($standardData['clauses']);

            $standard = Standard::updateOrCreate(
                ['uuid' => $standardData['uuid']],
                $standardData
            );

            $this->seedClauses($standard, $clauses);

            $this->command->info("Seeded standard: {$standard->name}");
        }
    }

    private function seedClauses(Standard $standard, array $clauses, ?int $parentId = null): void
    {
        foreach ($clauses as $clauseData) {
            $children = $clauseData['children'] ?? [];
            unset($clauseData['children']);

            $clauseData['standard_id'] = $standard->id;
            $clauseData['parent_id'] = $parentId;
            $clauseData['is_child'] = ! empty($children);

            $clause = Clause::updateOrCreate(
                [
                    'standard_id' => $standard->id,
                    'parent_id' => $parentId,
                    'title' => $clauseData['title'],
                ],
                $clauseData
            );

            if (! empty($children)) {
                $this->seedClauses($standard, $children, $clause->id);
            }
        }
    }
}
