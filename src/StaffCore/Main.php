<?php

declare(strict_types=1);

namespace StaffCore;

use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;
use StaffCore\commands\ModCommand;
use StaffCore\commands\StaffChatCommand;
use StaffCore\commands\UnbanCommand;
use StaffCore\database\DatabaseManager;
use StaffCore\event\PlayerListener;
use StaffCore\session\SessionManager;
use StaffCore\tasks\FetchGeoDataTask;
use StaffCore\tasks\SendWebhookTask;

class Main extends PluginBase {
    use SingletonTrait;

    private DatabaseManager $databaseManager;
    private SessionManager $sessionManager;
    private Config $messages;

    protected function onLoad(): void {
        self::setInstance($this);
    }

    protected function onEnable(): void {
        // Guardar configuraciones por defecto
        $this->saveDefaultConfig();
        $this->saveResource("messages.yml");
        $this->messages = new Config($this->getDataFolder() . "messages.yml", Config::YAML);

        // Inicializar gestores
        $this->databaseManager = new DatabaseManager($this->getDataFolder() . "staffcore.db");
        $this->sessionManager = new SessionManager();

        // Registrar eventos y comandos
        $this->getServer()->getPluginManager()->registerEvents(new PlayerListener(), $this);
        $commandMap = $this->getServer()->getCommandMap();
        $commandMap->register("staffcore", new ModCommand());
        $commandMap->register("staffcore", new UnbanCommand());
        $commandMap->register("staffcore", new StaffChatCommand()); // NUEVO

        $this->getLogger()->info("§aStaffCore v2.0.0 ha sido habilitado correctamente.");
    }

    public function getDatabaseManager(): DatabaseManager {
        return $this->databaseManager;
    }

    public function getSessionManager(): SessionManager {
        return $this->sessionManager;
    }

    public function getMessage(string $key, array $replacements = []): string {
        $message = $this->messages->getNested($key, "§cMessage not found: " . $key);
        // Reemplazar prefijo automáticamente si no está presente en la clave
        if (strpos($key, "prefix") === false) {
            $message = str_replace("{prefix}", $this->messages->get("prefix", "§c§lStaffCore §8»§r "), $message);
        }
        
        foreach ($replacements as $find => $replace) {
            $message = str_replace("{" . $find . "}", (string)$replace, $message);
        }
        return $message;
    }

    /**
     * Envía un Webhook a Discord de forma asíncrona para no generar lag.
     */
    public function sendDiscordWebhook(string $title, string $description, int $color, array $fields): void {
        $webhookUrl = $this->getConfig()->get("discord_webhook_url");
        if (empty($webhookUrl) || !is_string($webhookUrl)) {
            return;
        }

        $payload = [
            'embeds' => [[
                'title' => $title,
                'description' => $description,
                'color' => $color,
                'fields' => array_map(function($name, $value) {
                    return ['name' => $name, 'value' => $value, 'inline' => true];
                }, array_keys($fields), array_values($fields)),
                'footer' => ['text' => "StaffCore Plugin | " . date("Y-m-d H:i:s")]
            ]]
        ];
        
        $this->getServer()->getAsyncPool()->submitTask(new SendWebhookTask($webhookUrl, json_encode($payload)));
    }
    
    /**
     * Obtiene la información de geolocalización de una IP de forma asíncrona.
     */
    public function fetchGeoData(Player $player, callable $callback): void {
        $this->getServer()->getAsyncPool()->submitTask(new FetchGeoDataTask($player->getNetworkSession()->getIp(), $callback));
    }
}
