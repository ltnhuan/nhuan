<?php

namespace App\Contracts;

use App\Models\GradeApprovalBatch;
use App\Models\Gradebook;

interface SISGradeSyncContract
{
    public function syncGradebook(Gradebook $gradebook, GradeApprovalBatch $batch): array;
}
