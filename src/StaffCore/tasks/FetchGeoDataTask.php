<?php

declare(strict_types=1);

namespace StaffCore\tasks;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;

class FetchGeoDataTask extends AsyncTask {

    public function __construct(
        private string $ip,
        private $callback
    ) {}

    public function onRun(): void {
        $url = "http://ip-api.com/json/" . $this->ip;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($response) {
            $data = json_decode($response, true);
            if ($data && $data['status'] === 'success') {
                $this->setResult(['country' => $data['country'] ?? 'Desconocido']);
                return;
            }
        }
        $this->setResult(['country' => 'Error al obtener']);
    }

    public function onCompletion(): void {
        $server = Server::getInstance();
        if ($server->isRunning()) {
            $callback = $this->callback;
            if (is_callable($callback)) {
                $callback($this->getResult());
            }
        }
    }
}
