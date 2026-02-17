<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Deploy;

use App\Filament\Concerns\HasGlobalBulkActions;
use App\Filament\Concerns\RemembersTableSettings;
use App\Filament\Resources\Modules\Deploy\DomainResource\Pages;
use App\Filament\Resources\Modules\Deploy\DomainResource\RelationManagers\DNSRecordsRelationManager;
use App\Jobs\ConfigureDNSJob;
use App\Jobs\RequestSSLJob;
use App\Modules\Deploy\Models\Domain;
use App\Modules\Deploy\Services\OVHService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Queue;

/**
 * Filament Resource dla zarządzania domenami.
 */
final class DomainResource extends Resource
{
    use HasGlobalBulkActions;
    use RemembersTableSettings;

    protected static ?string $model = Domain::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationLabel = 'Domeny';

    protected static ?string $modelLabel = 'Domena';

    protected static ?string $pluralModelLabel = 'Domeny';

    protected static ?string $navigationGroup = 'Deployment';

    /**
     * Tylko super admin widzi domeny.
     */
    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        return $user && ($user->is_super_admin || $user->hasRole('super_admin'));
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Podstawowe informacje')
                    ->schema([
                        Forms\Components\TextInput::make('domain')
                            ->label('Domena')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Pełna nazwa domeny (np. example.com)'),

                        Forms\Components\TextInput::make('subdomain')
                            ->label('Subdomena')
                            ->maxLength(255)
                            ->helperText('Subdomena (np. dev, www)'),

                        Forms\Components\Select::make('dns_status')
                            ->label('Status DNS')
                            ->options([
                                Domain::DNS_STATUS_PENDING => 'Oczekujący',
                                Domain::DNS_STATUS_PROPAGATING => 'Propagacja',
                                Domain::DNS_STATUS_ACTIVE => 'Aktywny',
                                Domain::DNS_STATUS_FAILED => 'Nieudany',
                            ])
                            ->default(Domain::DNS_STATUS_PENDING)
                            ->native(false)
                            ->disabled(),

                        Forms\Components\Select::make('ssl_status')
                            ->label('Status SSL')
                            ->options([
                                Domain::SSL_STATUS_PENDING => 'Oczekujący',
                                Domain::SSL_STATUS_REQUESTED => 'Żądany',
                                Domain::SSL_STATUS_ACTIVE => 'Aktywny',
                                Domain::SSL_STATUS_EXPIRED => 'Wygasły',
                                Domain::SSL_STATUS_FAILED => 'Nieudany',
                            ])
                            ->default(Domain::SSL_STATUS_PENDING)
                            ->native(false)
                            ->disabled(),

                        Forms\Components\TextInput::make('vps_ip')
                            ->label('IP VPS')
                            ->maxLength(255)
                            ->default(config('vps.ip', '203.0.113.10')),

                        Forms\Components\TextInput::make('mail_hostname')
                            ->label('Mail Hostname')
                            ->maxLength(255)
                            ->helperText('Hostname dla email (np. mail.example.com)'),

                        Forms\Components\DateTimePicker::make('dns_checked_at')
                            ->label('Ostatnie sprawdzenie DNS')
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('ssl_expires_at')
                            ->label('Data wygaśnięcia SSL')
                            ->disabled(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        // Zapamiętaj ustawienia w sesji (automatyczne klucze)
        $table->persistSortInSession()
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistColumnSearchesInSession();
        
        $table = $table
            ->columns([
                Tables\Columns\TextColumn::make('domain')
                    ->label('Domena')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('subdomain')
                    ->label('Subdomena')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('dns_status')
                    ->label('DNS')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Domain::DNS_STATUS_ACTIVE => 'success',
                        Domain::DNS_STATUS_PROPAGATING => 'warning',
                        Domain::DNS_STATUS_FAILED => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Domain::DNS_STATUS_PENDING => 'Oczekujący',
                        Domain::DNS_STATUS_PROPAGATING => 'Propagacja',
                        Domain::DNS_STATUS_ACTIVE => 'Aktywny',
                        Domain::DNS_STATUS_FAILED => 'Nieudany',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('ssl_status')
                    ->label('SSL')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Domain::SSL_STATUS_ACTIVE => 'success',
                        Domain::SSL_STATUS_REQUESTED => 'warning',
                        Domain::SSL_STATUS_EXPIRED => 'danger',
                        Domain::SSL_STATUS_FAILED => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Domain::SSL_STATUS_PENDING => 'Oczekujący',
                        Domain::SSL_STATUS_REQUESTED => 'Żądany',
                        Domain::SSL_STATUS_ACTIVE => 'Aktywny',
                        Domain::SSL_STATUS_EXPIRED => 'Wygasły',
                        Domain::SSL_STATUS_FAILED => 'Nieudany',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('ssl_expires_at')
                    ->label('SSL wygasa')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable()
                    ->color(fn (?Domain $record): ?string => $record?->isSslExpiringSoon() ? 'danger' : null),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('dns_status')
                    ->label('Status DNS')
                    ->options([
                        Domain::DNS_STATUS_PENDING => 'Oczekujący',
                        Domain::DNS_STATUS_PROPAGATING => 'Propagacja',
                        Domain::DNS_STATUS_ACTIVE => 'Aktywny',
                        Domain::DNS_STATUS_FAILED => 'Nieudany',
                    ])
                    ->native(false),

                Tables\Filters\SelectFilter::make('ssl_status')
                    ->label('Status SSL')
                    ->options([
                        Domain::SSL_STATUS_PENDING => 'Oczekujący',
                        Domain::SSL_STATUS_REQUESTED => 'Żądany',
                        Domain::SSL_STATUS_ACTIVE => 'Aktywny',
                        Domain::SSL_STATUS_EXPIRED => 'Wygasły',
                        Domain::SSL_STATUS_FAILED => 'Nieudany',
                    ])
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('configure_dns')
                    ->label('Konfiguruj DNS')
                    ->icon('heroicon-o-globe-alt')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function (Domain $record, OVHService $ovhService): void {
                        try {
                            if (empty($record->subdomain) || empty($record->vps_ip)) {
                                throw new \RuntimeException('Uzupełnij subdomenę i IP VPS');
                            }

                            Queue::push(new ConfigureDNSJob(
                                $record->id,
                                $record->domain,
                                $record->subdomain,
                                $record->vps_ip
                            ));

                            Notification::make()
                                ->title('Konfiguracja DNS uruchomiona')
                                ->body("DNS dla {$record->domain} został dodany do kolejki")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Błąd')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn (Domain $record): bool => ! $record->isDnsActive()),
                Tables\Actions\Action::make('check_propagation')
                    ->label('Sprawdź propagację')
                    ->icon('heroicon-o-magnifying-glass')
                    ->color('warning')
                    ->action(function (Domain $record, OVHService $ovhService): void {
                        try {
                            $fullDomain = $record->subdomain
                                ? "{$record->subdomain}.{$record->domain}"
                                : $record->domain;

                            $results = $ovhService->checkPropagation($fullDomain);

                            $message = "Wyniki propagacji DNS dla {$fullDomain}:\n\n";
                            foreach ($results as $server => $result) {
                                $message .= "{$server}: ".($result ?: 'Brak odpowiedzi')."\n";
                            }

                            Notification::make()
                                ->title('Propagacja DNS')
                                ->body($message)
                                ->info()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Błąd')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('request_ssl')
                    ->label('Wygeneruj SSL')
                    ->icon('heroicon-o-lock-closed')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Domain $record): void {
                        if (! $record->isDnsActive()) {
                            Notification::make()
                                ->title('DNS nie jest aktywny')
                                ->body('Najpierw skonfiguruj DNS')
                                ->warning()
                                ->send();

                            return;
                        }

                        Queue::push(new RequestSSLJob(
                            $record->id,
                            $record->domain,
                            $record->subdomain ? ["www.{$record->domain}"] : []
                        ));

                        Notification::make()
                            ->title('Żądanie SSL uruchomione')
                            ->body("Certyfikat SSL dla {$record->domain} został dodany do kolejki")
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Domain $record): bool => $record->isDnsActive() && ! $record->isSslActive()),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
        
        // Konfiguruj bulk actions
        $table = static::configureBulkActions($table);
        
        return $table;
    }

    public static function getRelations(): array
    {
        return [
            // DNSRecordsRelationManager::class, // TODO: Utworzyć RelationManager
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDomains::route('/'),
            'create' => Pages\CreateDomain::route('/create'),
            'edit' => Pages\EditDomain::route('/{record}/edit'),
        ];
    }
}
