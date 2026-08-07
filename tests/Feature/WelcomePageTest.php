<?php

test('welcome page renders the landing page sections', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertSee('NetPlay');
    $response->assertSee('TOURNAMENTS');
    $response->assertSee('Annual Tournaments');
    $response->assertSee('FEATURED PLAYERS');
});
