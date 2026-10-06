<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RegistrarController extends Controller
{
    /**
     * Enroll a student in a course for a term (FR-5).
     */
    public function enroll(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => ['required', 'integer'],
            'course_id' => ['required', 'integer'],
            'school_year' => ['required', 'string'],
            'semester' => ['required', 'integer'],
        ]);

        $student = Student::whereKey((int) $request->input('student_id'))->first();
        if (! $student) {
            return response()->json([
                'error' => 'STUDENT_NOT_FOUND',
            ], 404);
        }

        $course = Course::whereKey((int) $request->input('course_id'))->first();
        if (! $course) {
            return response()->json([
                'error' => 'COURSE_NOT_FOUND',
            ], 404);
        }

        $schoolYear = $request->input('school_year');
        $semester = (int) $request->input('semester');

        $alreadyEnrolled = Enrollment::where('student_id', $student->id)
            ->where('course_id', $course->id)
            ->where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->exists();

        if ($alreadyEnrolled) {
            return response()->json([
                'error' => 'ALREADY_ENROLLED',
            ], 409);
        }

        return DB::transaction(function () use ($student, $course, $schoolYear, $semester) {
            $ratePerUnit = (int) config('fees.rate_per_unit_centavos', 50000);
            $chargeApplied = (int) $course->units * $ratePerUnit;

            $enrollment = Enrollment::create([
                'student_id' => $student->id,
                'course_id' => $course->id,
                'school_year' => $schoolYear,
                'semester' => $semester,
                'status' => 'enrolled',
                'grade' => null,
            ]);

            $lockedStudent = Student::whereKey($student->id)->lockForUpdate()->first();
            if ($lockedStudent) {
                $lockedStudent->increment('balance_centavos', $chargeApplied);
            }

            return response()->json([
                'id' => $enrollment->id,
                'status' => 'enrolled',
                'charge_applied_centavos' => $chargeApplied,
            ], 201);
        });
    }

    /**
     * Encode or update a student's grade for an enrollment (FR-6).
     */
    public function encodeGrade(Request $request, int|string $id): JsonResponse
    {
        $request->validate([
            'grade' => ['required', 'numeric'],
        ]);

        $enrollment = Enrollment::whereKey((int) $id)->first();
        if (! $enrollment) {
            return response()->json([
                'error' => 'ENROLLMENT_NOT_FOUND',
            ], 404);
        }

        $rawGrade = (float) $request->input('grade');
        $gradeCents = (int) round($rawGrade * 100);

        if ($gradeCents < 100 || $gradeCents > 500 || ($gradeCents % 25) !== 0) {
            return response()->json([
                'error' => 'INVALID_GRADE_RANGE',
            ], 422);
        }

        $validatedGrade = $gradeCents / 100;

        $enrollment->grade = $validatedGrade;
        $enrollment->status = 'completed';
        $enrollment->save();

        return response()->json([
            'id' => $enrollment->id,
            'grade' => (float) $enrollment->grade,
            'status' => 'completed',
        ], 200);
    }
}
