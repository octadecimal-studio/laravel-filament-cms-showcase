<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Modules\Content\Models\Media;
use App\Modules\Content\Models\TwoWheels\Motorcycle;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class MotorcycleGalleryManager extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;
    use WithFileUploads;

    public string $motorcycleId = '';

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $newFiles = [];

    /**
     * Mount: accepts 'record' from Filament Livewire form component.
     */
    public function mount(?Model $record = null): void
    {
        if ($record) {
            $this->motorcycleId = (string) $record->getKey();
        }
    }

    public function getMotorcycleProperty(): Motorcycle
    {
        return Motorcycle::withoutGlobalScopes()->findOrFail($this->motorcycleId);
    }

    public function getImagesProperty(): \Illuminate\Support\Collection
    {
        return $this->motorcycle->gallery()
            ->orderBy('two_wheels_motorcycle_gallery.order')
            ->get();
    }

    public function deleteImageAction(): Action
    {
        return Action::make('deleteImage')
            ->requiresConfirmation()
            ->modalHeading('Usuń zdjęcie')
            ->modalDescription('Czy na pewno chcesz usunąć to zdjęcie z galerii?')
            ->modalSubmitActionLabel('Usuń')
            ->color('danger')
            ->action(function (array $arguments): void {
                $mediaId = $arguments['id'] ?? null;
                if (! $mediaId) {
                    return;
                }

                $motorcycle = $this->motorcycle;
                $motorcycle->gallery()->detach($mediaId);

                $media = Media::withoutGlobalScopes()->find($mediaId);
                if ($media) {
                    $disk = Storage::disk($media->disk ?? 'public');
                    if ($media->file_path && $disk->exists($media->file_path)) {
                        $disk->delete($media->file_path);
                    }
                    $media->delete();
                }

                Notification::make()
                    ->title('Zdjęcie usunięte')
                    ->success()
                    ->send();
            });
    }

    public function uploadFiles(): void
    {
        if (empty($this->newFiles)) {
            return;
        }

        $motorcycle = $this->motorcycle;
        $tenantId = $motorcycle->tenant_id;
        $disk = Storage::disk('public');
        $count = 0;

        foreach ($this->newFiles as $file) {
            $path = $file->store('motorcycles/gallery', 'public');
            if (! $path) {
                continue;
            }

            $media = new Media();
            $media->tenant_id = $tenantId;
            $media->file_name = $file->getClientOriginalName();
            $media->file_path = $path;
            $media->mime_type = $file->getMimeType() ?? 'image/jpeg';
            $media->size = $disk->size($path);
            $media->collection = 'motorcycles-gallery';
            $media->alt_text = Str::beforeLast($file->getClientOriginalName(), '.');
            $media->disk = 'public';
            $media->save();

            $maxOrder = $motorcycle->gallery()->max('two_wheels_motorcycle_gallery.order') ?? 0;
            $motorcycle->gallery()->attach($media->id, ['order' => $maxOrder + 1]);
            $count++;
        }

        $this->newFiles = [];

        Notification::make()
            ->title($count === 1 ? 'Dodano 1 zdjęcie' : "Dodano {$count} zdjęć")
            ->success()
            ->send();
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.motorcycle-gallery-manager', [
            'images' => $this->images,
        ]);
    }
}
