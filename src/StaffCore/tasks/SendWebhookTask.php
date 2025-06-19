<?php

declare(strict_types=1);

namespace StaffCore\tasks;

use pocketmine\scheduler\AsyncTask;

class SendWebhookTask extends AsyncTask {
    
    public function __construct(
        private string $webhookUrl,
        private string $messageJson
    ) {}

    public function onRun(): void {
        $ch = curl_init($this->webhookUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-type: application/json']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->messageJson);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Timeout de 5 segundos
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        curl_close($ch);
    }
}
