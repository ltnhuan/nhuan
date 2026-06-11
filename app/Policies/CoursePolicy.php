<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\LmsUser;
use App\Services\Core\CorePermissionService;

class CoursePolicy
{
    public function __construct(private readonly CorePermissionService $permissions)
    {
    }

    public function view(LmsUser $user, Course $course): bool
    {
        return $this->permissions->can($user, 'course.view', ['tenant_id' => $course->tenant_id, 'academic_unit_id' => $course->academic_unit_id, 'course_id' => $course->id]);
    }

    public function create(LmsUser $user): bool
    {
        return $this->permissions->can($user, 'course.create', ['tenant_id' => $user->tenant_id]);
    }

    public function update(LmsUser $user, Course $course): bool
    {
        return $course->owner_id === $user->id || $this->permissions->can($user, 'course.update', ['tenant_id' => $course->tenant_id, 'academic_unit_id' => $course->academic_unit_id, 'course_id' => $course->id]);
    }

    public function approve(LmsUser $user, Course $course): bool
    {
        return $this->permissions->can($user, 'course.approve', ['tenant_id' => $course->tenant_id, 'academic_unit_id' => $course->academic_unit_id, 'course_id' => $course->id]);
    }

    public function publish(LmsUser $user, Course $course): bool
    {
        return $this->permissions->can($user, 'course.publish', ['tenant_id' => $course->tenant_id, 'academic_unit_id' => $course->academic_unit_id, 'course_id' => $course->id]);
    }
}
