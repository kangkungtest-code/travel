<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use SensitiveParameter;

/** Login panel admin: email (Owner & staf) atau username (Super Admin). */
class Masuk extends Login
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Email atau username')
            ->required()
            ->maxLength(150)
            ->autocomplete('username')
            ->autofocus();
    }

    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        $login = trim((string) $data['email']);
        $kolom = str_contains($login, '@') ? 'email' : 'username';

        return [$kolom => $kolom === 'email' ? mb_strtolower($login) : mb_strtolower($login), 'password' => $data['password']];
    }
}
