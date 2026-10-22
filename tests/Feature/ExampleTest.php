<?php

test('returns a successful response', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertSee('CuyoTech SSIS Backend API');
    $response->assertSee('http://localhost:3000');
});

test('returns json status when requested as json', function () {
    $response = $this->getJson(route('home'));

    $response->assertOk()
        ->assertJson([
            'service' => 'CuyoTech SSIS API',
            'status' => 'operational',
            'frontend_url' => 'http://localhost:3000',
        ]);
});
