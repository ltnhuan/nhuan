<?php

namespace App\Policies;

use App\Models\LmsUser;
use App\Models\Question;

class QuestionPolicy
{
    public function update(LmsUser $user, Question $question): bool
    {
        return $user->tenant_id === $question->tenant_id && ($user->id === $question->owner_id || in_array($user->user_type, ['admin', 'staff'], true));
    }
}
