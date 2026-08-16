<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private string $token;
    private string $chatId;
    private bool   $enabled;

    public function __construct()
    {
        $this->token   = config('services.telegram.bot_token','');
        $this->chatId  = config('services.telegram.chat_id','');
        $this->enabled = !empty($this->token) && !empty($this->chatId);
    }

    public function send(string $message): void
    {
        if (!$this->enabled) {
            Log::info('Telegram (disabled): '.$message);
            return;
        }
        try {
            Http::post("https://api.telegram.org/bot{$this->token}/sendMessage", [
                'chat_id'    => $this->chatId,
                'text'       => $message,
                'parse_mode' => 'HTML',
            ]);
        } catch (\Throwable $e) {
            Log::error('Telegram send failed: '.$e->getMessage());
        }
    }
}
