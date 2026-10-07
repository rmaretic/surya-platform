<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

test('registration and recovery emails arrive in the local inbox with trusted links', function () {
    $this->prepareStudio();
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1',
        'mail.mailers.smtp.port' => 11025, 'mail.mailers.smtp.url' => null,
        'mail.mailers.smtp.scheme' => 'smtp', 'mail.mailers.smtp.username' => null,
        'mail.mailers.smtp.password' => null]);
    $email = 'm1-05.'.Str::uuid().'@example.test';
    $this->post('http://lotus.yoga.test/register', ['name' => 'Local mail test', 'email' => $email,
        'password' => 'password123', 'password_confirmation' => 'password123'])->assertRedirect('/account');
    $this->postJson('http://lotus.yoga.test/forgot-password', ['email' => $email])->assertOk();
    $messages = Http::timeout(5)->get('http://127.0.0.1:18025/api/v1/search', ['query' => 'to:'.$email])->throw()->json('messages');
    expect($messages)->toHaveCount(2);
    expect(array_column($messages, 'Subject'))->toContain('Verify your email address', 'Reset your password');
    foreach ($messages as $message) {
        $body = Http::timeout(5)->get('http://127.0.0.1:18025/api/v1/message/'.$message['ID'])->throw()->json('Text');
        $path = $message['Subject'] === 'Verify your email address' ? 'email/verify/' : 'reset-password/';
        expect(str_contains($body, 'http://lotus.yoga.test/'.$path))->toBeTrue();
    }
})->group('local-mail');
