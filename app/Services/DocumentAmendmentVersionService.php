<?php

namespace App\Services;

use App\Models\{
    DocumentVersion,
    DocumentVersionAmendment
};
use Illuminate\Support\Facades\DB;
use Exception;

class DocumentAmendmentVersionService
{
    public function handle(
        DocumentVersion $version,
        string $type,
        int $userId,
        ?string $reason = null
    ): DocumentVersion {

        if ($version->workflow_state === 'archived') {
            throw new Exception('Archived document cannot be updated');
        }

        // 🧾 Always save amendment
        DocumentVersionAmendment::create([
            'document_version_id' => $version->id,
            'amendment_type' => $type,
            'amendment_number' =>
                DocumentVersionAmendment::where('document_version_id', $version->id)->max('amendment_number') + 1,
            'amendment_reason' => $reason,
            'amended_by' => $userId,
            'amendment_date' => now(),
        ]);

        // ❌ No version change before effective
        if ($version->workflow_state !== 'effective') {
            return $version;
        }

        // ✅ Create new version AFTER effective
        return DB::transaction(function () use ($version, $type) {

            $version->update(['is_current' => false]);

            $major = $version->major_version;
            $minor = $version->minor_version;

            if ($type === 'major') {
                $major++;
                $minor = 0;
            } else {
                $minor++;
            }

            $new = $version->replicate();
            $new->major_version = $major;
            $new->minor_version = $minor;
            $new->full_version = "$major.$minor";
            $new->workflow_state = 'prepared';
            $new->is_current = true;
            $new->save();

            return $new;
        });
    }
}
