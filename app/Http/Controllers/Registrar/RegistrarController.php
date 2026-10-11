<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\DocumentRequest;
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

    /**
     * Retrieve course roster for specified term (FR-7).
     * Auth: role=registrar
     */
    public function roster(Request $request, int|string $id): JsonResponse
    {
        $request->validate([
            'school_year' => ['required', 'string'],
            'semester' => ['required', 'integer'],
        ]);

        $course = Course::whereKey((int) $id)->first();
        if (! $course) {
            return response()->json([
                'error' => 'COURSE_NOT_FOUND',
            ], 404);
        }

        $schoolYear = (string) $request->query('school_year');
        $semester = (int) $request->query('semester');

        $enrollments = Enrollment::query()
            ->with(['student.user'])
            ->where('course_id', $course->id)
            ->where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->get();

        $data = $enrollments->map(fn (Enrollment $e): array => [
            'student_id' => $e->student_id,
            'student_number' => $e->student->student_number,
            'name' => $e->student->user->name,
            'grade' => $e->grade !== null ? (float) $e->grade : null,
            'status' => $e->status,
        ])->values();

        return response()->json($data);
    }

    /**
     * List courses for registrar catalog.
     */
    public function courses(): JsonResponse
    {
        $courses = Course::with('department')->orderBy('code')->get();

        $data = $courses->map(fn (Course $c): array => [
            'id' => $c->id,
            'code' => $c->code,
            'title' => $c->title,
            'units' => $c->units,
            'department_id' => $c->department_id,
            'department_name' => $c->department?->name,
        ]);

        return response()->json($data);
    }

    /**
     * List students with account balance for registrar enrollment.
     */
    public function students(): JsonResponse
    {
        $students = Student::with('user')->orderBy('student_number')->get();

        $data = $students->map(fn (Student $s): array => [
            'id' => $s->id,
            'student_number' => $s->student_number,
            'name' => $s->user?->name ?? 'Student',
            'email' => $s->user?->email,
            'balance_centavos' => (int) $s->balance_centavos,
            'balance_formatted' => $s->balance_formatted,
        ]);

        return response()->json($data);
    }

    /**
     * List all enrollments for registrar grade overview.
     */
    public function enrollments(Request $request): JsonResponse
    {
        $query = Enrollment::with(['student.user', 'course']);

        if ($request->filled('school_year')) {
            $query->where('school_year', $request->query('school_year'));
        }

        if ($request->filled('semester')) {
            $query->where('semester', (int) $request->query('semester'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $enrollments = $query->orderByDesc('id')->get();

        $data = $enrollments->map(fn (Enrollment $e): array => [
            'id' => $e->id,
            'student_id' => $e->student_id,
            'student_number' => $e->student?->student_number,
            'student_name' => $e->student?->user?->name ?? 'Student',
            'course_id' => $e->course_id,
            'course_code' => $e->course?->code,
            'course_title' => $e->course?->title,
            'units' => $e->course?->units ?? 0,
            'school_year' => $e->school_year,
            'semester' => (int) $e->semester,
            'grade' => $e->grade !== null ? (float) $e->grade : null,
            'status' => $e->status,
        ]);

        return response()->json($data);
    }

    /**
     * List document requests for registrar management.
     */
    public function documentRequests(): JsonResponse
    {
        $requests = DocumentRequest::with(['student.user'])
            ->orderByDesc('requested_at')
            ->get();

        $data = $requests->map(fn (DocumentRequest $d): array => [
            'id' => $d->id,
            'student_id' => $d->student_id,
            'student_number' => $d->student?->student_number,
            'student_name' => $d->student?->user?->name ?? 'Student',
            'type' => $d->type,
            'purpose' => $d->purpose,
            'status' => $d->status,
            'requested_at' => $d->requested_at?->toIso8601String(),
            'released_at' => $d->released_at?->toIso8601String(),
        ]);

        return response()->json($data);
    }
}
