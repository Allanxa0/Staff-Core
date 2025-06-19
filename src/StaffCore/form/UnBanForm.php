<?php

declare(strict_types=1);

namespace StaffCore\form;

use jojoe77777\FormAPI\SimpleForm;
use pocketmine\player\Player;
use pocketmine\Server;
use StaffCore\Main;

class UnbanForm extends SimpleForm {

    private array $bannedPlayers;

    public function __construct(Player $player) {
        parent::__construct(function (Player $player, ?int $data) {
            if ($data === null) return;
            
            $targetName = $this->bannedPlayers[$data]['username'];
            $plugin = Main::getInstance();
            $db = $plugin->getDatabaseManager();

            if ($db->unbanPlayer($targetName)) {
                $player->sendMessage($plugin->getMessage("unban_success", ["target" => $targetName]));

                $broadcastMessage = $plugin->getMessage("unban_broadcast", [
                    "target" => $targetName,
                    "staff" => $player->getName()
                ]);
                Server::getInstance()->broadcastMessage($broadcastMessage);
            
                $plugin->sendDiscordWebhook("Jugador Desbaneado", "", 0x00FF00,
                    [
                        "Jugador" => $targetName,
                        "Staff" => $player->getName(),
                    ]
                );
            } else {
                 $player->sendMessage($plugin->getMessage("player_not_banned", ["target" => $targetName]));
            }
        });

        $this->setTitle("§8Desbanear Jugador");
        $this->setContent("Selecciona un jugador para revocar su sanción.");
        
        $this->bannedPlayers = Main::getInstance()->getDatabaseManager()->getAllBans();

        if (empty($this->bannedPlayers)) {
            $this->setContent("¡No hay jugadores baneados actualmente!");
        } else {
            foreach ($this->bannedPlayers as $banInfo) {
                $username = $banInfo['username'];
                $expire = $banInfo['expire_date'];
                
                if ($expire === PHP_INT_MAX) {
                    $timeLeft = "Permanente";
                } else {
                    $remaining = $expire - time();
                    $days = floor($remaining / 86400);
                    $hours = floor(($remaining % 86400) / 3600);
                    $timeLeft = "$days d $hours h restantes";
                }
                
                $banDetails = "§7Baneado por: §f" . $banInfo['staff_member'] . "\n";
                $banDetails .= "§7Tipo: §f" . $banInfo['ban_type'] . " §8| §7Tiempo: §f" . $timeLeft;

                $this->addButton("§l§8» §r" . $username . " §l§8«§r\n" . $banDetails, SimpleForm::IMAGE_TYPE_PATH, "textures/ui/icon_steve.png");
            }
        }
    }
}
