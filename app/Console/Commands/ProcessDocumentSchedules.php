<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Document;
use App\Models\Lab;
use App\Models\LabDocumentsEntryData;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProcessDocumentSchedules extends Command
{
    protected $signature = 'app:process-document-schedules';
    protected $description = 'Process document schedules and create blank entries (Backfill + Daily)';

    public function handle()
    {
        $today = now()->endOfDay();

        $documents = Document::with('currentVersion')
            ->whereHas('currentVersion', function ($q) {
                $q->whereNotNull('schedule')
                  ->where('is_current', true);
            })
            ->get();


        foreach ($documents as $document) {

            $version = $document->currentVersion;

            $schedule = $version->schedule ?? null;


            if (!$schedule || empty($schedule['type'])) {
                continue;
            }

            // Start from effective_date OR version created_at
            $startDate = $version->effective_date
                ? Carbon::parse($version->effective_date)->startOfDay()
                : $version->created_at->startOfDay();


            $interval = $schedule['interval'] ?? 1;

            $date = $startDate->copy();
            $dayCounter = 0;


            while ($date->lte($today)) {

                if ($schedule['type'] === 'Daily') {

                    if ($dayCounter % $interval === 0) {
                        $this->processDaily($document, $version, $schedule, $date);
                    }

                } elseif ($schedule['type'] === 'Weekly') {

                    if (
                        $date->format('l') === ($schedule['selectedDay'] ?? null)
                    ) {
                        $this->processDaily($document, $version, $schedule, $date);
                    }

                } elseif ($schedule['type'] === 'Monthly') {

                    if (
                        $date->day == ($schedule['selectedDay'] ?? null)
                    ) {
                        $this->processDaily($document, $version, $schedule, $date);
                    }
                }

                $date->addDay();
                $dayCounter++;
            }
        }

        $this->info('Scheduler Backfill Completed');
    }

    /*
    |--------------------------------------------------------------------------
    | PROCESS DAILY TIMES
    |--------------------------------------------------------------------------
    */
    private function processDaily($document, $version, $schedule, $date)
    {
        $times = $schedule['cutOffTimes'] ?? ['00:00'];


        foreach ($times as $time) {

            $runDateTime = Carbon::parse(
                $date->format('Y-m-d') . ' ' . $time
            );


            $this->createBlankEntry($document, $version, $runDateTime);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE ENTRY (LAB + MASTER SUPPORT)
    |--------------------------------------------------------------------------
    */
    private function createBlankEntry($document, $version, $runDateTime)
    {
        $blankFields = [];
        if($document->mode == 'upload'){
            $blankFields['document'] = "";
        }else{
            if ($version->form_fields && is_array($version->form_fields)) {
                foreach ($version->form_fields as $key => $config) {
                    $safeKey = str_replace(' ', '_', $key);
                    $blankFields[$safeKey] = "";
                }
            }
        }


        // LAB document
        if ($document->owner_type === 'lab') {

            $lab = Lab::with('users')->find($document->owner_id);

            if (!$lab) return;

            $superAdmin = $lab->users->firstWhere('is_super_admin', true);
            \Log::info($superAdmin);

            if (!$superAdmin) return;

            $this->createEntryForLab(
                $document,
                $version,
                $lab->id,
                $superAdmin->id,
                $blankFields,
                $runDateTime
            );
        }

        // MASTER document → all labs
        // if ($document->owner_type === 'one_step') {

        //     $labs = Lab::with('users')->get();

        //     foreach ($labs as $lab) {

        //         $superAdmin = $lab->users->firstWhere('super_admin', true);
        //         if (!$superAdmin) continue;

        //         $this->createEntryForLab(
        //             $document,
        //             $version,
        //             $lab->id,
        //             $superAdmin->id,
        //             $blankFields,
        //             $runDateTime
        //         );
        //     }
        // }
    }

    /*
    |--------------------------------------------------------------------------
    | SAFE INSERT (NO DUPLICATE PER DATE+TIME)
    |--------------------------------------------------------------------------
    */
    private function createEntryForLab(
        $document,
        $version,
        $labId,
        $userId,
        $blankFields,
        $runDateTime
    ) {
        $exists = LabDocumentsEntryData::where('document_id', $document->id)
            ->where('document_version_id', $version->id)
            ->where('lab_id', $labId)
            ->whereDate('created_at', $runDateTime->toDateString())
            ->whereTime('created_at', $runDateTime->format('H:i:s'))
            ->exists();

        if ($exists) {
            return;
        }

        DB::transaction(function () use (
            $document,
            $version,
            $labId,
            $userId,
            $blankFields,
            $runDateTime
        ) {
            LabDocumentsEntryData::create([
                'user_id' => $userId,
                'lab_id' => $labId,
                'document_id' => $document->id,
                'document_version_id' => $version->id,
                'fields_entry' => $blankFields,
                'created_at' => $runDateTime,
                'updated_at' => $runDateTime,
            ]);
        });
    }
}