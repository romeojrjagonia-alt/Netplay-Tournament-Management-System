<?php

it('generates https urls when behind a proxy that forwards the https scheme', function () {
    Route::get('/_proxy-check', fn () => url('/asset'));

    $this->get('/_proxy-check', [
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'netplay.example.com',
        'X-Forwarded-Port' => '443',
    ])->assertOk()->assertSee('https://netplay.example.com/asset');
});
