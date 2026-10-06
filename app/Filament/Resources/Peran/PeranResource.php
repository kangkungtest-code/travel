<?php

namespace App\Filament\Resources\Peran;

use App\Filament\Resources\Peran\Pages\ManagePeran;
use App\Models\Role;
use App\Support\HakAkses;
use BackedEnum;
use Database\Seeders\RoleAndPermissionSeeder as R;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

/** Peran staf buatan Owner (mis. "Gudang", "CS") beserta izinnya. Owner & Super Admin tidak tampil. */
class PeranResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $slug = 'peran';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string | UnitEnum | null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 91;

    protected static ?string $navigationLabel = 'Peran staf';

    protected static ?string $modelLabel = 'peran';

    protected static ?string $pluralModelLabel = 'Peran staf';

    public static function canAccess(): bool
    {
        return HakAkses::kelolaStaf();
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
        return self::canAccess() && ! in_array($record->name, [R::OWNER, R::SUPER_ADMIN], true);
    }

    public static function canDelete($record): bool
    {
        return self::canEdit($record) && $record->users()->doesntExist();
    }

    public static function getEloquentQuery(): Builder
    {
        return HakAkses::queryPeranStaf()->withCount('users');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama peran')->placeholder('mis. Gudang, CS, Keuangan')->required()->maxLength(50)
                ->notIn([R::OWNER, R::SUPER_ADMIN])
                ->unique('roles', 'name', ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('guard_name', R::GUARD))
                ->validationMessages(['unique' => 'Nama peran ini sudah ada.', 'not_in' => 'Nama ini dipakai sistem.']),
            CheckboxList::make('izin')->label('Izin')
                ->options(R::LABEL)
                ->columns(2)
                ->required()
                ->helperText('Mengelola akun staf & peran hanya bisa dilakukan Owner.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Peran'),
                TextColumn::make('permissions_ringkas')->label('Izin')
                    ->state(fn (Role $r) => $r->permissions->pluck('name')->map(fn ($p) => R::LABEL[$p] ?? $p)->implode(', '))
                    ->wrap(),
                TextColumn::make('users_count')->label('Akun'),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(function (array $data, Role $record) {
                        $data['izin'] = $record->permissions->pluck('name')->all();

                        return $data;
                    })
                    ->using(fn (Role $record, array $data) => self::simpan($data, $record)),
                DeleteAction::make()->modalDescription('Hanya peran tanpa akun yang bisa dihapus.'),
            ]);
    }

    public static function simpan(array $data, ?Role $role = null): Role
    {
        $role ??= new Role(['guard_name' => R::GUARD]);
        $role->name = trim($data['name']);
        $role->save();
        // Hanya izin toko biasa; staf.kelola / izin Super Admin tidak pernah bisa diberikan.
        $role->syncPermissions(array_values(array_intersect(R::PERMISSIONS, $data['izin'] ?? [])));
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return $role;
    }

    public static function getPages(): array
    {
        return ['index' => ManagePeran::route('/')];
    }
}
