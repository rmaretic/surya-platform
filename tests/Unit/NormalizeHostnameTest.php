<?php

use App\Actions\NormalizeHostname;

test('hostnames have a canonical ASCII representation', function (string $input, string $expected) {
    expect((new NormalizeHostname)->handle($input))->toBe($expected);
})->with([
    [' LOTUS.YOGA.TEST. ', 'lotus.yoga.test'],
    ['münchen.example', 'xn--mnchen-3ya.example'],
    ['xn--mnchen-3ya.example', 'xn--mnchen-3ya.example'],
]);

test('invalid hostnames are rejected rather than repaired into trusted hosts', function (string $input) {
    expect(fn () => (new NormalizeHostname)->handle($input))->toThrow(InvalidArgumentException::class);
})->with([
    '', 'localhost', '127.0.0.1', '[::1]', 'https://lotus.yoga.test',
    'lotus.yoga.test:8000', 'lotus.yoga.test/path', 'lotus.yoga.test@evil.test',
    'lotus..yoga.test', 'lotus.yoga.test..', '-lotus.yoga.test', 'lotus_.yoga.test',
    'lotus.yoga.test,evil.test', 'lotus yoga.test', str_repeat('a', 64).'.test',
]);
