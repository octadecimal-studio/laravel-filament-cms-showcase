<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Deploy;

use App\Filament\Resources\Modules\Deploy\DeploymentResource\Pages;
use App\Jobs\DeployProjectJob;
use App\Modules\Deploy\Models\Deployment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Queue;

/**
 * Filament Resource dla zarządzania deploymentami.
 */
final class DeploymentResource extends Resource
{
    protected static ?string $model = Deployment::class;

    protected static ?string $navigationIcon = 'heroicon-o-rocket-launch';

    protected static ?string $navigationLabel = 'Wdrożenia';

    protected static ?string $modelLabel = 'Deployment';

    protected static ?string $pluralModelLabel = 'Wdrożenia';

    protected static ?string $navigationGroup = 'Deployment';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Podstawowe informacje')
                    ->schema([
                        Forms\Components\Select::make('domain_id')
                            ->label('Domena')
                            ->relationship('domain', 'domain')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\TextInput::make('version')
                            ->label('Wersja')
                            ->placeholder('20260122-212654')
                            ->helperText('Opcjonalnie - zostanie wygenerowana automatycznie'),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                Deployment::STATUS_PENDING => 'Oczekujący',
                                Deployment::STATUS_IN_PROGRESS => 'W trakcie',
                                Deployment::STATUS_COMPLETED => 'Zakończony',
                                Deployment::STATUS_FAILED => 'Nieudany',
                                Deployment::STATUS_ROLLED_BACK => 'Wycofany',
                            ])
                            ->default(Deployment::STATUS_PENDING)
                            ->native(false)
                            ->disabled(),

                        Forms\Components\Textarea::make('metadata')
                            ->label('Metadane (JSON)')
                            ->json()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('domain.domain')
                    ->label('Domena')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('version')
                    ->label('Wersja')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Deployment::STATUS_COMPLETED => 'success',
                        Deployment::STATUS_IN_PROGRESS => 'warning',
                        Deployment::STATUS_FAILED => 'danger',
                        Deployment::STATUS_ROLLED_BACK => 'gray',
                        default => 'info',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Deployment::STATUS_PENDING => 'Oczekujący',
                        Deployment::STATUS_IN_PROGRESS => 'W trakcie',
                        Deployment::STATUS_COMPLETED => 'Zakończony',
                        Deployment::STATUS_FAILED => 'Nieudany',
                        Deployment::STATUS_ROLLED_BACK => 'Wycofany',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('started_at')
                    ->label('Rozpoczęto')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Zakończono')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        Deployment::STATUS_PENDING => 'Oczekujący',
                        Deployment::STATUS_IN_PROGRESS => 'W trakcie',
                        Deployment::STATUS_COMPLETED => 'Zakończony',
                        Deployment::STATUS_FAILED => 'Nieudany',
                        Deployment::STATUS_ROLLED_BACK => 'Wycofany',
                    ])
                    ->native(false),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Od'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Do'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('deploy')
                    ->label('Wdróż')
                    ->icon('heroicon-o-rocket-launch')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Deployment $record): void {
                        if ($record->isInProgress()) {
                            Notification::make()
                                ->title('Deployment już w toku')
                                ->warning()
                                ->send();

                            return;
                        }

                        if (! $record->domain) {
                            Notification::make()
                                ->title('Brak domeny')
                                ->body('Przypisz domenę do deploymentu')
                                ->danger()
                                ->send();

                            return;
                        }

                        // Utwórz nowy deployment
                        $newDeployment = Deployment::create([
                            'domain_id' => $record->domain_id,
                            'project_id' => $record->project_id,
                            'status' => Deployment::STATUS_PENDING,
                        ]);

                        // Get local project path from deployment metadata or use default templates path
                        // If deployment has metadata with template_path, use it; otherwise construct from domain
                        $localPath = $record->metadata['template_path'] ?? null;
                        
                        if (! $localPath) {
                            // Fallback: try to construct path from domain name
                            // Assuming domain format: {template}.{domain} or {subdomain}.{domain}
                            $domainParts = explode('.', $record->domain->domain);
                            $templateSlug = $domainParts[0]; // First part is usually template slug
                            
                            // Try common template locations
                            $possiblePaths = [
                                base_path("templates/{$templateSlug}/out"),
                                base_path("templates/{$templateSlug}"),
                                base_path("../next-templates/{$templateSlug}"),
                            ];
                            
                            $localPath = null;
                            foreach ($possiblePaths as $path) {
                                if (is_dir($path)) {
                                    $localPath = $path;
                                    break;
                                }
                            }
                        }
                        
                        if (! $localPath || ! is_dir($localPath)) {
                            Notification::make()
                                ->title('Brak lokalnej ścieżki projektu')
                                ->body('Nie można znaleźć lokalnej ścieżki do projektu. Użyj komendy artisan deploy:template lub ustaw template_path w metadata deploymentu.')
                                ->danger()
                                ->send();
                            
                            return;
                        }

                        // Uruchom job z lokalną ścieżką projektu
                        Queue::push(new DeployProjectJob(
                            $newDeployment->id,
                            $record->domain->domain,
                            $localPath
                        ));

                        Notification::make()
                            ->title('Deployment uruchomiony')
                            ->body("Deployment #{$newDeployment->id} został dodany do kolejki")
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Deployment $record): bool => ! $record->isInProgress() && $record->domain !== null),
                Tables\Actions\Action::make('view_logs')
                    ->label('Logi')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->modalHeading('Logi deploymentu')
                    ->modalContent(fn (Deployment $record) => view('livewire.deployment-logs', [
                        'deploymentId' => $record->id,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Zamknij')
                    ->modalWidth('4xl'),
                Tables\Actions\Action::make('rollback')
                    ->label('Rollback')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Deployment $record): bool => $record->isCompleted())
                    ->action(function (Deployment $record): void {
                        // TODO: Implementacja rollback
                        Notification::make()
                            ->title('Rollback')
                            ->body('Funkcja rollback będzie dostępna wkrótce')
                            ->info()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeployments::route('/'),
            'create' => Pages\CreateDeployment::route('/create'),
            'edit' => Pages\EditDeployment::route('/{record}/edit'),
        ];
    }
}
