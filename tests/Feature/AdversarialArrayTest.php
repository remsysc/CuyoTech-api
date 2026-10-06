<?php

use App\Models\Student;
use App\Models\User;

describe('Adversarial Challenge: Array Query Parameters Injection', function () {
    it('tests array query parameter injection on subjects and grades endpoints', function () {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        Student::factory()->create(['user_id' => $user->id]);
        $token = $user->createToken('auth')->plainTextToken;

        $endpoints = ['/api/student/subjects', '/api/student/grades'];

        foreach ($endpoints as $ep) {
            $res = $this->withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/json',
            ])->getJson($ep.'?school_year[]=2026-2027&semester[]=1');

            dump([
                'endpoint' => $ep,
                'status' => $res->status(),
                'body' => $res->json(),
            ]);
        }
    });
});
