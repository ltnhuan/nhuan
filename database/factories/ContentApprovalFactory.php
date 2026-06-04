<?php

namespace Database\Factories;

use App\Models\ContentApproval;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContentApprovalFactory extends Factory
{
    protected $model = ContentApproval::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'entity_type' => 'course',
            'entity_id' => 1,
            'from_status' => 'draft',
            'to_status' => 'review',
            'requested_by' => 1,
            'decision' => 'pending',
            'note' => 'Yêu cầu duyệt',
        ];
    }
}
