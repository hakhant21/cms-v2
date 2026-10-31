<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\SiteSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * One screen for everything that is not page content: company details, the
 * contact form, brand colours and social links. The controls are generated
 * from {@see SiteSettings}, so this class never grows as settings are added.
 */
class Settings extends Page
{
    protected string $view = 'filament.pages.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 100;

    protected static ?string $navigationLabel = 'Site settings';

    protected static ?string $title = 'Site settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $state = [];

        foreach (SiteSettings::values() as $key => $value) {
            $state[SiteSettings::fieldName($key)] = $value;
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Settings')
                    ->persistTabInQueryString()
                    ->tabs($this->tabs()),
            ])
            ->statePath('data');
    }

    /**
     * @return array<int, Tab>
     */
    private function tabs(): array
    {
        $tabs = [];

        foreach (SiteSettings::groups() as $group) {
            $tabs[] = Tab::make($group['label'])
                ->schema([
                    Section::make($group['label'])
                        ->description($group['description'])
                        ->columns(2)
                        ->schema($this->fields($group['fields'])),
                ]);
        }

        return $tabs;
    }

    /**
     * @param  array<string, array{label: string, type: string, help?: string, options?: array<string, string>}>  $fields
     * @return array<int, Component>
     */
    private function fields(array $fields): array
    {
        $components = [];

        foreach ($fields as $key => $field) {
            $components[] = $this->field($key, $field);
        }

        return $components;
    }

    /**
     * @param  array{label: string, type: string, help?: string, options?: array<string, string>}  $field
     */
    private function field(string $key, array $field): Component
    {
        $name = SiteSettings::fieldName($key);
        $help = $field['help'] ?? null;

        return match ($field['type']) {
            SiteSettings::TYPE_TEXTAREA => Textarea::make($name)
                ->label($field['label'])
                ->rows(3)
                ->helperText($help)
                ->columnSpanFull(),
            SiteSettings::TYPE_COLOR => ColorPicker::make($name)->label($field['label']),
            SiteSettings::TYPE_BOOL => Toggle::make($name)->label($field['label'])->helperText($help),
            SiteSettings::TYPE_SELECT => Select::make($name)
                ->label($field['label'])
                ->options($field['options'] ?? []),
            default => TextInput::make($name)->label($field['label'])->helperText($help),
        };
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save settings')
                ->action('save')
                ->color('primary'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach (SiteSettings::fields() as $key => $field) {
            $isBool = $field['type'] === SiteSettings::TYPE_BOOL;

            Setting::put(
                $key,
                $data[SiteSettings::fieldName($key)] ?? null,
                SiteSettings::categoryOf($key),
                $isBool ? Setting::TYPE_BOOL : Setting::TYPE_STRING,
            );
        }

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
