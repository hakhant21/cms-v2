<?php

namespace App\Filament\Resources\ContactMessages\Pages;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Models\ContactMessage;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/** Opening a message is how the team reads it, so that marks it read. */
class EditContactMessage extends EditRecord
{
    protected static string $resource = ContactMessageResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($this->getRecord() instanceof ContactMessage) {
            $this->getRecord()->markRead();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markReplied')
                ->label('Mark replied')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (ContactMessage $record): bool => $record->status !== ContactMessage::STATUS_REPLIED)
                ->action(function (ContactMessage $record): void {
                    $record->markReplied();
                    $this->refreshFormData(['status']);
                }),
            Action::make('archive')
                ->label('Archive')
                ->icon('heroicon-o-archive-box')
                ->requiresConfirmation()
                ->visible(fn (ContactMessage $record): bool => $record->status !== ContactMessage::STATUS_ARCHIVED)
                ->action(function (ContactMessage $record): void {
                    $record->forceFill(['status' => ContactMessage::STATUS_ARCHIVED])->save();
                    $this->refreshFormData(['status']);
                }),
            DeleteAction::make(),
        ];
    }
}
