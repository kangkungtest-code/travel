<?php

namespace App\Filament\Resources\AkunAdmin;

use App\Filament\Resources\AkunAdmin\Pages\ManageAkunAdmin;
use App\Models\User;
use App\Support\HakAkses;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Password;
use UnitEnum;

/**
 * Akun admin toko. Super Admin melihat & membuat akun Owner; Owner melihat & membuat staf.
 * Akun tidak dihapus (riwayat pesanan mencatat pelakunya) — cukup dinonaktifkan.
 */
class AkunAdminResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'akun';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string | UnitEnum | null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 90;

    public static function getNavigationLabel(): string
    {
        return HakAkses::kelolaOwner() ? 'Akun Owner' : 'Akun staf';
    }

    public static function getModelLabel(): string
    {
        return HakAkses::kelolaOwner() ? 'akun Owner' : 'akun staf';
    }

    public static function getPluralModelLabel(): string
    {
        return self::getNavigationLabel();
    }

    public static function canAccess(): bool
    {
        return HakAkses::kelolaOwner() || HakAkses::kelolaStaf();
    }

    public static function canViewAny(): bool
    {
        return self::canAccess();
    }

    public static function canCreate(): bool
    {
        return self::canAccess();
    }

    public static function canEdit($record): bool
    {
        return self::canAccess() && HakAkses::queryAkun()->whereKey($record->getKey())->exists();
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return HakAkses::queryAkun()->with('roles');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('nama_lengkap')->label('Nama')->required()->maxLength(100),
            TextInput::make('email')->label('Email')->email()->required()->maxLength(150)
                ->unique(User::class, 'email', ignoreRecord: true)
                ->validationMessages(['unique' => 'Email ini sudah dipakai akun lain (admin atau pembeli).']),
            TextInput::make('password_baru')->label(fn (string $operation) => $operation === 'create' ? 'Password' : 'Password baru')
                ->password()->revealable()->autocomplete('new-password')
                ->required(fn (string $operation) => $operation === 'create')
                ->rule(Password::defaults())
                ->helperText(fn (string $operation) => $operation === 'create'
                    ? 'Berikan ke pemilik akun; dia bisa menggantinya sendiri di menu profil.'
                    : 'Kosongkan kalau tidak diganti.'),
            Select::make('peran')->label('Peran')
                ->options(fn () => HakAkses::queryPeranStaf()->orderBy('name')->pluck('name', 'name')->all())
                ->required()
                ->visible(fn () => ! HakAkses::kelolaOwner())
                ->helperText('Atur izin tiap peran di menu Peran staf.'),
            Toggle::make('aktif')->label('Aktif (bisa masuk panel)')->default(true)
                ->visible(fn (string $operation) => $operation === 'edit'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at')
            ->columns([
                TextColumn::make('nama_lengkap')->label('Nama')->searchable(),
                TextColumn::make('email')->label('Email')->searchable(),
                TextColumn::make('roles.name')->label('Peran')->badge(),
                TextColumn::make('nonaktif_pada')->label('Status')
                    ->state(fn (User $u) => $u->nonaktif_pada ? 'Nonaktif' : 'Aktif')
                    ->badge()->color(fn (string $state) => $state === 'Aktif' ? 'success' : 'gray'),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y', config('toko.zona_waktu')),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(function (array $data, User $record) {
                        $data['peran'] = $record->roles->first()?->name;
                        $data['aktif'] = $record->nonaktif_pada === null;

                        return $data;
                    })
                    ->using(fn (User $record, array $data) => self::simpan($data, $record)),
            ]);
    }

    /** Simpan dari form (buat / ubah). */
    public static function simpan(array $data, ?User $user = null): User
    {
        $baru = $user === null;
        $user ??= new User(['bahasa_preferensi' => 'id', 'mata_uang_preferensi' => 'IDR']);
        $user->fill(['nama_lengkap' => $data['nama_lengkap'], 'email' => mb_strtolower(trim($data['email']))]);
        if (filled($data['password_baru'] ?? null)) {
            $user->password = $data['password_baru'];
        }
        if ($baru) {
            $user->email_verified_at = now();
        }
        if (array_key_exists('aktif', $data)) {
            $user->nonaktif_pada = $data['aktif'] ? null : ($user->nonaktif_pada ?? now());
        }
        $user->save();

        $peran = HakAkses::kelolaOwner() ? \Database\Seeders\RoleAndPermissionSeeder::OWNER : $data['peran'];
        // Jaga-jaga: staf hanya boleh diberi peran staf.
        if (! HakAkses::kelolaOwner() && ! HakAkses::queryPeranStaf()->where('name', $peran)->exists()) {
            abort(403);
        }
        $user->syncRoles([$peran]);

        if (! $baru && $user->nonaktif_pada) {
            $user->tokens()->delete(); // keluar paksa dari aplikasi admin
        }

        return $user;
    }

    public static function getPages(): array
    {
        return ['index' => ManageAkunAdmin::route('/')];
    }
}
