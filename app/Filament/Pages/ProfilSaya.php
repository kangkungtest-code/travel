<?php

namespace App\Filament\Pages;

use Filament\Auth\Pages\EditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

/** Profil admin (menu avatar): ganti nama, email, password sendiri. */
class ProfilSaya extends EditProfile
{
    protected function getNameFormComponent(): Component
    {
        return TextInput::make('nama_lengkap')->label('Nama')->required()->maxLength(100)->autofocus();
    }
}
