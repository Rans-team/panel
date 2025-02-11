<?php

namespace App\Filament\Pages;

use Filament\Forms;
use Filament\Pages\Page;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Filament\Notifications\Notification;

class Settings extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog';
    protected static string $view = 'filament.pages.settings';
    protected static ?string $title = '環境設定';
    protected static ?string $navigationGroup = 'ユーザー管理';

    public $APP_NAME;
    public $APP_DEBUG;
    public $APP_TIMEZONE;
    public $APP_URL;
    public $LOG_LEVEL;
    public $DB_CONNECTION;
    public $DB_HOST;
    public $DB_PORT;
    public $DB_DATABASE;
    public $DB_USERNAME;
    public $DB_PASSWORD;
    public $TURNSTILE_SITEKEY;
    public $TURNSTILE_SECRET;

    public function mount()
    {
        $this->form->fill([
            'APP_NAME'           => config('app.name', ''),
            'APP_DEBUG'          => config('app.debug', ''),
            'APP_TIMEZONE'       => config('app.timezone', ''),
            'APP_URL'            => config('app.url', ''),
            'LOG_LEVEL'          => config('logging.level', ''),
            'DB_CONNECTION'      => config('database.default', ''),
            'DB_HOST'            => config('database.connections.mysql.host', ''),
            'DB_PORT'            => config('database.connections.mysql.port', ''),
            'DB_DATABASE'        => config('database.connections.mysql.database', ''),
            'DB_USERNAME'        => config('database.connections.mysql.username', ''),
            'DB_PASSWORD'        => config('database.connections.mysql.password', ''),
            'TURNSTILE_SITEKEY'  => config('services.turnstile.sitekey', ''),
            'TURNSTILE_SECRET'   => config('services.turnstile.secret', ''),
        ]);
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('APP_NAME')
                ->label('APP_NAME')
                ->required(),
            Forms\Components\TextInput::make('APP_DEBUG')
                ->label('APP_DEBUG')
                ->required(),
            Forms\Components\TextInput::make('APP_TIMEZONE')
                ->label('APP_TIMEZONE')
                ->required(),
            Forms\Components\TextInput::make('APP_URL')
                ->label('APP_URL')
                ->url()
                ->required(),
            Forms\Components\Select::make('LOG_LEVEL')
                ->label('LOG_LEVEL')
                ->options([
                    'debug'     => 'debug',
                    'info'      => 'info',
                    'notice'    => 'notice',
                    'warning'   => 'warning',
                    'error'     => 'error',
                    'critical'  => 'critical',
                ])
                ->required(),
            Forms\Components\Select::make('DB_CONNECTION')
                ->label('DB_CONNECTION')
                ->options([
                    'sqlite' => 'SQLite',
                    'mysql'  => 'MySQL / MariaDB',
                ])
                ->required()
                ->reactive(),
            Forms\Components\TextInput::make('DB_HOST')
                ->label('DB_HOST')
                ->required()
                ->hidden(fn (callable $get): bool => $get('DB_CONNECTION') === 'sqlite'),
            Forms\Components\TextInput::make('DB_PORT')
                ->label('DB_PORT')
                ->required()
                ->hidden(fn (callable $get): bool => $get('DB_CONNECTION') === 'sqlite'),
            Forms\Components\TextInput::make('DB_DATABASE')
                ->label('DB_DATABASE')
                ->required()
                ->hidden(fn (callable $get): bool => $get('DB_CONNECTION') === 'sqlite'),
            Forms\Components\TextInput::make('DB_USERNAME')
                ->label('DB_USERNAME')
                ->required()
                ->hidden(fn (callable $get): bool => $get('DB_CONNECTION') === 'sqlite'),
            Forms\Components\TextInput::make('DB_PASSWORD')
                ->label('DB_PASSWORD')
                ->password()
                ->required()
                ->hidden(fn (callable $get): bool => $get('DB_CONNECTION') === 'sqlite'),
            Forms\Components\TextInput::make('TURNSTILE_SITEKEY')
                ->label('TURNSTILE_SITEKEY'),
            Forms\Components\TextInput::make('TURNSTILE_SECRET')
                ->label('TURNSTILE_SECRET'),
        ];
    }

    public function updateEnv(): void
    {
        $data = $this->form->getState();
        $envFilePath = base_path('.env');

        if (!File::exists($envFilePath)) {
            Notification::make()
                ->title('.env ファイルが存在しません')
                ->danger()
                ->send();
            return;
        }

        $envContent = File::get($envFilePath);
        $envLines   = explode("\n", $envContent);
        $keys = array_keys($data);

        foreach ($keys as $key) {
            $value = $data[$key];

            if (strpos($value, ' ') !== false) {
                $value = '"' . $value . '"';
            }
            $found = false;
            foreach ($envLines as $index => $line) {
                if (strpos(trim($line), '#') === 0 || trim($line) === '') {
                    continue;
                }

                if (preg_match('/^' . $key . '\s*=/', $line)) {
                    $envLines[$index] = $key . '=' . $value;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $envLines[] = $key . '=' . $value;
            }
        }

        $newEnvContent = implode("\n", $envLines);

        try {
            File::put($envFilePath, $newEnvContent);
        } catch (\Exception $e) {
            \Log::error('Failed to update .env file: ' . $e->getMessage());
            Notification::make()
                ->title('.env ファイルの更新に失敗しました')
                ->danger()
                ->send();
            return;
        }

        Artisan::call('config:clear');
        Notification::make()
            ->title('.env が更新されました')
            ->success()
            ->send();
    }

    protected function getActions(): array
    {
        return [
            \Filament\Pages\Actions\Action::make('save')
                ->label('保存')
                ->action('updateEnv'),
        ];
    }
}
