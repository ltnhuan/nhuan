<?php

namespace App\Services;

use App\Contracts\SISGradeSyncContract;
use App\Models\GradeApprovalBatch;
use App\Models\Gradebook;

class NullSISGradeSyncService implements SISGradeSyncContract
{
    public function syncGradebook(Gradebook $gradebook, GradeApprovalBatch $batch): array
    {
        return ['provider' => 'null', 'gradebook_id' => $gradebook->id, 'batch_id' => $batch->id, 'synced' => true];
    }
}
