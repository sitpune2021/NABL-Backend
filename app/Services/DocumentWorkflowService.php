<?php

namespace App\Services;

use App\Models\DocumentVersion;
use App\Models\DocumentVersionWorkflowLog;
use Illuminate\Support\Facades\DB;
use Exception;

class DocumentWorkflowService
{
    protected array $steps = [
        'prepared',
        'reviewed',
        'approved',
        'issued',
        'effective',
    ];

    public function act(
        int $documentVersionId,
        string $action,
        int $userId,
        ?string $comments = null
    ): string {
        return DB::transaction(function () use ($documentVersionId, $action, $userId, $comments) {

            $version = DocumentVersion::lockForUpdate()->findOrFail($documentVersionId);

            $currentStep  = $version->workflow_state ?? 'prepared';
            $currentIndex = array_search($currentStep, $this->steps);

            if ($currentIndex === false) {
                throw new Exception('Invalid workflow state');
            }

            /* =================== HARD RULES =================== */

            // 🚫 effective: only pending & completed allowed
            if ($currentStep === 'effective' && in_array($action, ['sent_back', 'rejected'])) {
                throw new Exception('Action not allowed on effective document');
            }

            /* =================== LOG ACTION =================== */

            DocumentVersionWorkflowLog::create([
                'document_version_id' => $version->id,
                'step_type'            => $currentStep,
                'step_status'          => $action,
                'performed_by'         => $userId,
                'comments'             => $comments,
            ]);

            /* =================== ACTION HANDLING =================== */

            // ⏸ pending → no movement
            if ($action === 'pending') {
                return $currentStep;
            }

            // ❌ rejected → restart workflow (before effective only)
            if ($action === 'rejected') {
                $version->update(['workflow_state' => 'prepared']);

                DocumentVersionWorkflowLog::create([
                    'document_version_id' => $version->id,
                    'step_type'            => 'prepared',
                    'step_status'          => 'pending',
                    'performed_by'         => $userId,
                    'comments'             => 'Restarted after rejection',
                ]);

                return 'prepared';
            }

            // 🔙 sent_back → previous step
            if ($action === 'sent_back') {
                $previousIndex = max(0, $currentIndex - 1);
                $previousStep  = $this->steps[$previousIndex];

                $version->update(['workflow_state' => $previousStep]);

                DocumentVersionWorkflowLog::create([
                    'document_version_id' => $version->id,
                    'step_type'            => $previousStep,
                    'step_status'          => 'pending',
                    'performed_by'         => $userId,
                    'comments'             => 'Sent back',
                ]);

                return $previousStep;
            }

            // ✅ completed
            if ($action === 'completed') {

                // ✅ effective completed → FINAL (no movement)
                if ($currentStep === 'effective') {
                    return 'effective';
                }

                // move forward
                $nextStep = $this->steps[$currentIndex + 1];

                $version->update(['workflow_state' => $nextStep]);

                DocumentVersionWorkflowLog::create([
                    'document_version_id' => $version->id,
                    'step_type'            => $nextStep,
                    'step_status'          => 'pending',
                    'performed_by'         => $userId,
                    'comments'             => 'Moved to next step',
                ]);

                return $nextStep;
            }

            throw new Exception('Invalid workflow action');
        });
    }
}
