<?php

namespace App\Services;

use App\Models\Clearance;
use App\Models\Student;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ClearanceGuard
{
    /**
     * Ensure all clearances for the student (or for a specific term if provided) are approved.
     * If no term is specified, verifies all existing clearance records are approved.
     * If a student has no clearance records at all, or if any clearance record has status != 'approved',
     * aborts with 422 CLEARANCE_INCOMPLETE.
     *
     * @throws HttpException with 422 CLEARANCE_INCOMPLETE
     */
    public function ensureClearancesApproved(Student $student, ?string $schoolYear = null, ?int $semester = null): void
    {
        $query = Clearance::query()->where('student_id', $student->id);

        if ($schoolYear !== null && $semester !== null) {
            $query->where('school_year', $schoolYear)
                ->where('semester', $semester);
        }

        $clearances = $query->get();

        // If no clearances exist for the student or any clearance is not approved, clearance is incomplete
        if ($clearances->isEmpty() || $clearances->contains(fn (Clearance $c) => $c->status !== 'approved')) {
            abort(422, 'CLEARANCE_INCOMPLETE');
        }
    }
}
